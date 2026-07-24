<?php

declare(strict_types=1);

namespace QSManager\Tests\Support;

/**
 * Stream wrapper falso para "mock-gas://" -- simula respuestas del Web App
 * de Google Apps Script sin pegarle a la red real. Extraido de
 * HttpRoutesTest.php (Fase 5 del plan de migracion).
 */
final class MockGasStreamWrapper
{
    public $context;
    public static array $responses = [];
    public static array $requests = [];

    public static function reset(): void
    {
        self::$responses = [];
        self::$requests = [];
    }

    public static function addResponse(string $body): void
    {
        self::$responses[] = $body;
    }

    public static function getRequests(): array
    {
        return self::$requests;
    }

    private string $data = '';
    private int $position = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        if ($this->context) {
            $opts = stream_context_get_options($this->context);
            self::$requests[] = $opts;
        }

        $response = array_shift(self::$responses) ?? json_encode(['ok' => true, 'status' => 'success']);
        if ($response === 'TIMEOUT_ERROR') {
            return false;
        }
        $this->data = $response;
        $this->position = 0;
        return true;
    }

    public function stream_read(int $count): string
    {
        $ret = substr($this->data, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen($this->data);
    }

    public function stream_stat(): array
    {
        return [];
    }
}
