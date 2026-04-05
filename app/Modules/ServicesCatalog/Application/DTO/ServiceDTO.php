<?php

declare(strict_types=1);

namespace QS\Modules\ServicesCatalog\Application\DTO;

use QS\Modules\ServicesCatalog\Domain\Entity\Service;

final class ServiceDTO
{
    public function __construct(private readonly Service $service)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->service->id()->value(),
            'name' => $this->service->name(),
            'category' => $this->service->category()->value,
            'duration_min' => $this->service->duration()->durationMin(),
            'buffer_min' => $this->service->duration()->bufferMin(),
            'total_min' => $this->service->duration()->totalMin(),
            'price_clp' => $this->service->price()->amount(),
            'staff_cost_clp' => $this->service->staffCostClp(),
            'staff_required' => $this->service->staffRequired()->value,
            'active' => $this->service->active(),
            'description' => $this->service->description(),
            'has_staff_cost_warning' => $this->service->hasStaffCostWarning(),
        ];
    }
}
