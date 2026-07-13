<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Persistence\Postgres;

use PDO;
use QSManager\Domain\Team\StaffMember;
use QSManager\Domain\Team\StaffRepository;

final class PostgresStaffRepository implements StaffRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(StaffMember $staffMember): StaffMember
    {
        $startedTransaction = !$this->connection->inTransaction();

        try {
            if ($startedTransaction) {
                $this->connection->beginTransaction();
            }

            $statement = $this->connection->prepare(
                'insert into qs_staff (display_name, role, active)
                 values (:display_name, :role, :active)
                 returning id, display_name, role, active'
            );

            $statement->execute([
                'display_name' => $staffMember->displayName()->value(),
                'role' => $staffMember->role()->value(),
                'active' => $staffMember->active(),
            ]);

            $row = $statement->fetch();

            if ($startedTransaction) {
                $this->connection->commit();
            }

            return $this->fromRow($row);
        } catch (\Throwable $exception) {
            if ($startedTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    public function findAll(): array
    {
        $statement = $this->connection->query(
            'select id, display_name, role, active
             from qs_staff
             order by display_name asc'
        );

        $rows = $statement->fetchAll();

        return array_map(fn (array $row): StaffMember => $this->fromRow($row), $rows);
    }

    public function exists(int $id): bool
    {
        $statement = $this->connection->prepare('select exists(select 1 from qs_staff where id = :id)');
        $statement->execute(['id' => $id]);

        return (bool) $statement->fetchColumn();
    }

    private function fromRow(array $row): StaffMember
    {
        return StaffMember::fromPersistence(
            (int) $row['id'],
            (string) $row['display_name'],
            (string) $row['role'],
            (bool) $row['active'],
        );
    }
}
