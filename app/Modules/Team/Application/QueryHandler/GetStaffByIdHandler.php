<?php

declare(strict_types=1);

namespace QS\Modules\Team\Application\QueryHandler;

use QS\Modules\Team\Application\DTO\StaffDTO;
use QS\Modules\Team\Application\Query\GetStaffById;
use QS\Modules\Team\Domain\Repository\StaffRepository;

final class GetStaffByIdHandler
{
    public function __construct(private readonly StaffRepository $staffRepository)
    {
    }

    public function handle(GetStaffById $query): ?StaffDTO
    {
        $staff = $this->staffRepository->findById($query->staffId);

        return $staff === null ? null : new StaffDTO($staff);
    }
}
