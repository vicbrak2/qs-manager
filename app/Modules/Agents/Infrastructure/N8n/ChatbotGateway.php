<?php

declare(strict_types=1);

namespace QS\Modules\Agents\Infrastructure\N8n;

use WP_Error;

final class ChatbotGateway
{
    private string $webhookUrl;

    public function __construct()
    {
        $this->webhookUrl = defined('QS_N8N_CHATBOT_URL')
            ? QS_N8N_CHATBOT_URL
            : 'http://localhost:5678/webhook/wp-chatbot-rag';
    }

    /**
     * Envía un mensaje al agente de n8n y devuelve la respuesta en texto plano.
     * Retorna null si n8n no está disponible o responde con error.
     */
    public function ask(string $message, string $sessionId): string|WP_Error
    {
        $response = wp_remote_post($this->webhookUrl, [
            'body'    => json_encode([
                'message'    => $message,
                'session_id' => $sessionId,
            ]),
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'timeout' => 45,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error(
                'n8n_connection_error',
                'No se pudo conectar con el agente.',
                ['status' => 503]
            );
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            return new WP_Error(
                'n8n_processing_error',
                'El agente devolvió un error inesperado.',
                ['status' => $code]
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        // El workflow activo puede devolver 'reply' (Respond to Webhook) u 'output' (lastNode del AI Agent)
        return (string) ($body['reply'] ?? $body['output'] ?? $body['response'] ?? '');
    }
}
