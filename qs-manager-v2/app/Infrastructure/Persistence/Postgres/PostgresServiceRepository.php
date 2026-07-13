<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Persistence\Postgres;

use PDO;
use QSManager\Domain\ServicesCatalog\Service;
use QSManager\Domain\ServicesCatalog\ServiceRepository;

final class PostgresServiceRepository implements ServiceRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(Service $service): Service
    {
        $startedTransaction = !$this->connection->inTransaction();

        try {
            if ($startedTransaction) {
                $this->connection->beginTransaction();
            }

            $statement = $this->connection->prepare(
                'insert into qs_services (name, category, duration_minutes, active)
                 values (:name, :category, :duration_minutes, :active)
                 returning id, name, category, duration_minutes, active, sale_price, total_cost, utility,
                           margin_percent, margin_status, source_sheet, source_row'
            );

            $statement->execute([
                'name' => $service->name()->value(),
                'category' => $service->category(),
                'duration_minutes' => $service->duration()?->minutes(),
                'active' => $service->active(),
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
            'select id, name, category, duration_minutes, active, sale_price, total_cost, utility,
                    margin_percent, margin_status, source_sheet, source_row
             from qs_services
             order by name asc'
        );

        $rows = $statement->fetchAll();

        return array_map(fn (array $row): Service => $this->fromRow($row), $rows);
    }

    public function findById(int $id): ?Service
    {
        $statement = $this->connection->prepare(
            'select id, name, category, duration_minutes, active, sale_price, total_cost, utility,
                    margin_percent, margin_status, source_sheet, source_row
             from qs_services
             where id = :id'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->fromRow($row);
    }

    public function exists(int $id): bool
    {
        $statement = $this->connection->prepare('select exists(select 1 from qs_services where id = :id)');
        $statement->execute(['id' => $id]);

        return (bool) $statement->fetchColumn();
    }

    public function update(int $id, array $data): ?Service
    {
        $startedTransaction = !$this->connection->inTransaction();

        try {
            if ($startedTransaction) {
                $this->connection->beginTransaction();
            }

            $statement = $this->connection->prepare(
                'update qs_services
                 set name = :name,
                     category = :category,
                     duration_minutes = :duration_minutes,
                     active = :active,
                     sale_price = :sale_price,
                     total_cost = :total_cost,
                     utility = :utility,
                     margin_percent = :margin_percent,
                     margin_status = :margin_status
                 where id = :id
                 returning id, name, category, duration_minutes, active, sale_price, total_cost, utility,
                           margin_percent, margin_status, source_sheet, source_row'
            );

            $statement->execute([
                'id' => $id,
                'name' => $data['name'],
                'category' => $data['category'],
                'duration_minutes' => $data['duration_minutes'],
                'active' => $data['active'],
                'sale_price' => $data['sale_price'],
                'total_cost' => $data['total_cost'],
                'utility' => $data['utility'],
                'margin_percent' => $data['margin_percent'],
                'margin_status' => $data['margin_status'],
            ]);

            $row = $statement->fetch();

            if ($row === false) {
                if ($startedTransaction && $this->connection->inTransaction()) {
                    $this->connection->rollBack();
                }
                return null;
            }

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

    public function delete(int $id): bool
    {
        $statement = $this->connection->prepare('delete from qs_services where id = :id');
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }

    private function fromRow(array $row): Service
    {
        return Service::fromPersistence(
            (int) $row['id'],
            (string) $row['name'],
            $row['category'] === null ? null : (string) $row['category'],
            $row['duration_minutes'] === null ? null : (int) $row['duration_minutes'],
            (bool) $row['active'],
            $row['sale_price'] === null ? null : (float) $row['sale_price'],
            $row['total_cost'] === null ? null : (float) $row['total_cost'],
            $row['utility'] === null ? null : (float) $row['utility'],
            $row['margin_percent'] === null ? null : (float) $row['margin_percent'],
            $row['margin_status'] === null ? null : (string) $row['margin_status'],
            $row['source_sheet'] === null ? null : (string) $row['source_sheet'],
            $row['source_row'] === null ? null : (int) $row['source_row'],
        );
    }
}
