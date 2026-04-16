<?php

declare(strict_types=1);

namespace QS\Modules\Agents\Infrastructure\Chatbot;

final class ChatbotProfile
{
    public const OPTION_NAME = 'qs_chatbot_profile_json';
    public const ENV_PROFILE_JSON = 'QS_CHATBOT_PROFILE_JSON';
    public const CONST_PROFILE_JSON = 'QS_CHATBOT_PROFILE_JSON';

    /**
     * @param list<string> $aliases
     * @param list<string> $services
     * @param list<string> $bookingFields
     * @param list<string> $restrictions
     */
    public function __construct(
        private readonly string $siteId,
        private readonly string $brandName,
        private readonly string $locale,
        private readonly string $tone,
        private readonly string $whatsappUrl,
        private readonly array $aliases,
        private readonly array $services,
        private readonly array $bookingFields,
        private readonly array $restrictions,
        private readonly string $vectorCollection,
        private readonly int $retrievalTopK
    ) {
    }

    public static function resolveDefault(): self
    {
        $configured = self::configuredProfile();

        return new self(
            siteId: self::stringValue($configured, 'site_id', 'qamiluna'),
            brandName: self::stringValue($configured, 'brand_name', 'Qamiluna Studio'),
            locale: self::stringValue($configured, 'locale', 'es-CL'),
            tone: self::stringValue($configured, 'tone', 'cercano, claro y resolutivo'),
            whatsappUrl: self::stringValue($configured, 'whatsapp_url', self::fallbackWhatsappUrl()),
            aliases: self::listValue($configured, 'aliases', ['Qamiluna', 'Qami Luna', 'Cami Luna']),
            services: self::listValue($configured, 'services', [
                'Maquillaje social',
                'Peinado',
                'Combo social maquillaje + peinado',
                'Novia civil',
                'Novia fiesta',
            ]),
            bookingFields: self::listValue($configured, 'booking_fields', [
                'servicio',
                'comuna',
                'direccion',
                'telefono',
                'fecha',
            ]),
            restrictions: self::listValue($configured, 'restrictions', [
                'No inventar precios, promociones ni disponibilidad.',
                'No entregar valores numericos ni fechas de talleres.',
                'Enviar cotizaciones y talleres a WhatsApp.',
            ]),
            vectorCollection: self::stringValue($configured, 'vector_collection', 'wordpress_context'),
            retrievalTopK: self::intValue($configured, 'retrieval_top_k', 5, 1, 10)
        );
    }

    public function siteId(): string
    {
        return $this->siteId;
    }

    public function brandName(): string
    {
        return $this->brandName;
    }

    public function whatsappUrl(): string
    {
        return $this->whatsappUrl;
    }

    /**
     * @return list<string>
     */
    public function aliases(): array
    {
        return $this->aliases;
    }

    /**
     * @return list<string>
     */
    public function services(): array
    {
        return $this->services;
    }

    public function vectorCollection(): string
    {
        return $this->vectorCollection;
    }

    public function retrievalTopK(): int
    {
        return $this->retrievalTopK;
    }

