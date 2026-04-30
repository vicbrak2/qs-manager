<?php

declare(strict_types=1);

namespace QS\Modules\Agents\Infrastructure\N8n;

use QS\Modules\Agents\Infrastructure\Chatbot\ChatbotProfile;
use WP_Error;

final class ChatbotGateway
{
    private const OPTION_NAME = 'qs_n8n_chatbot_url';
    private const REPLY_CACHE_TTL = 1800; // 30 minutes
    private const MAX_INPUT_CHARS = 800;
    private const HISTORY_CACHE_TTL = 3600; // 1 hour
    private const HISTORY_MAX_TURNS = 5;
    private const BOOKING_FLOW_TTL = 3600; // 1 hour

    private string $webhookUrl;
    private ChatbotProfile $profile;

    public function __construct(
        private readonly WhatsAppGateway $whatsAppGateway,
        ?ChatbotProfile $profile = null
    ) {
        $this->profile = $profile ?? ChatbotProfile::resolveDefault();
        $this->webhookUrl = $this->resolveWebhookUrl();
    }

    /**
     * Envía un mensaje al agente de n8n y devuelve la respuesta en texto plano.
     * Retorna null si n8n no está disponible o responde con error.
     */
    public function ask(string $message, string $sessionId, string $channel = 'web'): string|WP_Error
    {
        $message = $this->truncateInput($message);

        // Booking flow: captura datos estructurados cuando el usuario confirma
        // que quiere reservar usando una frase explícita de reserva.
        // Las respuestas conversacionales y de información van siempre al LLM.
        $bookingFlowReply = $this->handleBookingFlow($message, $sessionId);

        if ($bookingFlowReply !== null) {
            $this->appendToHistory($sessionId, $message, $bookingFlowReply);

            return $bookingFlowReply;
        }

        $rewrittenMessage = $this->rewriteMessageForContext($message);
        $historyContext   = $this->getHistoryContext($sessionId);

        $cacheKey = $this->replyCacheKey($sessionId, $rewrittenMessage, $historyContext);
        $cached   = get_transient($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $body = wp_json_encode([
            'message'    => $rewrittenMessage,
            'prompt'     => $this->buildAgentPrompt($rewrittenMessage, $historyContext),
            'session_id' => $sessionId,
            'history'    => $historyContext,
            'channel'    => $this->sanitizeChannel($channel),
            'site_id'    => $this->profile->siteId(),
            'profile'    => $this->profile->toArray(),
            'vector_collection' => $this->profile->vectorCollection(),
            'retrieval_top_k' => $this->profile->retrievalTopK(),
            'memory_source' => 'wordpress',
        ]);

        if (! is_string($body)) {
            return new WP_Error(
                'n8n_encoding_error',
                'No se pudo serializar la solicitud al agente.',
                ['status' => 500]
            );
        }

        $response = wp_remote_post($this->webhookUrl, [
            'body'    => $body,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'timeout' => 90,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error(
                'n8n_connection_error',
                'No se pudo conectar con el agente.',
                ['status' => 503]
            );
        }

        /** @var array<string, mixed> $response */
        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            return new WP_Error(
                'n8n_processing_error',
                'El agente devolvió un error inesperado.',
                ['status' => $code]
            );
        }

        $body  = json_decode(wp_remote_retrieve_body($response), true);
        $reply = is_array($body) ? $this->extractReply($body) : '';

        if ($reply !== '') {
            set_transient($cacheKey, $reply, self::REPLY_CACHE_TTL);
            $this->appendToHistory($sessionId, $message, $reply);
        }

        return $reply;
    }

    public function webhookUrl(): string
    {
        return $this->webhookUrl;
    }

    public function profile(): ChatbotProfile
    {
        return $this->profile;
    }

    private function truncateInput(string $message): string
    {
        $trimmed = trim($message);

        if ($trimmed === '') {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($trimmed, 0, self::MAX_INPUT_CHARS);
        }

        return substr($trimmed, 0, self::MAX_INPUT_CHARS);
    }

    private function sanitizeChannel(string $channel): string
    {
        $channel = strtolower(trim($channel));
        $channel = preg_replace('/[^a-z0-9_\-]/', '', $channel);

        return is_string($channel) && $channel !== '' ? substr($channel, 0, 40) : 'web';
    }

    private function buildAgentPrompt(string $message, string $history): string
    {
        $profile = $this->profile->toArray();
        $lines = [
            '--- Perfil del sitio ---',
            'site_id: ' . $profile['site_id'],
            'marca: ' . $profile['brand_name'],
            'locale: ' . $profile['locale'],
            'tono: ' . $profile['tone'],
            'whatsapp: ' . $profile['whatsapp_url'],
            'aliases: ' . implode(', ', $profile['aliases']),
            'servicios: ' . implode(', ', $profile['services']),
            'detalle_servicios: ' . $this->formatServiceDetails($profile['service_details']),
            'campos_reserva: ' . implode(', ', $profile['booking_fields']),
            'restricciones: ' . implode(' ', $profile['restrictions']),
            '--- Fin perfil ---',
        ];

        if ($history !== '') {
            $lines[] = '--- Historial previo curado por WordPress ---';
            $lines[] = $history;
            $lines[] = '--- Fin historial ---';
        }

        $lines[] = 'Mensaje actual: ' . $message;

        return implode("\n", $lines);
    }

    private function replyCacheKey(string $sessionId, string $message, string $history = ''): string
    {
        return 'qs_n8n_reply_' . md5($this->profile->siteId() . '|' . $sessionId . '|' . $message . '|' . $history);
    }

    /**
     * @param array<string, string> $serviceDetails
     */
    private function formatServiceDetails(array $serviceDetails): string
    {
        if ($serviceDetails === []) {
            return '';
        }

        $lines = [];

        foreach ($serviceDetails as $service => $detail) {
            $service = trim($service);
            $detail = trim($detail);

            if ($service === '' || $detail === '') {
                continue;
            }

            $lines[] = $service . ': ' . $detail;
        }

        return implode(' | ', $lines);
    }

    private function historyKey(string $sessionId): string
    {
        return 'qs_chat_hist_' . md5($this->profile->siteId() . '|' . $sessionId);
    }

    private function bookingFlowKey(string $sessionId): string
    {
        return 'qs_booking_flow_' . md5($this->profile->siteId() . '|' . $sessionId);
    }

    private function appendToHistory(string $sessionId, string $userMsg, string $botReply): void
    {
        $key      = $this->historyKey($sessionId);
        $existing = get_transient($key);
        $turns    = is_string($existing) ? (json_decode($existing, true) ?? []) : [];
        $turns    = is_array($turns) ? $turns : [];

        $turns[] = [
            'u' => mb_substr($userMsg, 0, 200),
            'b' => mb_substr($botReply, 0, 300),
        ];

        if (count($turns) > self::HISTORY_MAX_TURNS) {
            $turns = array_slice($turns, -self::HISTORY_MAX_TURNS);
        }

        $encoded = json_encode($turns);

        if (is_string($encoded)) {
            set_transient($key, $encoded, self::HISTORY_CACHE_TTL);
        }
    }

    private function getHistoryContext(string $sessionId): string
    {
        $key      = $this->historyKey($sessionId);
        $existing = get_transient($key);

        if (! is_string($existing) || $existing === '') {
            return '';
        }

        $turns = json_decode($existing, true);

        if (! is_array($turns) || $turns === []) {
            return '';
        }

        $lines = [];

        foreach ($turns as $turn) {
            if (! is_array($turn)) {
                continue;
            }

            $lines[] = 'Usuaria: ' . ($turn['u'] ?? '');
            $lines[] = 'Asistente: ' . ($turn['b'] ?? '');
        }

        return implode("\n", $lines);
    }

    private function handleBookingFlow(string $message, string $sessionId): ?string
    {
        if ($message === '') {
            return null;
        }

        $state = $this->getBookingFlowState($sessionId);

        if ($state !== null) {
            return $this->advanceBookingFlow($message, $sessionId, $state);
        }

        if (! $this->shouldStartBookingFlow($message, $sessionId)) {
            return null;
        }

        $this->setBookingFlowState($sessionId, [
            'stage' => 'service',
            'data' => [],
        ]);

        return $this->bookingServicePrompt();
    }

    /**
     * @param array{stage: string, data: array<string, string>} $state
     */
    private function advanceBookingFlow(string $message, string $sessionId, array $state): string
    {
        $stage = $state['stage'];
        $data = $state['data'];

        if ($stage === 'service') {
            $data['service'] = $message;
            $this->setBookingFlowState($sessionId, [
                'stage' => 'comuna',
                'data' => $data,
            ]);

            return 'Perfecto. En que comuna seria el servicio?';
        }

        if ($stage === 'comuna') {
            $data['comuna'] = $message;
            $this->setBookingFlowState($sessionId, [
                'stage' => 'address',
                'data' => $data,
            ]);

            return 'Gracias. Ahora enviame la direccion donde seria la atencion.';
        }

        if ($stage === 'address') {
            $data['address'] = $message;
            $this->setBookingFlowState($sessionId, [
                'stage' => 'phone',
                'data' => $data,
            ]);

            return 'Me compartes tu telefono de contacto?';
        }

        if ($stage === 'phone') {
            $data['phone'] = $message;
            $this->setBookingFlowState($sessionId, [
                'stage' => 'date',
                'data' => $data,
            ]);

            return 'Para que fecha quieres reservar?';
        }

        $data['date'] = $message;
        $this->clearBookingFlowState($sessionId);

        $this->sendBookingWhatsApp($data, $sessionId);

        return 'Listo, ya tengo los datos base para revisar tu reserva. El equipo confirma disponibilidad y siguientes pasos antes de bloquear la fecha.';
    }

    /**
     * @return array{stage: string, data: array<string, string>}|null
     */
    private function getBookingFlowState(string $sessionId): ?array
    {
        $existing = get_transient($this->bookingFlowKey($sessionId));

        if (! is_string($existing) || $existing === '') {
            return null;
        }

        $decoded = json_decode($existing, true);

        if (! is_array($decoded) || ! isset($decoded['stage']) || ! is_string($decoded['stage'])) {
            return null;
        }

        $data = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : [];
        $normalizedData = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $normalizedData[$key] = $value;
            }
        }

