<?php

declare(strict_types=1);

namespace QS\Modules\Agents\Infrastructure\Chatbot;

use InvalidArgumentException;

final class QuickReplyMatcher
{
    public const OPTION_NAME = 'qs_chatbot_quick_replies_json';
    public const THRESHOLD_OPTION_NAME = 'qs_chatbot_quick_reply_threshold';
    public const ENV_RULES_JSON = 'QS_CHATBOT_QUICK_REPLIES_JSON';
    public const ENV_THRESHOLD = 'QS_CHATBOT_QUICK_REPLY_THRESHOLD';
    public const CONST_RULES_JSON = 'QS_CHATBOT_QUICK_REPLIES_JSON';
    public const CONST_THRESHOLD = 'QS_CHATBOT_QUICK_REPLY_THRESHOLD';
    private const DEFAULT_THRESHOLD = 0.80;

    /**
     * @var list<array{id: string, response: string, examples: list<string>, min_score: float|null}>
     */
    private array $rules;

    private float $threshold;

    public function __construct(
        private readonly ?string $configuredRulesJson = null,
        private readonly ?string $configuredThreshold = null
    ) {
        $this->threshold = $this->resolveThreshold();
        $this->rules = array_merge($this->configuredRules(), $this->defaultRules());
    }

    public function match(string $message): ?string
    {
        $normalizedMessage = $this->normalizeText($message);

        if ($normalizedMessage === '') {
            return null;
        }

        $bestScore = 0.0;
        $bestResponse = null;

        foreach ($this->rules as $rule) {
            $score = $this->bestScoreForRule($normalizedMessage, $rule);
            $requiredScore = max($this->threshold, $rule['min_score'] ?? 0.0);

            if ($score < $requiredScore || $score <= $bestScore) {
                continue;
            }

            $bestScore = $score;
            $bestResponse = $rule['response'];
        }

        return $bestResponse;
    }

    public function threshold(): float
    {
        return $this->threshold;
    }

    public static function sampleRulesJson(): string
    {
        $sample = [
            [
                'id' => 'whatsapp',
                'response' => 'Si prefieres atencion directa, escribenos por WhatsApp: https://wa.me/56912345678',
                'examples' => [
                    'whatsapp',
                    'me das el whatsapp',
                    'hablar por whatsapp',
                ],
                'min_score' => 0.85,
            ],
        ];

        return (string) json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function sanitizeRulesJson(string $json): string
    {
        $rules = self::decodeRulesJson($json);

        if ($rules === []) {
            throw new InvalidArgumentException('El JSON de quick replies no contiene reglas validas.');
        }

        $encoded = json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($encoded)) {
            throw new InvalidArgumentException('No se pudo serializar el JSON de quick replies.');
        }

        return $encoded;
    }

    /**
     * @return list<array{id: string, response: string, examples: list<string>, min_score: float|null}>
     */
    private function configuredRules(): array
    {
        $json = $this->resolveRulesJson();

        if ($json === '') {
            return [];
        }

        try {
            return self::decodeRulesJson($json);
        } catch (\Throwable) {
            return [];
        }
    }

    private function resolveThreshold(): float
    {
        $candidates = [
            $this->configuredThreshold,
            defined(self::CONST_THRESHOLD) && is_string(constant(self::CONST_THRESHOLD))
                ? (string) constant(self::CONST_THRESHOLD)
                : null,
            getenv(self::ENV_THRESHOLD) ?: null,
            function_exists('get_option') ? get_option(self::THRESHOLD_OPTION_NAME, '') : null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $candidate = trim($candidate);

            if ($candidate === '' || ! is_numeric($candidate)) {
                continue;
            }

            $value = (float) $candidate;

            if ($value >= 0.50 && $value <= 1.00) {
                return $value;
            }
        }

        return self::DEFAULT_THRESHOLD;
    }

    private function resolveRulesJson(): string
    {
        $candidates = [
            $this->configuredRulesJson,
            defined(self::CONST_RULES_JSON) && is_string(constant(self::CONST_RULES_JSON))
                ? (string) constant(self::CONST_RULES_JSON)
                : null,
            getenv(self::ENV_RULES_JSON) ?: null,
            function_exists('get_option') ? get_option(self::OPTION_NAME, '') : null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $candidate = trim($candidate);

            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @return list<array{id: string, response: string, examples: list<string>, min_score: float|null}>
     */
    private static function decodeRulesJson(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidArgumentException('El JSON de quick replies no es valido: ' . $exception->getMessage(), 0, $exception);
        }

        if (is_array($decoded) && isset($decoded['rules']) && is_array($decoded['rules'])) {
            $decoded = $decoded['rules'];
        }

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('El JSON de quick replies debe ser una lista de reglas o un objeto con la clave rules.');
        }

        $rules = [];

        foreach ($decoded as $index => $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $response = isset($rule['response']) && is_string($rule['response']) ? trim($rule['response']) : '';

            if ($response === '') {
                continue;
            }

            $examples = [];

            if (isset($rule['examples']) && is_array($rule['examples'])) {
                foreach ($rule['examples'] as $example) {
                    if (! is_string($example)) {
                        continue;
                    }

                    $example = trim($example);

                    if ($example !== '') {
                        $examples[] = $example;
                    }
                }
            }

            $examples = array_values(array_unique($examples));

            if ($examples === []) {
                continue;
            }

            $id = isset($rule['id']) && is_string($rule['id']) && trim($rule['id']) !== ''
                ? trim($rule['id'])
                : 'quick_reply_' . $index;

            $rules[] = [
                'id' => $id,
                'response' => $response,
                'examples' => $examples,
                'min_score' => self::normalizeMinScore($rule['min_score'] ?? null),
            ];
        }

        return $rules;
    }

