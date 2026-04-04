<?php

declare(strict_types=1);

namespace QS\Core\Logging;

use QS\Core\Config\PluginConfig;

final class Logger
{
    private string $logFile;

    public function __construct(PluginConfig $config)
    {
        $logDirectory = (string) $config->get('paths.logs', 'var/logs');
        $this->logFile = rtrim(QS_CORE_ROOT_DIR . '/' . trim($logDirectory, '/'), '/') . '/qs-core.log';
    }

    public function debug(string $message): void
    {
        $this->log(LogLevel::Debug, $message);
    }

    public function info(string $message): void
    {
        $this->log(LogLevel::Info, $message);
    }

    public function warning(string $message): void
    {
        $this->log(LogLevel::Warning, $message);
    }

    public function error(string $message): void
    {
        $this->log(LogLevel::Error, $message);
    }

    public function log(LogLevel $level, string $message): void
    {
        $entry = sprintf('[%s] [%s] %s', gmdate('c'), strtoupper($level->value), $message);
        error_log($entry);
        @file_put_contents($this->logFile, $entry . PHP_EOL, FILE_APPEND);
    }
}
