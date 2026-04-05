<?php

declare(strict_types=1);

namespace QS\Modules\Agents\Infrastructure\Wordpress;

use WP_Error;

final class ChatbotFallbackResponder
{
    private const OPTION_NAME = 'qs_chatbot_fallback_whatsapp_url';

    public function __construct(
        private readonly string $configuredWhatsappUrl = ''
    ) {
    }

    /**
     * @return array{
     *     success: true,
     *     response: string,
     *     fallback: true,
     *     fallback_channel: 'whatsapp',
     *     whatsapp_url: string|null,
     *     fallback_reason: string
     * }
     */
    public function unavailableResponse(?WP_Error $error = null): array
    {
        $whatsappUrl = $this->whatsappUrl();
        $message = 'En este momento el asistente automatico no esta disponible. Escribenos por WhatsApp y te ayudamos directamente.';
        $reason = $error !== null ? (string) $error->get_error_code() : 'service_unavailable';

        if ($whatsappUrl !== '') {
            $message .= ' ' . $whatsappUrl;
        }

        return [
            'success' => true,
            'response' => $message,
            'fallback' => true,
            'fallback_channel' => 'whatsapp',
            'whatsapp_url' => $whatsappUrl !== '' ? $whatsappUrl : null,
            'fallback_reason' => $reason,
        ];
    }

    public function whatsappUrl(): string
    {
        return $this->resolveWhatsappUrl();
    }

    private function resolveWhatsappUrl(): string
    {
        $configuredValue = trim($this->configuredWhatsappUrl);

        if ($configuredValue !== '') {
            return $configuredValue;
        }

        if (
            defined('QS_CHATBOT_FALLBACK_WHATSAPP_URL')
            && is_string(QS_CHATBOT_FALLBACK_WHATSAPP_URL)
            && trim(QS_CHATBOT_FALLBACK_WHATSAPP_URL) !== ''
        ) {
            return trim(QS_CHATBOT_FALLBACK_WHATSAPP_URL);
        }

        $envValue = getenv('QS_CHATBOT_FALLBACK_WHATSAPP_URL');

        if (is_string($envValue) && trim($envValue) !== '') {
            return trim($envValue);
        }

        if (! function_exists('get_option')) {
            return '';
        }

        $optionValue = get_option(self::OPTION_NAME, '');

        return is_string($optionValue) ? trim($optionValue) : '';
    }
}
