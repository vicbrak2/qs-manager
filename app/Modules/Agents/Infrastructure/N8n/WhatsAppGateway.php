<?php

declare(strict_types=1);

namespace QS\Modules\Agents\Infrastructure\N8n;

use QS\Core\Logging\Logger;

final class WhatsAppGateway
{
    private const OPTION_NAME = 'qs_n8n_whatsapp_url';
    private const PHONE_OPTION_NAME = 'qs_n8n_whatsapp_phone';

    private string $webhookUrl;
    private string $defaultPhone;

    public function __construct(
        private readonly Logger $logger
    ) {
        $this->webhookUrl = $this->resolveWebhookUrl();
        $this->defaultPhone = $this->resolveDefaultPhone();
    }

    /**
     * Envía un mensaje de WhatsApp a través del webhook híbrido de n8n.
     *
     * @return array{ok: bool, status_code: int|null, error: string|null, response_body: string}
     */
    public function send(string $phone, string $text, bool $esCritico = false): array
    {
        $destinationPhone = $this->resolveDestinationPhone($phone);

        if ($destinationPhone === '') {
            return [
                'ok' => false,
                'status_code' => null,
                'error' => 'No hay un número destino configurado para WhatsApp.',
                'response_body' => '',
            ];
        }
        $body = wp_json_encode([
            'phone'     => $destinationPhone,
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
                sprintf('QS WhatsApp send failed to %s: %s', $destinationPhone, $error)
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
                sprintf('QS WhatsApp send failed to %s with HTTP %d. Body: %s', $destinationPhone, $statusCode, $responseBody)
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

    public function defaultPhone(): string
    {
        return $this->defaultPhone;
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

    private function resolveDefaultPhone(): string
    {
        if (defined('QS_N8N_WHATSAPP_PHONE') && is_string(QS_N8N_WHATSAPP_PHONE) && trim(QS_N8N_WHATSAPP_PHONE) !== '') {
            return $this->sanitizePhone(QS_N8N_WHATSAPP_PHONE);
        }

        $envValue = getenv('QS_N8N_WHATSAPP_PHONE');

        if (is_string($envValue) && trim($envValue) !== '') {
            return $this->sanitizePhone($envValue);
        }

        $optionValue = get_option(self::PHONE_OPTION_NAME, '');

        if (is_string($optionValue) && trim($optionValue) !== '') {
            return $this->sanitizePhone($optionValue);
        }

        return '';
    }

    private function resolveDestinationPhone(string $phone): string
    {
        $providedPhone = $this->sanitizePhone($phone);

        if ($providedPhone !== '') {
            return $providedPhone;
        }

        return $this->defaultPhone;
    }

    private function sanitizePhone(string $value): string
    {
        $sanitized = preg_replace('/[^0-9+]/', '', trim($value));

        return is_string($sanitized) ? trim($sanitized) : '';
    }
}
