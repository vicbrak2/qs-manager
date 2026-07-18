<?php

declare(strict_types=1);

namespace QSManager\Domain\ServicesCatalog;

final class Service
{
    private function __construct(
        private readonly ?ServiceId $id,
        private readonly ServiceName $name,
        private readonly ?string $category,
        private readonly ?ServiceDuration $duration,
        private readonly int $quantity,
        private readonly bool $active,
        private readonly ?float $salePrice,
        private readonly ?float $totalCost,
        private readonly ?float $utility,
        private readonly ?float $marginPercent,
        private readonly ?string $marginStatus,
        private readonly ?string $sourceSheet,
        private readonly ?int $sourceRow,
    ) {
    }

    public static function create(
        string $name,
        ?string $category,
        ?int $durationMinutes,
        int $quantity = 1,
    ): self {
        return new self(
            null,
            ServiceName::fromString($name),
            self::normalizeCategory($category),
            $durationMinutes === null ? null : ServiceDuration::fromMinutes($durationMinutes),
            $quantity,
            true,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        );
    }

    public static function fromPersistence(
        int $id,
        string $name,
        ?string $category,
        ?int $durationMinutes,
        int $quantity,
        bool $active,
        ?float $salePrice = null,
        ?float $totalCost = null,
        ?float $utility = null,
        ?float $marginPercent = null,
        ?string $marginStatus = null,
        ?string $sourceSheet = null,
        ?int $sourceRow = null,
    ): self {
        return new self(
            ServiceId::fromInt($id),
            ServiceName::fromString($name),
            self::normalizeCategory($category),
            $durationMinutes === null ? null : ServiceDuration::fromMinutes($durationMinutes),
            $quantity,
            $active,
            $salePrice,
            $totalCost,
            $utility,
            $marginPercent,
            self::normalizeCategory($marginStatus),
            self::normalizeCategory($sourceSheet),
            $sourceRow,
        );
    }

    public function id(): ?ServiceId
    {
        return $this->id;
    }

    public function name(): ServiceName
    {
        return $this->name;
    }

    public function category(): ?string
    {
        return $this->category;
    }

    public function duration(): ?ServiceDuration
    {
        return $this->duration;
    }

    public function active(): bool
    {
        return $this->active;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id?->value(),
            'name' => $this->name->value(),
            'category' => $this->category,
            'duration_minutes' => $this->duration?->minutes(),
            'quantity' => $this->quantity,
            'active' => $this->active,
            'sale_price' => $this->salePrice,
            'total_cost' => $this->totalCost,
            'utility' => $this->utility,
            'margin_percent' => $this->marginPercent,
            'margin_status' => $this->marginStatus,
            'source_sheet' => $this->sourceSheet,
            'source_row' => $this->sourceRow,
        ];
    }

    private static function normalizeCategory(?string $category): ?string
    {
        if ($category === null) {
            return null;
        }

        $category = trim($category);

        return $category === '' ? null : $category;
    }
}
