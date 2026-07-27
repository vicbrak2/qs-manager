<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Persistence\Postgres;

use DateTimeImmutable;
use PDO;
use QSManager\Domain\Booking\Booking;
use QSManager\Domain\Booking\BookingRepository;

final class PostgresBookingRepository implements BookingRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(Booking $booking): Booking
    {
        $startedTransaction = !$this->connection->inTransaction();

        try {
            if ($startedTransaction) {
                $this->connection->beginTransaction();
            }

            $statement = $this->connection->prepare(
                'insert into qs_bookings (
                    service_id, staff_id, customer_name, customer_phone, scheduled_for, status,
                    address, comuna, service_value, transfer_value, deposit_amount, total_service,
                    balance_due, payment_status, service_status, contract_id, milestone, cash_group
                 )
                 values (
                    :service_id, :staff_id, :customer_name, :customer_phone, :scheduled_for, :status,
                    :address, :comuna, :service_value, :transfer_value, :deposit_amount, :total_service,
                    :balance_due, :payment_status, :service_status, :contract_id, :milestone, :cash_group
                 )
                 returning id'
            );

            $statement->execute([
                'service_id' => $booking->serviceId()?->value(),
                'staff_id' => $booking->staffId()?->value(),
                'customer_name' => $booking->customerName(),
                'customer_phone' => $booking->customerPhone(),
                'scheduled_for' => $booking->scheduledFor()?->format(DateTimeImmutable::ATOM),
                'status' => $booking->status()->value(),
                'address' => $booking->address(),
                'comuna' => $booking->comuna(),
                'service_value' => $booking->serviceValue(),
                'transfer_value' => $booking->transferValue(),
                'deposit_amount' => $booking->depositAmount(),
                'total_service' => $booking->totalService(),
                'balance_due' => $booking->balanceDue(),
                'payment_status' => $booking->paymentStatus(),
                'service_status' => $booking->serviceStatus(),
                'contract_id' => $booking->contractId(),
                'milestone' => $booking->milestone(),
                'cash_group' => $booking->cashGroup(),
            ]);

            $id = (int) $statement->fetchColumn();

            $savedBooking = $this->findById($id);
            if ($savedBooking === null) {
                throw new \RuntimeException('Failed to retrieve newly created booking.');
            }

            if ($startedTransaction) {
                $this->connection->commit();
            }

            return $savedBooking;
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
            'select b.id, b.service_id, b.staff_id, b.customer_name, b.customer_phone, b.scheduled_for, b.status,
                    b.address, b.comuna, b.service_value, b.transfer_value, b.deposit_amount, b.total_service,
                    b.balance_due, b.payment_status, b.service_status, b.contract_id, b.milestone, b.cash_group,
                    b.calendar_event_id, b.agenda_reference, b.sheet_external_id, b.source_sheet, b.source_row,
                    (b.transfer_receipt_image is not null) as has_transfer_receipt,
                    b.transfer_receipt_mime, b.transfer_receipt_filename, b.transfer_receipt_size,
                    bi.id as bitacora_id, b.estilista_id, est.display_name as estilista_name,
                    b.gas_last_sync_status, b.gas_last_sync_message,
                    s.name as service_name, st.display_name as staff_name
             from qs_bookings b
             left join qs_services s on b.service_id = s.id
             left join qs_staff st on b.staff_id = st.id
             left join qs_bitacoras bi on bi.booking_id = b.id
             left join qs_staff est on b.estilista_id = est.id
             order by b.scheduled_for desc, b.id desc'
        );

        $rows = $statement->fetchAll();

        return array_map(fn (array $row): Booking => $this->fromRow($row), $rows);
    }

    public function findById(int $id): ?Booking
    {
        $statement = $this->connection->prepare(
            'select b.id, b.service_id, b.staff_id, b.customer_name, b.customer_phone, b.scheduled_for, b.status,
                    b.address, b.comuna, b.service_value, b.transfer_value, b.deposit_amount, b.total_service,
                    b.balance_due, b.payment_status, b.service_status, b.contract_id, b.milestone, b.cash_group,
                    b.calendar_event_id, b.agenda_reference, b.sheet_external_id, b.source_sheet, b.source_row,
                    (b.transfer_receipt_image is not null) as has_transfer_receipt,
                    b.transfer_receipt_mime, b.transfer_receipt_filename, b.transfer_receipt_size,
                    bi.id as bitacora_id, b.estilista_id, est.display_name as estilista_name,
                    b.gas_last_sync_status, b.gas_last_sync_message,
                    s.name as service_name, st.display_name as staff_name
             from qs_bookings b
             left join qs_services s on b.service_id = s.id
             left join qs_staff st on b.staff_id = st.id
             left join qs_bitacoras bi on bi.booking_id = b.id
             left join qs_staff est on b.estilista_id = est.id
             where b.id = :id'
        );

        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return $this->fromRow($row);
    }

    public function update(int $id, array $data): ?Booking
    {
        $startedTransaction = !$this->connection->inTransaction();

        try {
            if ($startedTransaction) {
                $this->connection->beginTransaction();
            }

            $statement = $this->connection->prepare(
                'update qs_bookings
                 set service_id = :service_id,
                     staff_id = :staff_id,
                     customer_name = :customer_name,
                     customer_phone = :customer_phone,
                     scheduled_for = :scheduled_for,
                     status = :status,
                     address = :address,
                     comuna = :comuna,
                     service_value = :service_value,
                     transfer_value = :transfer_value,
                     deposit_amount = :deposit_amount,
                     total_service = :total_service,
                     balance_due = :balance_due,
                     payment_status = :payment_status,
                     service_status = :service_status,
                     contract_id = :contract_id,
                     milestone = :milestone,
                     cash_group = :cash_group
                 where id = :id
                 returning id'
            );

            $statement->execute([
                'id' => $id,
                'service_id' => $data['service_id'],
                'staff_id' => $data['staff_id'],
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'scheduled_for' => $data['scheduled_for'],
                'status' => $data['status'],
                'address' => $data['address'],
                'comuna' => $data['comuna'],
                'service_value' => $data['service_value'],
                'transfer_value' => $data['transfer_value'],
                'deposit_amount' => $data['deposit_amount'],
                'total_service' => $data['total_service'],
                'balance_due' => $data['balance_due'],
                'payment_status' => $data['payment_status'],
                'service_status' => $data['service_status'],
                'contract_id' => $data['contract_id'],
                'milestone' => $data['milestone'],
                'cash_group' => $data['cash_group'],
            ]);

            if ($statement->fetchColumn() === false) {
                if ($startedTransaction && $this->connection->inTransaction()) {
                    $this->connection->rollBack();
                }
                return null;
            }

            $updatedBooking = $this->findById($id);

            if ($startedTransaction) {
                $this->connection->commit();
            }

            return $updatedBooking;
        } catch (\Throwable $exception) {
            if ($startedTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    public function markServiceCompleted(int $id): ?Booking
    {
        $statement = $this->connection->prepare(
            "update qs_bookings
             set status = 'completed',
                 service_status = 'Realizado'
             where id = :id
             returning id"
        );
        $statement->execute(['id' => $id]);

        if ($statement->fetchColumn() === false) {
            return null;
        }

        return $this->findById($id);
    }

    public function updateTransferReceipt(int $id, array $receipt): ?Booking
    {
        $statement = $this->connection->prepare(
            "update qs_bookings
             set transfer_receipt_image = decode(:image_base64, 'base64'),
                 transfer_receipt_mime = :mime,
                 transfer_receipt_filename = :filename,
                 transfer_receipt_size = :size,
                 transfer_receipt_uploaded_at = now()
             where id = :id
             returning id"
        );
        $statement->execute([
            'id' => $id,
            'image_base64' => $receipt['image_base64'],
            'mime' => $receipt['mime'],
            'filename' => $receipt['filename'],
            'size' => $receipt['size'],
        ]);

        if ($statement->fetchColumn() === false) {
            return null;
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $statement = $this->connection->prepare('delete from qs_bookings where id = :id');
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }

    public function recordGasSyncResult(int $id, string $status, ?string $message): void
    {
        $statement = $this->connection->prepare(
            'update qs_bookings
             set gas_last_sync_at = now(),
                 gas_last_sync_status = :status,
                 gas_last_sync_message = :message
             where id = :id'
        );

        $statement->execute([
            'id' => $id,
            'status' => $status,
            'message' => $message,
        ]);
    }

    public function activeSlotsForStaffBetween(
        int $staffId,
        string $from,
        string $to,
        int $defaultDurationMinutes,
    ): array {
        $statement = $this->connection->prepare(
            "select b.scheduled_for,
                    coalesce(s.duration_minutes, :default_duration) as duration_minutes,
                    coalesce(s.name, 'Reserva') || ' — ' || coalesce(b.customer_name, 'sin clienta') as label
             from qs_bookings b
             left join qs_services s on s.id = b.service_id
             where b.staff_id = :staff_id
               and b.status <> 'cancelled'
               and b.scheduled_for is not null
               and b.scheduled_for between :from and :to"
        );

        $statement->execute([
            'staff_id' => $staffId,
            'default_duration' => $defaultDurationMinutes,
            'from' => $from,
            'to' => $to,
        ]);

        $slots = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $slots[] = [
                'label' => (string) $row['label'],
                'scheduled_for' => (string) $row['scheduled_for'],
                'duration_minutes' => (int) $row['duration_minutes'],
            ];
        }

        return $slots;
    }

    private function fromRow(array $row): Booking
    {
        return Booking::fromPersistence(
            (int) $row['id'],
            $row['service_id'] === null ? null : (int) $row['service_id'],
            $row['staff_id'] === null ? null : (int) $row['staff_id'],
            $row['customer_name'] === null ? null : (string) $row['customer_name'],
            $row['customer_phone'] === null ? null : (string) $row['customer_phone'],
            $row['scheduled_for'] === null ? null : (string) $row['scheduled_for'],
            (string) $row['status'],
            $row['service_name'] === null ? null : (string) $row['service_name'],
            $row['staff_name'] === null ? null : (string) $row['staff_name'],
            $row['address'] === null ? null : (string) $row['address'],
            $row['comuna'] === null ? null : (string) $row['comuna'],
            $row['service_value'] === null ? null : (float) $row['service_value'],
            $row['transfer_value'] === null ? null : (float) $row['transfer_value'],
            $row['deposit_amount'] === null ? null : (float) $row['deposit_amount'],
            $row['total_service'] === null ? null : (float) $row['total_service'],
            $row['balance_due'] === null ? null : (float) $row['balance_due'],
            $row['payment_status'] === null ? null : (string) $row['payment_status'],
            $row['service_status'] === null ? null : (string) $row['service_status'],
            $row['contract_id'] === null ? null : (string) $row['contract_id'],
            $row['milestone'] === null ? null : (string) $row['milestone'],
            $row['cash_group'] === null ? null : (string) $row['cash_group'],
            $row['calendar_event_id'] === null ? null : (string) $row['calendar_event_id'],
            $row['agenda_reference'] === null ? null : (string) $row['agenda_reference'],
            $row['sheet_external_id'] === null ? null : (string) $row['sheet_external_id'],
            $row['source_sheet'] === null ? null : (string) $row['source_sheet'],
            $row['source_row'] === null ? null : (int) $row['source_row'],
            $row['bitacora_id'] === null ? null : (int) $row['bitacora_id'],
            $row['estilista_id'] === null ? null : (int) $row['estilista_id'],
            $row['estilista_name'] === null ? null : (string) $row['estilista_name'],
            $row['gas_last_sync_status'] === null ? null : (string) $row['gas_last_sync_status'],
            $row['gas_last_sync_message'] === null ? null : (string) $row['gas_last_sync_message'],
            $this->boolValue($row['has_transfer_receipt']),
            $row['transfer_receipt_mime'] === null ? null : (string) $row['transfer_receipt_mime'],
            $row['transfer_receipt_filename'] === null ? null : (string) $row['transfer_receipt_filename'],
            $row['transfer_receipt_size'] === null ? null : (int) $row['transfer_receipt_size'],
        );
    }

    private function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 't', 'true', 'yes'], true);
    }
}
