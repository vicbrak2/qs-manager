<?php

declare(strict_types=1);

namespace QS\Modules\Setup\Infrastructure\Wordpress;

use QS\Core\Logging\Logger;

final class AgentStatusChecker
{
    private const CHATBOT_URL_OPTION = 'qs_n8n_chatbot_url';

    public function __construct(
        private readonly Logger $logger
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function check(): array
    {
        $n8n = $this->probe('n8n', $this->resolveN8nStatusUrl());
        $qdrant = $this->probe('qdrant', $this->resolveQdrantStatusUrl());

        return [
            'overall_ok' => $n8n['ok'] && $qdrant['ok'],
            'checked_at' => gmdate('c'),
            'services' => [
                'n8n' => $n8n,
                'qdrant' => $qdrant,
            ],
        ];
    }

    private function resolveN8nStatusUrl(): string
    {
        $explicit = $this->env('QS_N8N_STATUS_URL');

        if ($explicit !== '') {
            return $explicit;
        }

        $chatbotUrl = $this->resolveChatbotUrl();

        return $this->replacePath($chatbotUrl, '/healthz');
    }

    private function resolveQdrantStatusUrl(): string
    {
        $explicit = $this->env('QDRANT_STATUS_URL');

        if ($explicit !== '') {
            return $explicit;
        }

        return $this->replacePath($this->env('QDRANT_URL', 'http://localhost:6333'), '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function probe(string $service, string $url): array
    {
        $startedAt = microtime(true);
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        if (is_wp_error($response)) {
            $message = $response->get_error_message();
            $this->logger->warning(sprintf('QS %s status check failed: %s', $service, $message));

            return [
                'service' => $service,
                'url' => $url,
                'ok' => false,
                'status_code' => null,
                'latency_ms' => $latencyMs,
                'error' => $message,
            ];
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $ok = $statusCode >= 200 && $statusCode < 400;

        if (! $ok) {
            $this->logger->warning(sprintf('QS %s status check returned HTTP %d.', $service, $statusCode));
        }

        return [
            'service' => $service,
            'url' => $url,
            'ok' => $ok,
            'status_code' => $statusCode,
            'latency_ms' => $latencyMs,
            'error' => $ok ? null : 'HTTP ' . $statusCode,
        ];
    }

    private function env(string $name, string $default = ''): string
    {
        $value = getenv($name);

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return $default;
    }

    private function resolveChatbotUrl(): string
    {
        if (defined('QS_N8N_CHATBOT_URL') && is_string(QS_N8N_CHATBOT_URL) && QS_N8N_CHATBOT_URL !== '') {
            return QS_N8N_CHATBOT_URL;
        }

        $envValue = $this->env('QS_N8N_CHATBOT_URL');

        if ($envValue !== '') {
            return $envValue;
        }

        $optionValue = get_option(self::CHATBOT_URL_OPTION, '');

        if (is_string($optionValue) && trim($optionValue) !== '') {
            return trim($optionValue);
        }

        return 'http://localhost:5678/webhook/wp-chatbot-rag';
    }

    private function replacePath(string $url, string $path): string
    {
        $parts = wp_parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $rebuilt = $parts['scheme'] . '://' . $parts['host'];

        if (isset($parts['port'])) {
            $rebuilt .= ':' . $parts['port'];
        }

        return $rebuilt . $path;
    }
}
