<?php

declare(strict_types=1);

namespace QS\Modules\Team\Infrastructure\Persistence;

use DateTimeImmutable;
use QS\Modules\Team\Domain\Entity\StaffMember;
use QS\Modules\Team\Domain\Repository\StaffRepository;
use QS\Modules\Team\Domain\ValueObject\Specialty;
use QS\Modules\Team\Domain\ValueObject\StaffId;
use RuntimeException;

final class WpdbStaffRepository implements StaffRepository
{
    private string $tableName;

    public function __construct()
    {
        global $wpdb;

        $this->tableName = isset($wpdb) ? $wpdb->prefix . 'qs_staff' : 'wp_qs_staff';
    }

    public function findAll(?Specialty $specialty = null, bool $activeOnly = true): array
    {
        global $wpdb;

        if (! isset($wpdb)) {
            return [];
        }

        $sql = "SELECT * FROM {$this->tableName} WHERE 1=1";
        $arguments = [];

        if ($specialty !== null) {
            $sql .= ' AND especialidad = %s';
            $arguments[] = $specialty->value;
        }

        if ($activeOnly) {
            $sql .= " AND estado = 'activo'";
        }

        $sql .= ' ORDER BY nombre ASC, apellido ASC';

        $prepared = count($arguments) > 0 ? $wpdb->prepare($sql, ...$arguments) : $sql;
        $rows = $wpdb->get_results($prepared);

        if (! is_array($rows)) {
            return [];
        }

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findById(int $id): ?StaffMember
    {
        global $wpdb;

        if (! isset($wpdb)) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->tableName} WHERE id = %d LIMIT 1", $id)
        );

        return $row instanceof \stdClass ? $this->hydrate($row) : null;
    }

    public function save(StaffMember $staffMember): StaffMember
    {
        global $wpdb;

        if (! isset($wpdb)) {
            throw new RuntimeException('wpdb is not available.');
        }

        $data = [
            'nombre' => $staffMember->nombre(),
            'apellido' => $staffMember->apellido(),
            'especialidad' => $staffMember->specialty()->value,
            'costo_hora_clp' => $staffMember->costoHoraClp(),
            'contacto_whatsapp' => $staffMember->contactoWhatsapp(),
            'estado' => $staffMember->estado(),
            'created_at' => $staffMember->createdAt()->format('Y-m-d H:i:s'),
            'updated_at' => $staffMember->updatedAt()->format('Y-m-d H:i:s'),
        ];

        $formats = ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s'];
        $id = $staffMember->id()->value();
        $exists = $this->findById($id);

        if ($exists === null) {
            $inserted = $wpdb->insert($this->tableName, $data, $formats);

            if ($inserted === false) {
                throw new RuntimeException('Could not insert staff member.');
            }

            return $this->findById((int) $wpdb->insert_id) ?? $staffMember;
        }

        $updated = $wpdb->update($this->tableName, $data, ['id' => $id], $formats, ['%d']);

        if ($updated === false) {
            throw new RuntimeException(sprintf('Could not update staff member %d.', $id));
        }

        return $this->findById($id) ?? $staffMember;
    }

    private function hydrate(object $row): StaffMember
    {
        $specialty = Specialty::fromNullable((string) $row->especialidad) ?? Specialty::Mua;

        return new StaffMember(
            new StaffId((int) $row->id),
            (string) $row->nombre,
            (string) $row->apellido,
            $specialty,
            (int) $row->costo_hora_clp,
            $row->contacto_whatsapp !== null ? (string) $row->contacto_whatsapp : null,
            (string) $row->estado,
            new DateTimeImmutable((string) $row->created_at),
            new DateTimeImmutable((string) $row->updated_at)
        );
    }
}
