<?php

declare(strict_types=1);

namespace QS\Core\Errors;

use QS\Core\Logging\Logger;
use Throwable;

final class ErrorHandler
{
    public function __construct(private readonly Logger $logger)
    {
    }

    public function register(): void
    {
        set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function handleException(Throwable $throwable): void
    {
        $this->logger->error(
            sprintf(
                'Unhandled exception: %s in %s:%d',
                $throwable->getMessage(),
                $throwable->getFile(),
                $throwable->getLine()
            )
        );
    }

    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        $this->logger->error(sprintf('PHP error [%d] %s in %s:%d', $severity, $message, $file, $line));

        return false;
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error === null) {
            return;
        }

        $this->logger->error(
            sprintf(
                'Shutdown error [%d] %s in %s:%d',
                (int) $error['type'],
                (string) $error['message'],
                (string) $error['file'],
                (int) $error['line']
            )
        );
    }
}
