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

        $greetingReply = $this->greetingReply($message);

        if ($greetingReply !== null) {
            return $greetingReply;
        }

        $quickReply = $this->quickReplyMatcher->match($message);

        if ($quickReply !== null) {
            return $quickReply;
        }

        $rewrittenMessage = $this->rewriteMessageForContext($message);

        $cacheKey = $this->replyCacheKey($sessionId, $rewrittenMessage);
        $cached   = get_transient($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $body = wp_json_encode([
            'message'    => $rewrittenMessage,
            'session_id' => $sessionId,
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

    private function replyCacheKey(string $sessionId, string $message): string
    {
        return 'qs_n8n_reply_' . md5($sessionId . '|' . $message);
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
