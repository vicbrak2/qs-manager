<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Persistence\Postgres;

use PDO;
use QSManager\Domain\Team\StaffMember;
use QSManager\Domain\Team\StaffRepository;

final class PostgresStaffRepository implements StaffRepository
{
    private const COLUMNS = 'id, display_name, role, active, phone, comuna_base, aliases';

    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(StaffMember $staffMember): StaffMember
    {
        $statement = $this->connection->prepare(
            'insert into qs_staff (display_name, role, active, phone, comuna_base, aliases)
             values (:display_name, :role, :active, :phone, :comuna_base, :aliases)
             returning ' . self::COLUMNS
        );

        $statement->execute($this->params($staffMember));

        return $this->fromRow($statement->fetch(PDO::FETCH_ASSOC));
    }

    public function findAll(): array
    {
        $rows = $this->connection
            ->query('select ' . self::COLUMNS . ' from qs_staff order by active desc, display_name asc')
            ->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn (array $row): StaffMember => $this->fromRow($row), $rows);
    }

    public function findById(int $id): ?StaffMember
    {
        $statement = $this->connection->prepare('select ' . self::COLUMNS . ' from qs_staff where id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->fromRow($row);
    }

    public function update(int $id, StaffMember $staffMember): ?StaffMember
    {
        $statement = $this->connection->prepare(
            'update qs_staff set
                display_name = :display_name,
                role = :role,
                active = :active,
                phone = :phone,
                comuna_base = :comuna_base,
                aliases = :aliases
             where id = :id
             returning ' . self::COLUMNS
        );

        $statement->execute($this->params($staffMember) + ['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->fromRow($row);
    }

    public function delete(int $id): bool
    {
        $statement = $this->connection->prepare('delete from qs_staff where id = :id');
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }

    public function pendingServices(int $staffId): array
    {
        $statement = $this->connection->prepare(
            "select b.scheduled_for, b.customer_name
             from qs_bookings b
             where (b.staff_id = :id or b.estilista_id = :id)
               and b.scheduled_for is not null
               and b.scheduled_for >= now()
               and b.status not in ('cancelled', 'completed')
             order by b.scheduled_for asc"
        );
        $statement->execute(['id' => $staffId]);

        $pending = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $pending[] = [
                'scheduled_for' => (string) $row['scheduled_for'],
                'customer_name' => $row['customer_name'] === null ? null : (string) $row['customer_name'],
            ];
        }

        return $pending;
    }

    public function exists(int $id): bool
    {
        $statement = $this->connection->prepare('select exists(select 1 from qs_staff where id = :id)');
        $statement->execute(['id' => $id]);

        return (bool) $statement->fetchColumn();
    }

    /**
     * @return array<string, mixed>
     */
    private function params(StaffMember $staffMember): array
    {
        return [
            'display_name' => $staffMember->displayName()->value(),
            'role' => $staffMember->role()->value(),
            'active' => $this->dbBool($staffMember->active()),
            'phone' => $staffMember->phone(),
            'comuna_base' => $staffMember->comunaBase(),
            // Los alias se guardan separados por coma: es una lista corta de
            // variantes de escritura, no una entidad aparte.
            'aliases' => $staffMember->aliases() === [] ? null : implode(', ', $staffMember->aliases()),
        ];
    }

    private function dbBool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function fromRow(array $row): StaffMember
    {
        $aliases = [];
        if (($row['aliases'] ?? null) !== null && trim((string) $row['aliases']) !== '') {
            foreach (explode(',', (string) $row['aliases']) as $alias) {
                $alias = trim($alias);
                if ($alias !== '') {
                    $aliases[] = $alias;
                }
            }
        }

        return StaffMember::fromPersistence(
            (int) $row['id'],
            (string) $row['display_name'],
            (string) $row['role'],
            (bool) $row['active'],
            $row['phone'] === null ? null : (string) $row['phone'],
            $row['comuna_base'] === null ? null : (string) $row['comuna_base'],
            $aliases,
        );
    }
}
