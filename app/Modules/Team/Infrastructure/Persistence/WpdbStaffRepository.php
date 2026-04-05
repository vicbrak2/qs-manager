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
    private readonly string $tableName;

    public function __construct(private readonly \wpdb $wpdb)
    {
        $this->tableName = $this->wpdb->prefix . 'qs_staff';
    }

    public function findAll(?Specialty $specialty = null, bool $activeOnly = true): array
    {
        if ($specialty !== null && $activeOnly) {
            /** @var literal-string $sql */
            $sql = "SELECT * FROM {$this->tableName} WHERE especialidad = %s AND estado = 'activo' ORDER BY nombre ASC, apellido ASC";
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare($sql, $specialty->value)
            );
        } elseif ($specialty !== null) {
            /** @var literal-string $sql */
            $sql = "SELECT * FROM {$this->tableName} WHERE especialidad = %s ORDER BY nombre ASC, apellido ASC";
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare($sql, $specialty->value)
            );
        } elseif ($activeOnly) {
            $rows = $this->wpdb->get_results(
                "SELECT * FROM {$this->tableName} WHERE estado = 'activo' ORDER BY nombre ASC, apellido ASC"
            );
        } else {
            $rows = $this->wpdb->get_results(
                "SELECT * FROM {$this->tableName} ORDER BY nombre ASC, apellido ASC"
            );
        }

        if (! is_array($rows)) {
            return [];
        }

        /** @var list<\stdClass> $rows */
        $rows = array_values(
            array_filter($rows, static fn (mixed $row): bool => $row instanceof \stdClass)
        );

        return array_map([$this, 'hydrate'], $rows);
    }

    public function findById(int $id): ?StaffMember
    {
        /** @var literal-string $sql */
        $sql = "SELECT * FROM {$this->tableName} WHERE id = %d LIMIT 1";
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare($sql, $id)
        );

        return $row instanceof \stdClass ? $this->hydrate($row) : null;
    }

    public function save(StaffMember $staffMember): StaffMember
    {
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
            $inserted = $this->wpdb->insert($this->tableName, $data, $formats);

            if ($inserted === false) {
                throw new RuntimeException('Could not insert staff member.');
            }

            return $this->findById((int) $this->wpdb->insert_id) ?? $staffMember;
        }

        $updated = $this->wpdb->update($this->tableName, $data, ['id' => $id], $formats, ['%d']);

        if ($updated === false) {
            throw new RuntimeException(sprintf('Could not update staff member %d.', $id));
        }

        return $this->findById($id) ?? $staffMember;
    }

    private function hydrate(\stdClass $row): StaffMember
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
