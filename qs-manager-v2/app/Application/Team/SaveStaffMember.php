<?php

declare(strict_types=1);

namespace QSManager\Application\Team;

use QSManager\Domain\Team\StaffMember;
use QSManager\Domain\Team\StaffRepository;

/**
 * Alta y edicion de integrantes del equipo desde el mantenedor. El sync de
 * planillas tambien crea profesionales, pero solo con el nombre: aca se
 * completan telefono, comuna base y alias.
 */
final class SaveStaffMember
{
    public function __construct(private readonly StaffRepository $staff)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function execute(array $data, ?int $id = null): ?StaffMember
    {
        $staffMember = StaffMember::create(
            $data['display_name'],
            $data['role'],
            $data['phone'],
            $data['comuna_base'],
            $data['aliases'],
            $data['active'],
        );

        return $id === null
            ? $this->staff->save($staffMember)
            : $this->staff->update($id, $staffMember);
    }
}