        return [
            'stage' => $decoded['stage'],
            'data' => $normalizedData,
        ];
    }

    /**
     * @param array{stage: string, data: array<string, string>} $state
     */
    private function setBookingFlowState(string $sessionId, array $state): void
    {
        $encoded = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (is_string($encoded)) {
            set_transient($this->bookingFlowKey($sessionId), $encoded, self::BOOKING_FLOW_TTL);
        }
    }

    /**
     * Envía un WhatsApp de resumen de reserva al número de la operadora (defaultPhone del gateway),
     * incluyendo los datos del cliente y un resumen de la conversación.
     *
     * @param array<string, string> $data
     * @param string $sessionId Usado para recuperar el historial de conversación
     */
    private function sendBookingWhatsApp(array $data, string $sessionId): void
    {
        $brand    = $this->profile->brandName();
        $service  = $data['service'] ?? 'No especificado';
        $comuna   = $data['comuna']  ?? 'No especificada';
        $address  = $data['address'] ?? 'No especificada';
        $phone    = $data['phone']   ?? 'No entregado';
        $date     = $data['date']    ?? 'No especificada';

        $lines = [
            "📋 *Nueva solicitud de reserva — {$brand}*",
            '',
            "• *Servicio:* {$service}",
            "• *Fecha:* {$date}",
            "• *Comuna:* {$comuna}",
            "• *Dirección:* {$address}",
            "• *Teléfono cliente:* {$phone}",
        ];

        $history = $this->getHistoryContext($sessionId);

        if ($history !== '') {
            $lines[] = '';
            $lines[] = '💬 *Resumen conversación:*';

            foreach (explode("\n", $history) as $historyLine) {
                $trimmed = trim($historyLine);

                if ($trimmed !== '') {
                    $lines[] = $trimmed;
                }
            }
        }

        $text = implode("\n", $lines);

        $this->whatsAppGateway->send('', $text);
    }

    private function clearBookingFlowState(string $sessionId): void
    {
        if (function_exists('delete_transient')) {
            delete_transient($this->bookingFlowKey($sessionId));
            return;
        }

        set_transient($this->bookingFlowKey($sessionId), '', 1);
    }

    private function shouldStartBookingFlow(string $message, string $sessionId): bool
    {
        $normalized = $this->normalizeIntentText($message);

        // Solo activa el flujo PHP si la usuaria expresa explícitamente que quiere
        // reservar/agendar. Las respuestas afirmativas solas ("si", "dale") van al LLM
        // para que él decida el contexto y continúe la conversación naturalmente.
        if (preg_match('/\b(quiero|deseo|necesito|me gustaria|voy a)\s+(reservar|agendar)\b/u', $normalized) === 1) {
            return true;
        }

        if (preg_match('/\b(reservar una hora|agendar una hora|tomar una hora|hacer una reserva|quiero reservar ahora|quiero agendar ahora)\b/u', $normalized) === 1) {
            return true;
        }

        return false;
    }

    private function bookingServicePrompt(): string
    {
        $services = $this->profile->services();

        if ($services === []) {
            return 'Perfecto, para reservar lo vemos paso a paso. Primero dime que servicio necesitas.';
        }

        return "Perfecto, para reservar lo vemos paso a paso.\nPrimero dime que servicio necesitas:\n- " . implode("\n- ", $services);
    }

    private function resolveWebhookUrl(): string
    {
        if (defined('QS_N8N_CHATBOT_URL') && is_string(QS_N8N_CHATBOT_URL) && QS_N8N_CHATBOT_URL !== '') {
            return QS_N8N_CHATBOT_URL;
        }

        $envValue = getenv('QS_N8N_CHATBOT_URL');

        if (is_string($envValue) && trim($envValue) !== '') {
            return trim($envValue);
        }

        $optionValue = get_option(self::OPTION_NAME, '');

        if (is_string($optionValue) && trim($optionValue) !== '') {
            return trim($optionValue);
        }

        return 'http://localhost:5678/webhook/wp-chatbot-rag';
    }

    private function rewriteMessageForContext(string $message): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? $message);

        if ($message === '') {
            return $message;
        }

        $normalized = $this->normalizeText($message);

        $brandName = $this->profile->brandName();
        $aliasPattern = $this->aliasPattern();

        if ($aliasPattern !== '') {
            $normalized = preg_replace($aliasPattern, $this->normalizeText($brandName), $normalized) ?? $normalized;
        }

        return match (true) {
            preg_match('/^(reserva|reservas|agendar|agenda|disponibilidad)$/u', $normalized) === 1
                => sprintf('Quiero informacion sobre reservas, disponibilidad, abono y como agendar en %s.', $brandName),
            preg_match('/^(precio|precios|valor|valores|cuanto sale|cuanto cuesta)$/u', $normalized) === 1
                => sprintf('Quiero informacion sobre precios referenciales y como cotizar en %s.', $brandName),
            preg_match('/^(servicios|que servicios tienen|que servicios ofrecen|listalos|listalas|lista los servicios|lista las opciones|enumera los servicios|cuales son los servicios|cu[aá]les son los servicios)$/u', $normalized) === 1
                => sprintf('Quiero una lista clara de los servicios principales de %s.', $brandName),
            preg_match('/^(novia|novias|novia civil|novia fiesta)$/u', $normalized) === 1
                => sprintf('Quiero informacion sobre servicios para novia en %s.', $brandName),
            $this->isBrandOnlyQuery($normalized)
                => sprintf('Quiero una descripcion breve de %s y sus servicios principales.', $brandName),
            preg_match('/^(hablame sobre el estudio|hablame del estudio|pasame informacion|informacion|info)$/u', $normalized) === 1
                => sprintf('Quiero una descripcion breve de %s, sus servicios principales y como funciona la atencion.', $brandName),
            default => $this->normalizeAliasesInOriginalMessage($message),
        };
    }

    private function normalizeText(string $message): string
    {
        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower(trim($message))
            : strtolower(trim($message));

        return trim((string) preg_replace('/\s+/', ' ', $normalized));
    }

    private function normalizeIntentText(string $message): string
    {
        $normalized = $this->normalizeText($message);

        if (function_exists('remove_accents')) {
            $normalized = remove_accents($normalized);
        } else {
            $normalized = strtr($normalized, [
                'á' => 'a',
                'é' => 'e',
                'í' => 'i',
                'ó' => 'o',
                'ú' => 'u',
                'ü' => 'u',
                'ñ' => 'n',
            ]);
        }

        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized) ?? $normalized;

        return trim((string) preg_replace('/\s+/', ' ', $normalized));
    }

    private function aliasPattern(): string
    {
        $aliases = array_merge([$this->profile->brandName()], $this->profile->aliases());
        $patterns = [];

        foreach ($aliases as $alias) {
            $normalized = $this->normalizeText($alias);

            if ($normalized === '') {
                continue;
            }

            $parts = preg_split('/\s+/u', $normalized) ?: [];
            $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));

            if ($parts === []) {
                continue;
            }

            $patterns[] = implode('\s*', array_map(static fn (string $part): string => preg_quote($part, '/'), $parts));
        }

        $patterns = array_values(array_unique($patterns));

        return $patterns !== [] ? '/\b(' . implode('|', $patterns) . ')\b/u' : '';
    }

    private function isBrandOnlyQuery(string $normalized): bool
    {
        $brand = $this->normalizeText($this->profile->brandName());
        $aliases = array_map(fn (string $alias): string => $this->normalizeText($alias), $this->profile->aliases());
        $candidates = array_values(array_filter(array_unique(array_merge([$brand, 'estudio'], $aliases))));

        return in_array($normalized, $candidates, true)
            || in_array($normalized, array_map(static fn (string $candidate): string => 'que es ' . $candidate, $candidates), true);
    }

    private function normalizeAliasesInOriginalMessage(string $message): string
    {
        $aliasPattern = $this->aliasPattern();

        if ($aliasPattern === '') {
            return $message;
        }

        return preg_replace($aliasPattern . 'i', $this->profile->brandName(), $message) ?? $message;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function extractReply(array $body): string
    {
        foreach (['reply', 'output', 'response'] as $key) {
            if (! array_key_exists($key, $body)) {
                continue;
            }

            $text = $this->extractTextValue($body[$key]);

            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function extractTextValue(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (! is_array($value)) {
            return '';
        }

        foreach (['texto', 'text', 'output', 'content'] as $key) {
            if (! array_key_exists($key, $value)) {
                continue;
            }

            $nested = $this->extractTextValue($value[$key]);

            if ($nested !== '') {
                return $nested;
            }
        }

        return '';
    }
}
