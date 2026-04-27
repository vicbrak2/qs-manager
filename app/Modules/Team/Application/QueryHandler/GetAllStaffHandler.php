<?php

declare(strict_types=1);

namespace QS\Modules\Team\Application\QueryHandler;

use QS\Modules\Team\Application\DTO\StaffDTO;
use QS\Modules\Team\Application\Query\GetAllStaff;
use QS\Modules\Team\Domain\Repository\StaffRepository;
use QS\Shared\Bus\QueryHandlerInterface;

final class GetAllStaffHandler implements QueryHandlerInterface
{
    public function __construct(private readonly StaffRepository $staffRepository)
    {
    }

    /**
     * @return array<int, StaffDTO>
     */
    public function handle(object $query): array
    {
        assert($query instanceof GetAllStaff);

        $staffMembers = $this->staffRepository->findAll($query->specialty, $query->activeOnly);

        return array_map(
            static fn ($staffMember): StaffDTO => new StaffDTO($staffMember),
            $staffMembers
        );
    }
}
