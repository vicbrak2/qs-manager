<?php

declare(strict_types=1);

namespace QS\Modules\ServicesCatalog\Domain\Entity;

use QS\Modules\ServicesCatalog\Domain\ValueObject\ServiceCategory;
use QS\Modules\ServicesCatalog\Domain\ValueObject\ServiceDuration;
use QS\Modules\ServicesCatalog\Domain\ValueObject\ServicePrice;
use QS\Modules\ServicesCatalog\Domain\ValueObject\StaffRequirement;
use QS\Shared\ValueObjects\ServiceId;

final class Service
{
    public function __construct(
        private readonly ServiceId $id,
        private readonly string $name,
        private readonly ServiceCategory $category,
        private readonly ServiceDuration $duration,
        private readonly ServicePrice $price,
        private readonly int $staffCostClp,
        private readonly StaffRequirement $staffRequired,
        private readonly bool $active,
        private readonly ?string $description
    ) {
    }

    public function id(): ServiceId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function category(): ServiceCategory
    {
        return $this->category;
    }

    public function duration(): ServiceDuration
    {
        return $this->duration;
    }

    public function price(): ServicePrice
    {
        return $this->price;
    }

    public function staffCostClp(): int
    {
        return $this->staffCostClp;
    }

    public function staffRequired(): StaffRequirement
    {
        return $this->staffRequired;
    }

    public function active(): bool
    {
        return $this->active;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function hasStaffCostWarning(): bool
    {
        return $this->staffCostClp > $this->price->amount();
    }
}
