<?php

declare(strict_types=1);

namespace QSManager\Application\Booking;

final class GasSyncResult
{
    public function __construct(
        private readonly bool $configured,
        private readonly bool $success,
        private readonly string $status,
        private readonly ?string $message,
        private readonly array $payload = [],
        private readonly ?array $response = null,
    ) {
    }

    public static function skipped(array $payload, string $message): self
    {
        return new self(false, false, 'skipped', $message, $payload);
    }

    public static function synced(array $payload, ?array $response = null): self
    {
        return new self(true, true, 'synced', null, $payload, $response);
    }

    public static function failed(array $payload, string $message, ?array $response = null): self
    {
        return new self(true, false, 'failed', $message, $payload, $response);
    }

    public function success(): bool
    {
        return $this->success;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function message(): ?string
    {
        return $this->message;
    }

    public function toArray(): array
    {
        return [
            'configured' => $this->configured,
            'success' => $this->success,
            'status' => $this->status,
            'message' => $this->message,
            'payload' => $this->payload,
            'response' => $this->response,
        ];
    }
}
