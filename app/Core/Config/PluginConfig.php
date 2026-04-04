<?php

declare(strict_types=1);

namespace QS\Core\Config;

final class PluginConfig
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->config;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->config;

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function option(string $key): string
    {
        return (string) $this->get(sprintf('options.%s', $key), '');
    }
}
