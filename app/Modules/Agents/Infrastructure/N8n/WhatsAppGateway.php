<?php

declare(strict_types=1);

namespace QS\Modules\Agents\Infrastructure\N8n;

use QS\Core\Logging\Logger;

final class WhatsAppGateway
{
    private const OPTION_NAME = 'qs_n8n_whatsapp_url';
    private const PHONE_OPTION_NAME = 'qs_n8n_whatsapp_phone';
    private const ACTIONS_ENABLED_OPTION_NAME = 'qs_n8n_whatsapp_actions_enabled';
    private const ALLOWED_PHONES_OPTION_NAME = 'qs_n8n_whatsapp_allowed_phones';
    private const INSTANCE_OPTION_NAME = 'qs_n8n_whatsapp_instance';

    private string $webhookUrl;
    private string $defaultPhone;
    private string $instanceName;
    private bool $actionsEnabled;
    /** @var list<string> */
    private array $allowedPhones;

    public function __construct(
        private readonly Logger $logger
    ) {
        $this->webhookUrl = $this->resolveWebhookUrl();
        $this->defaultPhone = $this->resolveDefaultPhone();
        $this->instanceName = $this->resolveInstanceName();
        $this->actionsEnabled = $this->resolveActionsEnabled();
        $this->allowedPhones = $this->resolveAllowedPhones();
    }

    /**
     * Envía un mensaje de WhatsApp a través del webhook híbrido de n8n.
     *
     * @return array{ok: bool, status_code: int|null, error: string|null, response_body: string}
     */
    public function send(string $phone, string $text, bool $esCritico = false): array
    {
        if (! $this->actionsEnabled) {
            return [
                'ok' => false,
                'status_code' => null,
                'error' => 'Las acciones de WhatsApp estan desactivadas.',
                'response_body' => '',
            ];
        }

        $destinationPhone = $this->resolveDestinationPhone($phone);

        if ($destinationPhone === '') {
            return [
                'ok' => false,
                'status_code' => null,
                'error' => 'No hay un número destino configurado para WhatsApp.',
                'response_body' => '',
            ];
        }

        if (! $this->isAllowedPhone($destinationPhone)) {
            $this->logger->warning(
                sprintf('QS WhatsApp send blocked to non-allowed destination %s.', $destinationPhone)
            );

            return [
                'ok' => false,
                'status_code' => null,
                'error' => 'El numero destino no esta permitido para acciones de WhatsApp.',
                'response_body' => '',
            ];
        }

        $body = wp_json_encode([
            'phone'     => $destinationPhone,
            'text'      => $text,
            'instance'  => $this->instanceName,
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

    public function actionsEnabled(): bool
    {
        return $this->actionsEnabled;
    }
 
    public function instanceName(): string
    {
        return $this->instanceName;
    }

    /**
     * @return list<string>
     */
    public function allowedPhones(): array
    {
        return $this->allowedPhones;
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
 
    private function resolveInstanceName(): string
    {
        if (defined('EVOLUTION_INSTANCE_NAME') && is_string(EVOLUTION_INSTANCE_NAME) && trim(EVOLUTION_INSTANCE_NAME) !== '') {
            return trim(EVOLUTION_INSTANCE_NAME);
        }
 
        $envValue = getenv('EVOLUTION_INSTANCE_NAME');
 
        if (is_string($envValue) && trim($envValue) !== '') {
            return trim($envValue);
        }
 
        $optionValue = get_option(self::INSTANCE_OPTION_NAME, '');
 
        if (is_string($optionValue) && trim($optionValue) !== '') {
            return trim($optionValue);
        }
 
        return 'qamiluna-test';
    }

    private function resolveActionsEnabled(): bool
    {
        if (defined('QS_N8N_WHATSAPP_ACTIONS_ENABLED') && is_scalar(QS_N8N_WHATSAPP_ACTIONS_ENABLED)) {
            return $this->stringToBoolean((string) QS_N8N_WHATSAPP_ACTIONS_ENABLED);
        }

        $envValue = getenv('QS_N8N_WHATSAPP_ACTIONS_ENABLED');

        if (is_string($envValue) && trim($envValue) !== '') {
            return $this->stringToBoolean($envValue);
        }

        $optionValue = get_option(self::ACTIONS_ENABLED_OPTION_NAME, '0');

        if (is_scalar($optionValue)) {
            return $this->stringToBoolean((string) $optionValue);
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function resolveAllowedPhones(): array
    {
        if (defined('QS_N8N_WHATSAPP_ALLOWED_PHONES') && is_scalar(QS_N8N_WHATSAPP_ALLOWED_PHONES)) {
            return $this->parsePhoneList((string) QS_N8N_WHATSAPP_ALLOWED_PHONES);
        }

        $envValue = getenv('QS_N8N_WHATSAPP_ALLOWED_PHONES');

        if (is_string($envValue) && trim($envValue) !== '') {
            return $this->parsePhoneList($envValue);
        }

        $optionValue = get_option(self::ALLOWED_PHONES_OPTION_NAME, '');

        if (is_string($optionValue) && trim($optionValue) !== '') {
            return $this->parsePhoneList($optionValue);
        }

        return [];
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

    private function isAllowedPhone(string $phone): bool
    {
        if ($this->allowedPhones === []) {
            return false;
        }

        $normalizedPhone = $this->normalizePhoneForMatch($phone);

        foreach ($this->allowedPhones as $allowedPhone) {
            if ($this->normalizePhoneForMatch($allowedPhone) === $normalizedPhone) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function parsePhoneList(string $value): array
    {
        $items = preg_split('/[\s,;]+/', trim($value));

        if (! is_array($items)) {
            return [];
        }

        $phones = [];

        foreach ($items as $item) {
            $phone = $this->sanitizePhone($item);

            if ($phone === '') {
                continue;
            }

            $phones[$this->normalizePhoneForMatch($phone)] = $phone;
        }

        return array_values($phones);
    }

    private function normalizePhoneForMatch(string $phone): string
    {
        $normalized = preg_replace('/\D+/', '', $phone);

        return is_string($normalized) ? $normalized : '';
    }

    private function stringToBoolean(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}
