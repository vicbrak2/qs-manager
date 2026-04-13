<?php

declare(strict_types=1);

namespace QS\Modules\Agents\Infrastructure\N8n;

use QS\Modules\Agents\Infrastructure\Chatbot\QuickReplyMatcher;
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

    public function __construct(
        private readonly QuickReplyMatcher $quickReplyMatcher
    ) {
        $this->webhookUrl = $this->resolveWebhookUrl();
    }

    /**
     * Envía un mensaje al agente de n8n y devuelve la respuesta en texto plano.
     * Retorna null si n8n no está disponible o responde con error.
     */
    public function ask(string $message, string $sessionId): string|WP_Error
    {
        $message = $this->truncateInput($message);

        $bookingFlowReply = $this->handleBookingFlow($message, $sessionId);

        if ($bookingFlowReply !== null) {
            $this->appendToHistory($sessionId, $message, $bookingFlowReply);

            return $bookingFlowReply;
        }

        $greetingReply = $this->greetingReply($message);

        if ($greetingReply !== null) {
            $this->appendToHistory($sessionId, $message, $greetingReply);

            return $greetingReply;
        }

        $quickReply = $this->quickReplyMatcher->match($message);

        if ($quickReply !== null) {
            $this->appendToHistory($sessionId, $message, $quickReply);

            return $quickReply;
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
            'session_id' => $sessionId,
            'history'    => $historyContext,
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

    private function replyCacheKey(string $sessionId, string $message, string $history = ''): string
    {
        return 'qs_n8n_reply_' . md5($sessionId . '|' . $message . '|' . $history);
    }

    private function historyKey(string $sessionId): string
    {
        return 'qs_chat_hist_' . md5($sessionId);
    }

    private function bookingFlowKey(string $sessionId): string
    {
        return 'qs_booking_flow_' . md5($sessionId);
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

        if (preg_match('/\b(quiero|deseo|necesito|me gustaria|voy a|si quiero|si deseo)\s+(reservar|agendar)\b/u', $normalized) === 1) {
            return true;
        }

        if (preg_match('/\b(reservar una hora|agendar una hora|tomar una hora|hacer una reserva)\b/u', $normalized) === 1) {
            return true;
        }

        return $this->isAffirmative($normalized) && $this->lastBotAskedBookingIntent($sessionId);
    }

    private function isAffirmative(string $normalized): bool
    {
        return preg_match('/^(si|sip|sii|si quiero|si por favor|dale|ok|ya|claro|confirmo|quiero reservar|quiero agendar)$/u', $normalized) === 1;
    }

    private function lastBotAskedBookingIntent(string $sessionId): bool
    {
        return str_contains($this->normalizeIntentText($this->getHistoryContext($sessionId)), 'deseas reservar');
    }

    private function bookingServicePrompt(): string
    {
        return "Perfecto, para reservar lo vemos paso a paso.\nPrimero dime que servicio necesitas:\n- Maquillaje social\n- Peinado\n- Combo social maquillaje + peinado\n- Novia civil\n- Novia fiesta";
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

    private function greetingReply(string $message): ?string
    {
        $normalized = $this->normalizeText($message);

        if ($normalized === '') {
            return null;
        }

        if (! preg_match('/^(hola|hi|buenas|buenos dias|buenas tardes|buenas noches|hey|saludos)[[:space:]!,.?]*$/u', $normalized)) {
            return null;
        }

        return 'Cuentame, te interesa ver servicios, precios o reservas?';
    }

    private function rewriteMessageForContext(string $message): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? $message);

        if ($message === '') {
            return $message;
        }

        $normalized = $this->normalizeText($message);

        // Normaliza alias frecuentes para no perder contexto por variaciones de escritura.
        $normalized = preg_replace('/\b(cami\s*luna|qami\s*luna|camiluna)\b/u', 'qamiluna studio', $normalized) ?? $normalized;

        return match (true) {
            preg_match('/^(reserva|reservas|agendar|agenda|disponibilidad)$/u', $normalized) === 1
                => 'Quiero informacion sobre reservas, disponibilidad, abono y como agendar en Qamiluna Studio.',
            preg_match('/^(precio|precios|valor|valores|cuanto sale|cuanto cuesta)$/u', $normalized) === 1
                => 'Quiero informacion sobre precios referenciales y como cotizar en Qamiluna Studio.',
            preg_match('/^(servicios|que servicios tienen|que servicios ofrecen|listalos|listalas|lista los servicios|lista las opciones|enumera los servicios|cuales son los servicios|cu[aá]les son los servicios)$/u', $normalized) === 1
                => 'Quiero una lista clara de los servicios principales de Qamiluna Studio.',
            preg_match('/^(novia|novias|novia civil|novia fiesta)$/u', $normalized) === 1
                => 'Quiero informacion sobre servicios para novia civil y novia fiesta en Qamiluna Studio.',
            preg_match('/^(qamiluna|qamiluna studio|cami luna|qami luna|estudio)$/u', $normalized) === 1
                => 'Quiero una descripcion breve de Qamiluna Studio y sus servicios principales.',
            preg_match('/^(que es qamiluna studio|que es qamiluna|que es cami luna|hablame sobre el estudio|hablame del estudio|pasame informacion|informacion|info)$/u', $normalized) === 1
                => 'Quiero una descripcion breve de Qamiluna Studio, sus servicios principales y como funciona la atencion.',
            default => preg_replace('/\b(cami\s*luna|qami\s*luna|camiluna)\b/ui', 'Qamiluna Studio', $message) ?? $message,
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