    /**
     * @return array{
     *     site_id: string,
     *     brand_name: string,
     *     locale: string,
     *     tone: string,
     *     whatsapp_url: string,
     *     aliases: list<string>,
     *     services: list<string>,
     *     booking_fields: list<string>,
     *     restrictions: list<string>,
     *     vector_collection: string,
     *     retrieval_top_k: int
     * }
     */
    public function toArray(): array
    {
        return [
            'site_id' => $this->siteId,
            'brand_name' => $this->brandName,
            'locale' => $this->locale,
            'tone' => $this->tone,
            'whatsapp_url' => $this->whatsappUrl,
            'aliases' => $this->aliases,
            'services' => $this->services,
            'booking_fields' => $this->bookingFields,
            'restrictions' => $this->restrictions,
            'vector_collection' => $this->vectorCollection,
            'retrieval_top_k' => $this->retrievalTopK,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function configuredProfile(): array
    {
        $json = '';

        if (defined(self::CONST_PROFILE_JSON) && is_string(constant(self::CONST_PROFILE_JSON))) {
            $json = trim((string) constant(self::CONST_PROFILE_JSON));
        }

        if ($json === '') {
            $envValue = getenv(self::ENV_PROFILE_JSON);
            $json = is_string($envValue) ? trim($envValue) : '';
        }

        if ($json === '' && function_exists('get_option')) {
            $optionValue = get_option(self::OPTION_NAME, '');
            $json = is_string($optionValue) ? trim($optionValue) : '';
        }

        if ($json === '') {
            return self::configuredProfileFile();
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function configuredProfileFile(): array
    {
        $file = dirname(__DIR__, 5) . '/config/chatbots/profiles.json';

        if (! is_readable($file)) {
            return [];
        }

        try {
            $decoded = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $siteId = self::configuredSiteId();
        $profiles = isset($decoded['profiles']) && is_array($decoded['profiles']) ? $decoded['profiles'] : [];

        foreach ($profiles as $profile) {
            if (! is_array($profile)) {
                continue;
            }

            if (($profile['site_id'] ?? null) === $siteId) {
                return $profile;
            }
        }

        return isset($profiles[0]) && is_array($profiles[0]) ? $profiles[0] : [];
    }

    private static function configuredSiteId(): string
    {
        if (defined('QS_CHATBOT_SITE_ID') && is_string(QS_CHATBOT_SITE_ID) && trim(QS_CHATBOT_SITE_ID) !== '') {
            return trim(QS_CHATBOT_SITE_ID);
        }

        $envValue = getenv('QS_CHATBOT_SITE_ID');

        if (is_string($envValue) && trim($envValue) !== '') {
            return trim($envValue);
        }

        if (function_exists('get_option')) {
            $optionValue = get_option('qs_chatbot_site_id', '');

            if (is_string($optionValue) && trim($optionValue) !== '') {
                return trim($optionValue);
            }
        }

        return 'qamiluna';
    }

    /**
     * @param array<string, mixed> $source
     */
    private static function stringValue(array $source, string $key, string $default): string
    {
        $value = $source[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    /**
     * @param array<string, mixed> $source
     * @param list<string> $default
     * @return list<string>
     */
    private static function listValue(array $source, string $key, array $default): array
    {
        $value = $source[$key] ?? null;

        if (! is_array($value)) {
            return $default;
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_string($item) || trim($item) === '') {
                continue;
            }

            $items[] = trim($item);
        }

        return $items !== [] ? array_values(array_unique($items)) : $default;
    }

    /**
     * @param array<string, mixed> $source
     */
    private static function intValue(array $source, string $key, int $default, int $min, int $max): int
    {
        $value = $source[$key] ?? null;

        if (! is_numeric($value)) {
            return $default;
        }

        $normalized = (int) $value;

        if ($normalized < $min || $normalized > $max) {
            return $default;
        }

        return $normalized;
    }

    private static function fallbackWhatsappUrl(): string
    {
        $candidates = [
            'QS_CHATBOT_WHATSAPP_URL',
            'QS_CHATBOT_FALLBACK_WHATSAPP_URL',
        ];

        foreach ($candidates as $candidate) {
            if (defined($candidate) && is_string(constant($candidate)) && trim((string) constant($candidate)) !== '') {
                return trim((string) constant($candidate));
            }

            $envValue = getenv($candidate);

            if (is_string($envValue) && trim($envValue) !== '') {
                return trim($envValue);
            }
        }

        if (function_exists('get_option')) {
            foreach (['qs_chatbot_whatsapp_url', 'qs_chatbot_fallback_whatsapp_url'] as $optionName) {
                $optionValue = get_option($optionName, '');

                if (is_string($optionValue) && trim($optionValue) !== '') {
                    return trim($optionValue);
                }
            }
        }

        return 'https://wa.me/56912345678';
    }
}
