<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Sheets;

use PDO;

/**
 * Escritura de proyecciones hacia qs_services / qs_bookings -- extraida de
 * PostgresSheetReplicaImporter (Fase 4). Compartida por varios importers
 * (ServicesCatalogImporter, ServicesMasterImporter, BitacoraImporter,
 * AgendaMonthImporter), por eso vive aparte en vez de duplicarse en cada
 * uno. Mismo SQL exacto que el original, sin cambios de comportamiento.
 */
final class BookingProjectionWriter
{
    public function __construct(
        private readonly PDO $connection,
        private readonly SheetRowMapper $mapper,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public function upsertServiceProjection(string $sheetName, int $sourceRow, array $row, string $serviceName): void
    {
        $this->connection->prepare(
            'insert into qs_services (
                name, category, duration_minutes, quantity, active, sale_price, total_cost, utility,
                margin_percent, margin_status, source_sheet, source_row
            ) values (
                :name, :category, null, :quantity, :active, :sale_price, :total_cost, :utility,
                :margin_percent, :margin_status, :source_sheet, :source_row
            )
            on conflict (source_sheet, source_row) where source_sheet is not null and source_row is not null
            do update set
                name = excluded.name,
                category = excluded.category,
                quantity = excluded.quantity,
                active = excluded.active,
                sale_price = excluded.sale_price,
                total_cost = excluded.total_cost,
                utility = excluded.utility,
                margin_percent = excluded.margin_percent,
                margin_status = excluded.margin_status'
        )->execute([
            'name' => $serviceName,
            'category' => $this->mapper->string($row, 'categoria'),
            'quantity' => $this->mapper->positiveInt($row, 'cantidad') ?? 1,
            'active' => $this->mapper->dbBool($this->mapper->bool($row, 'activo') ?? true),
            'sale_price' => $this->mapper->catalogMoney($row, 'precio venta'),
            'total_cost' => $this->mapper->catalogMoney($row, 'costo total'),
            'utility' => $this->mapper->catalogMoney($row, 'utilidad'),
            'margin_percent' => $this->mapper->percent($row, 'margen %'),
            'margin_status' => $this->mapper->string($row, 'estado'),
            'source_sheet' => $sheetName,
            'source_row' => $sourceRow,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function upsertMasterServiceProjection(
        string $sheetName,
        int $sourceRow,
        array $row,
        string $serviceId,
        string $serviceName,
    ): void {
        $statement = $this->connection->prepare(
            'update qs_services
             set sheet_external_id = :sheet_external_id,
                 category = :category,
                 quantity = :quantity,
                 active = :active,
                 sale_price = :sale_price,
                 total_cost = :total_cost,
                 utility = :utility,
                 margin_percent = :margin_percent,
                 margin_status = :margin_status,
                 source_sheet = :source_sheet,
                 source_row = :source_row
             where id = (
                 select id from qs_services
                 where lower(trim(name)) = lower(trim(:name))
                 order by case when source_sheet = :source_sheet then 0 else 1 end, id asc
                 limit 1
             )
             returning id'
        );

        $statement->execute([
            'sheet_external_id' => $serviceId,
            'name' => $serviceName,
            'category' => $this->mapper->string($row, 'categoria'),
            'quantity' => $this->mapper->positiveInt($row, 'cantidad') ?? 1,
            'active' => $this->mapper->dbBool($this->mapper->bool($row, 'activo') ?? true),
            'sale_price' => $this->mapper->money($row, 'precio_venta_clp'),
            'total_cost' => $this->mapper->money($row, 'costo_total_clp'),
            'utility' => $this->mapper->money($row, 'utilidad_clp'),
            'margin_percent' => $this->mapper->percent($row, 'margen'),
            'margin_status' => $this->mapper->string($row, 'estado_margen'),
            'source_sheet' => $sheetName,
            'source_row' => $sourceRow,
        ]);

        if ($statement->fetchColumn() !== false) {
            return;
        }

        $this->connection->prepare(
            'insert into qs_services (
                sheet_external_id, name, category, duration_minutes, quantity, active, sale_price, total_cost,
                utility, margin_percent, margin_status, source_sheet, source_row
            ) values (
                :sheet_external_id, :name, :category, null, :quantity, :active, :sale_price, :total_cost,
                :utility, :margin_percent, :margin_status, :source_sheet, :source_row
            ) on conflict (source_sheet, source_row) where source_sheet is not null and source_row is not null do update set
                sheet_external_id = excluded.sheet_external_id,
                name = excluded.name,
                category = excluded.category,
                quantity = excluded.quantity,
                active = excluded.active,
                sale_price = excluded.sale_price,
                total_cost = excluded.total_cost,
                utility = excluded.utility,
                margin_percent = excluded.margin_percent,
                margin_status = excluded.margin_status'
        )->execute([
            'sheet_external_id' => $serviceId,
            'name' => $serviceName,
            'category' => $this->mapper->string($row, 'categoria'),
            'quantity' => $this->mapper->positiveInt($row, 'cantidad') ?? 1,
            'active' => $this->mapper->dbBool($this->mapper->bool($row, 'activo') ?? true),
            'sale_price' => $this->mapper->money($row, 'precio_venta_clp'),
            'total_cost' => $this->mapper->money($row, 'costo_total_clp'),
            'utility' => $this->mapper->money($row, 'utilidad_clp'),
            'margin_percent' => $this->mapper->percent($row, 'margen'),
            'margin_status' => $this->mapper->string($row, 'estado_margen'),
            'source_sheet' => $sheetName,
            'source_row' => $sourceRow,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function upsertBookingProjection(string $sheetName, int $sourceRow, array $row, string $externalId): void
    {
        $scheduledFor = $this->mapper->scheduledFor($row);
        $serviceId = $this->serviceIdByName($this->mapper->string($row, 'servicio'));

        $this->connection->prepare(
            'insert into qs_bookings (
                service_id, staff_id, customer_name, customer_phone, scheduled_for, status,
                address, comuna, service_value, transfer_value, deposit_amount, total_service,
                balance_due, payment_status, service_status, contract_id, milestone, cash_group,
                calendar_event_id, agenda_reference, sheet_external_id, source_sheet, source_row,
                sheets_last_import_at
            ) values (
                :service_id, null, :customer_name, null, :scheduled_for, :status,
                :address, :comuna, :service_value, :transfer_value, :deposit_amount, :total_service,
                :balance_due, :payment_status, :service_status, :contract_id, :milestone, :cash_group,
                :calendar_event_id, :agenda_reference, :sheet_external_id, :source_sheet, :source_row,
                now()
            )
            on conflict (sheet_external_id) where sheet_external_id is not null
            do update set
                service_id = excluded.service_id,
                customer_name = excluded.customer_name,
                scheduled_for = excluded.scheduled_for,
                status = excluded.status,
                address = excluded.address,
                comuna = excluded.comuna,
                service_value = excluded.service_value,
                transfer_value = excluded.transfer_value,
                deposit_amount = excluded.deposit_amount,
                total_service = excluded.total_service,
                balance_due = excluded.balance_due,
                payment_status = excluded.payment_status,
                service_status = excluded.service_status,
                contract_id = excluded.contract_id,
                milestone = excluded.milestone,
                cash_group = excluded.cash_group,
                calendar_event_id = excluded.calendar_event_id,
                agenda_reference = excluded.agenda_reference,
                source_sheet = excluded.source_sheet,
                source_row = excluded.source_row,
                sheets_last_import_at = now()'
        )->execute([
            'service_id' => $serviceId,
            'customer_name' => $this->mapper->string($row, 'clienta'),
            'scheduled_for' => $scheduledFor,
            'status' => $this->mapper->bookingStatus($this->mapper->string($row, 'estado servicio')),
            'address' => $this->mapper->string($row, 'direccion'),
            'comuna' => $this->mapper->string($row, 'comuna'),
            'service_value' => $this->mapper->money($row, 'valor servicio'),
            'transfer_value' => $this->mapper->money($row, 'traslado'),
            'deposit_amount' => $this->mapper->money($row, 'abono'),
            'total_service' => $this->mapper->money($row, 'total servicio'),
            'balance_due' => $this->mapper->money($row, 'saldo'),
            'payment_status' => $this->mapper->string($row, 'estado pago'),
            'service_status' => $this->mapper->string($row, 'estado servicio'),
            'contract_id' => $this->mapper->string($row, 'id contrato'),
            'milestone' => $this->mapper->string($row, 'hito'),
            'cash_group' => $this->mapper->string($row, 'grupo caja'),
            'calendar_event_id' => $this->mapper->string($row, 'id calendar'),
            'agenda_reference' => $this->mapper->string($row, 'referencia agenda'),
            'sheet_external_id' => $externalId,
            'source_sheet' => $sheetName,
            'source_row' => $sourceRow,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function upsertAgendaBookingProjection(string $sheetName, int $sourceRow, array $row): void
    {
        $scheduledFor = $this->mapper->scheduledFor($row);
        $serviceName = $this->mapper->string($row, 'servicio');
        $financials = $this->mapper->agendaFinancials($row);

        $this->connection->prepare(
            'insert into qs_bookings (
                service_id, staff_id, customer_name, customer_phone, scheduled_for, status,
                address, comuna, service_value, transfer_value, deposit_amount, total_service,
                balance_due, payment_status, service_status, calendar_event_id, source_sheet,
                source_row, sheets_last_import_at
            ) values (
                :service_id, null, :customer_name, :customer_phone, :scheduled_for, :status,
                :address, :comuna, :service_value, :transfer_value, :deposit_amount, :total_service,
                :balance_due, :payment_status, :service_status, :calendar_event_id, :source_sheet,
                :source_row, now()
            )
            on conflict (source_sheet, source_row) where source_sheet is not null and source_row is not null
            do update set
                service_id = excluded.service_id,
                customer_name = excluded.customer_name,
                customer_phone = excluded.customer_phone,
                scheduled_for = excluded.scheduled_for,
                status = excluded.status,
                address = excluded.address,
                comuna = excluded.comuna,
                service_value = excluded.service_value,
                transfer_value = excluded.transfer_value,
                deposit_amount = excluded.deposit_amount,
                total_service = excluded.total_service,
                balance_due = excluded.balance_due,
                payment_status = excluded.payment_status,
                service_status = excluded.service_status,
                calendar_event_id = excluded.calendar_event_id,
                sheets_last_import_at = now()'
        )->execute([
            'service_id' => $this->serviceIdByName($serviceName),
            'customer_name' => $this->mapper->string($row, 'clienta'),
            'customer_phone' => $this->mapper->string($row, 'telefono'),
            'scheduled_for' => $scheduledFor,
            'status' => $this->mapper->bookingStatus($this->mapper->string($row, 'estado evento')),
            'address' => $this->mapper->string($row, 'direccion'),
            'comuna' => $this->mapper->string($row, 'comuna'),
            'service_value' => $financials['service_value'],
            'transfer_value' => $this->mapper->money($row, 'traslado'),
            'deposit_amount' => $this->mapper->money($row, 'abono'),
            'total_service' => $financials['total_service'],
            'balance_due' => $financials['balance_due'],
            'payment_status' => $financials['payment_status'],
            'service_status' => $this->mapper->string($row, 'estado evento'),
            'calendar_event_id' => $this->mapper->string($row, 'id evento'),
            'source_sheet' => $sheetName,
            'source_row' => $sourceRow,
        ]);
    }

    public function serviceIdByName(?string $serviceName): ?int
    {
        if ($serviceName === null) {
            return null;
        }

        // Agenda 2026 still contains this legacy label. Keep the source wording
        // while resolving it to the active canonical service in Servicios_Master.
        $serviceName = match (mb_strtolower(trim($serviceName))) {
            'novia fiesta (prueba presencial)' => 'Novia Fiesta Maquillaje Peinado (prueba presencial)',
            default => $serviceName,
        };

        $statement = $this->connection->prepare(
            "select id
             from qs_services
             where lower(trim(name)) = lower(trim(:name))
             order by case source_sheet
                 when 'Servicios_Master' then 0
                 when 'Servicios' then 1
                 else 2
             end, id asc
             limit 1"
        );
        $statement->execute(['name' => $serviceName]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }
}