    private static function normalizeMinScore(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $score = (float) $value;

        if ($score < 0.0 || $score > 1.0) {
            return null;
        }

        return $score;
    }

    /**
     * @param array{id: string, response: string, examples: list<string>, min_score: float|null} $rule
     */
    private function bestScoreForRule(string $message, array $rule): float
    {
        $best = 0.0;

        foreach ($rule['examples'] as $example) {
            $score = $this->similarityScore($message, $this->normalizeText($example));

            if ($score > $best) {
                $best = $score;
            }
        }

        return $best;
    }

    private function similarityScore(string $left, string $right): float
    {
        if ($left === '' || $right === '') {
            return 0.0;
        }

        if ($left === $right) {
            return 1.0;
        }

        similar_text($left, $right, $percentage);

        $charScore = max(0.0, min(1.0, $percentage / 100));
        $leftTokens = $this->tokenize($left);
        $rightTokens = $this->tokenize($right);
        $intersection = count(array_intersect($leftTokens, $rightTokens));
        $union = count(array_unique(array_merge($leftTokens, $rightTokens)));
        $jaccard = $union > 0 ? $intersection / $union : 0.0;
        $containment = $this->isContainmentMatch($leftTokens, $rightTokens) ? 0.93 : 0.0;

        return max($charScore, $jaccard, $containment);
    }

    /**
     * @param list<string> $leftTokens
     * @param list<string> $rightTokens
     */
    private function isContainmentMatch(array $leftTokens, array $rightTokens): bool
    {
        if ($leftTokens === [] || $rightTokens === []) {
            return false;
        }

        $short = count($leftTokens) <= count($rightTokens) ? $leftTokens : $rightTokens;
        $long = count($leftTokens) <= count($rightTokens) ? $rightTokens : $leftTokens;

        if (count($short) === 1 && strlen($short[0]) < 5) {
            return false;
        }

        foreach ($short as $token) {
            if (! in_array($token, $long, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $message): array
    {
        $tokens = preg_split('/\s+/u', $message) ?: [];

        return array_values(
            array_filter(
                array_unique(array_map(static fn (string $token): string => trim($token), $tokens)),
                static fn (string $token): bool => $token !== ''
            )
        );
    }

    private function normalizeText(string $message): string
    {
        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower(trim($message))
            : strtolower(trim($message));

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
     * @return list<array{id: string, response: string, examples: list<string>, min_score: float|null}>
     */
    private function defaultRules(): array
    {
        return [
            [
                'id' => 'services',
                'response' => 'Trabajamos principalmente con maquillaje social, peinado, novia civil y novia fiesta. Si quieres, te cuento cual opcion te conviene o te oriento con valores referenciales.',
                'examples' => [
                    'servicios',
                    'que servicios tienen',
                    'que servicios ofrecen',
                    'cuales son los servicios',
                    'lista de servicios',
                    'me dices los servicios',
                ],
                'min_score' => null,
            ],
            [
                'id' => 'prices',
                'response' => 'Los valores dependen del servicio, la comuna, el horario y si es para una o mas personas. Si quieres cotizar rapido, enviame en un solo mensaje servicio, fecha y comuna.',
                'examples' => [
                    'precios',
                    'precio',
                    'valores',
                    'cuanto sale',
                    'cuanto cuesta',
                    'quiero cotizar',
                    'cotizacion',
                    'precio maquillaje',
                ],
                'min_score' => null,
            ],
            [
                'id' => 'reservations',
                'response' => 'Si, te puedo orientar con la reserva. Para avanzar enviame en un solo mensaje servicio, fecha, comuna y horario aproximado, y asi revisamos los siguientes pasos.',
                'examples' => [
                    'reservas',
                    'reserva',
                    'quiero reservar',
                    'puedo reservar una hora',
                    'agendar',
                    'agenda',
                    'disponibilidad',
                    'tienen horas',
                ],
                'min_score' => null,
            ],
            [
                'id' => 'still_here',
                'response' => 'Sigo aqui. Si quieres, puedo ayudarte con servicios, precios o reservas.',
                'examples' => [
                    'que paso',
                    'sigues ahi',
                    'estas ahi',
                    'hola?',
                ],
                'min_score' => 0.90,
            ],
        ];
    }
}
