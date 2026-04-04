<?php

declare(strict_types=1);

namespace QS\Modules\Bitacora\Domain\Entity;

use DateTimeImmutable;
use InvalidArgumentException;

final class TravelNote
{
    private string $message;

    public function __construct(
        string $message,
        private readonly ?int $authorUserId,
        private readonly DateTimeImmutable $createdAt
    ) {
        $message = trim($message);

        if ($message === '') {
            throw new InvalidArgumentException('Travel note message is required.');
        }

        $this->message = $message;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function authorUserId(): ?int
    {
        return $this->authorUserId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'author_user_id' => $this->authorUserId,
            'created_at' => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
