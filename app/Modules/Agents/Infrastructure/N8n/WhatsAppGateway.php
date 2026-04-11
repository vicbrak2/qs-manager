<?php

declare(strict_types=1);

namespace QS\Modules\Agents\Infrastructure\N8n;

use QS\Core\Logging\Logger;

final class WhatsAppGateway
{
    private const OPTION_NAME = 'qs_n8n_whatsapp_url';

    private string $webhookUrl;

    public function __construct(
        private readonly Logger $logger
    ) {
        $this->webhookUrl = $this->resolveWebhookUrl();
    }

    /**
     * Envía un mensaje de WhatsApp a través del webhook híbrido de n8n.
     *
     * @return array{ok: bool, status_code: int|null, error: string|null, response_body: string}
     */
    public function send(string $phone, string $text, bool $esCritico = false): array
    {
        $body = wp_json_encode([
            'phone'     => $phone,
            'text'      => $text,
            'esCritico' => $esCritico,
        ]);

        if (! is_string($body)) {
            return [
                'ok' => false,
                'status_code' => null,
                'error' => 'No se pudo serializar el mensaje de WhatsApp.',
                'response_body' => '',
            ];
        }

        $response = wp_remote_post($this->webhookUrl, [
            'body'    => $body,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            /** @var \WP_Error $response */
            $error = $response->get_error_message();

            $this->logger->warning(
                sprintf('QS WhatsApp send failed to %s: %s', $phone, $error)
            );

            return [
                'ok' => false,
                'status_code' => null,
                'error' => $error,
                'response_body' => '',
            ];
        }

        /** @var array<string, mixed> $response */
        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $responseBody = (string) wp_remote_retrieve_body($response);
        $ok = $statusCode === 200;

        if (! $ok) {
            $this->logger->warning(
                sprintf('QS WhatsApp send failed to %s with HTTP %d. Body: %s', $phone, $statusCode, $responseBody)
            );
        }

        return [
            'ok' => $ok,
            'status_code' => $statusCode,
            'error' => $ok ? null : 'HTTP ' . $statusCode,
            'response_body' => $responseBody,
        ];
    }

    public function webhookUrl(): string
    {
        return $this->webhookUrl;
    }

    private function resolveWebhookUrl(): string
    {
        if (defined('QS_N8N_WHATSAPP_URL') && is_string(QS_N8N_WHATSAPP_URL) && QS_N8N_WHATSAPP_URL !== '') {
            return QS_N8N_WHATSAPP_URL;
        }

        $envValue = getenv('QS_N8N_WHATSAPP_URL');

        if (is_string($envValue) && trim($envValue) !== '') {
            return trim($envValue);
        }

        $optionValue = get_option(self::OPTION_NAME, '');

        if (is_string($optionValue) && trim($optionValue) !== '') {
            return trim($optionValue);
        }

        return 'http://localhost:5678/webhook/hybrid-whatsapp';
    }
}
