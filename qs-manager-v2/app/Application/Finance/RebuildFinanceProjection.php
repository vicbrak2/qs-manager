<?php

declare(strict_types=1);

namespace QSManager\Application\Finance;

use PDO;
use RuntimeException;
use Throwable;

final class RebuildFinanceProjection
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function rebuild(int $syncRunId): void
    {
        try {
            $this->connection->beginTransaction();

            $this->deleteOutdatedProjections();
            $this->projectServiceRevenues($syncRunId);
            $this->projectCustomerPayments($syncRunId);
            $this->projectOperationalExpenses($syncRunId);
            $this->projectDirectCosts($syncRunId);
            $this->projectRefunds($syncRunId);

            $this->connection->commit();
        } catch (Throwable $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw new RuntimeException('Failed to rebuild finance projection: ' . $e->getMessage(), 0, $e);
        }
    }

    private function deleteOutdatedProjections(): void
    {
        $this->connection->exec("
            DELETE FROM qs_finance_entries 
            WHERE source_type IN ('cash_tracking', 'operational_expense', 'service_catalog', 'booking', 'bitacora', 'agenda', 'workshop')
        ");
    }

    private function projectServiceRevenues(int $syncRunId): void
    {
        $sql = "
            INSERT INTO qs_finance_entries (
                external_id, entry_type, source_type, source_sheet, source_row, 
                occurred_on, status, currency, metadata, import_run_id, sync_run_id, amount
            )
            SELECT 
                r.stable_external_id, 
                'service_revenue', 'cash_tracking', s.sheet_name, r.source_row,
                r.service_date, 'completed', 'CLP', jsonb_build_object('service', r.service_names, 'customer', r.customer_name, 'confidence', CASE WHEN r.service_external_id IS NOT NULL THEN 'high' ELSE 'low' END), r.import_run_id, :run_id,
                r.total_services
            FROM v_cash_tracking_latest r
            JOIN qs_sheet_import_runs run ON run.id = r.import_run_id
            JOIN qs_sheet_sources s ON s.id = run.source_id
            WHERE lower(trim(r.service_status)) IN ('realizada', 'confirmado', 'realizado', 'confirmada')
            AND r.total_services > 0
        ";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['run_id' => $syncRunId]);
        
        // Fallback from Bitacora (Deduplication)
        $sql2 = "
            INSERT INTO qs_finance_entries (
                external_id, entry_type, source_type, source_sheet, source_row, 
                occurred_on, status, currency, metadata, import_run_id, sync_run_id, amount
            )
            SELECT 
                b.stable_external_id, 
                'service_revenue', 'bitacora', s.sheet_name, b.source_row,
                b.service_date, 'completed', 'CLP', jsonb_build_object('service', b.service_name, 'customer', b.customer_name, 'fallback', true, 'confidence', CASE WHEN b.qs_external_id IS NOT NULL THEN 'high' ELSE 'low' END), b.import_run_id, :run_id,
                b.total_service
            FROM v_bitacora_latest b
            JOIN qs_sheet_import_runs run ON run.id = b.import_run_id
            JOIN qs_sheet_sources s ON s.id = run.source_id
            WHERE lower(trim(b.service_status)) IN ('realizada', 'confirmado', 'realizado', 'confirmada')
            AND b.total_service > 0
            AND NOT EXISTS (
                SELECT 1 FROM v_cash_tracking_latest c
                WHERE c.stable_external_id = b.stable_external_id
                   OR (b.calendar_event_id IS NOT NULL AND c.stable_external_id = b.calendar_event_id)
            );
        ";
        // Fallback from Agenda (Deduplication)
        $sql3 = "
            INSERT INTO qs_finance_entries (
                external_id, entry_type, source_type, source_sheet, source_row, 
                occurred_on, status, currency, metadata, import_run_id, sync_run_id, amount
            )
            SELECT 
                a.stable_external_id, 
                'service_revenue', 'agenda', s.sheet_name, a.source_row,
                a.service_date, 'completed', 'CLP', jsonb_build_object('service', a.service_name, 'customer', a.customer_name, 'fallback', true, 'confidence', CASE WHEN a.calendar_event_id IS NOT NULL THEN 'high' ELSE 'low' END), a.import_run_id, :run_id,
                a.total_service
            FROM v_agenda_latest a
            JOIN qs_sheet_import_runs run ON run.id = a.import_run_id
            JOIN qs_sheet_sources s ON s.id = run.source_id
            WHERE lower(trim(a.event_status)) IN ('realizada', 'confirmado', 'realizado', 'confirmada')
            AND a.total_service > 0
            AND NOT EXISTS (
                SELECT 1 FROM v_bitacora_latest b
                WHERE b.stable_external_id = a.stable_external_id
                   OR (b.calendar_event_id IS NOT NULL AND a.calendar_event_id IS NOT NULL AND b.calendar_event_id = a.calendar_event_id)
            )
            AND NOT EXISTS (
                SELECT 1 FROM v_cash_tracking_latest c
                WHERE c.stable_external_id = a.stable_external_id
            );
        ";

        $stmt2 = $this->connection->prepare($sql2);
        $stmt2->execute(['run_id' => $syncRunId]);
        
        $stmt3 = $this->connection->prepare($sql3);
        $stmt3->execute(['run_id' => $syncRunId]);

        $sql4 = "
            INSERT INTO qs_finance_entries (
                external_id, entry_type, source_type, source_sheet, source_row, 
                occurred_on, status, currency, metadata, import_run_id, sync_run_id, amount
            )
            SELECT 
                w.stable_external_id, 
                'service_revenue', 'workshop', s.sheet_name, w.source_row,
                w.workshop_date, 'completed', 'CLP', jsonb_build_object('customer', w.customer_name, 'notes', w.notes, 'income_type', 'Taller'), w.import_run_id, :run_id,
                w.payment_amount
            FROM v_workshop_latest w
            JOIN qs_sheet_import_runs run ON run.id = w.import_run_id
            JOIN qs_sheet_sources s ON s.id = run.source_id
            WHERE w.payment_amount > 0;
        ";
        
        $stmt4 = $this->connection->prepare($sql4);
        $stmt4->execute(['run_id' => $syncRunId]);
    }

    private function projectCustomerPayments(int $syncRunId): void
    {
        $sql = "
            INSERT INTO qs_finance_entries (
                external_id, entry_type, source_type, source_sheet, source_row, 
                occurred_on, status, currency, metadata, import_run_id, sync_run_id, amount
            )
            SELECT 
                r.stable_external_id || '-pay', 
                'customer_payment', 'cash_tracking', s.sheet_name, r.source_row,
                r.service_date, 'completed', 'CLP', jsonb_build_object('service', r.service_names, 'payment_status', r.payment_status), r.import_run_id, :run_id,
                CASE 
                    WHEN lower(trim(r.payment_status)) = 'pagado' THEN r.total_services 
                    ELSE r.deposit_amount 
                END
            FROM v_cash_tracking_latest r
            JOIN qs_sheet_import_runs run ON run.id = r.import_run_id
            JOIN qs_sheet_sources s ON s.id = run.source_id
            WHERE lower(trim(r.service_status)) NOT IN ('anulado', 'cancelado', 'cancelada', 'anulada', 'no asiste')
            AND (r.deposit_amount > 0 OR lower(trim(r.payment_status)) = 'pagado')
        ";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['run_id' => $syncRunId]);

        $sql2 = "
            INSERT INTO qs_finance_entries (
                external_id, entry_type, source_type, source_sheet, source_row, 
                occurred_on, status, currency, metadata, import_run_id, sync_run_id, amount
            )
            SELECT 
                w.stable_external_id || '-pay', 
                'customer_payment', 'workshop', s.sheet_name, w.source_row,
                COALESCE(w.payment_date, w.workshop_date), 'completed', 'CLP', jsonb_build_object('customer', w.customer_name, 'income_type', 'Taller'), w.import_run_id, :run_id,
                w.payment_amount
            FROM v_workshop_latest w
            JOIN qs_sheet_import_runs run ON run.id = w.import_run_id
            JOIN qs_sheet_sources s ON s.id = run.source_id
            WHERE w.payment_amount > 0;
        ";

        $stmt2 = $this->connection->prepare($sql2);
        $stmt2->execute(['run_id' => $syncRunId]);
    }

    private function projectOperationalExpenses(int $syncRunId): void
    {
        $sql = "
            INSERT INTO qs_finance_entries (
                external_id, entry_type, source_type, source_sheet, source_row, 
                occurred_on, status, currency, metadata, import_run_id, sync_run_id, amount
            )
            SELECT 
                r.stable_external_id, 'operational_expense', 'operational_expense', s.sheet_name, r.source_row,
                r.expense_date, 'completed', 'CLP', jsonb_build_object('category', r.concept, 'description', r.observations), r.import_run_id, :run_id,
                r.amount
            FROM v_operational_expense_latest r
            JOIN qs_sheet_import_runs run ON run.id = r.import_run_id
            JOIN qs_sheet_sources s ON s.id = run.source_id
            WHERE lower(trim(r.expense_status)) IN ('pagado', 'aprobado', 'pagada', 'aprobada')
            AND r.amount > 0
        ";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['run_id' => $syncRunId]);
    }

    private function projectDirectCosts(int $syncRunId): void
    {
        $sql = "
            INSERT INTO qs_finance_entries (
                external_id, entry_type, source_type, source_sheet, source_row, 
                occurred_on, status, currency, metadata, import_run_id, sync_run_id, amount
            )
            SELECT 
                r.stable_external_id || '-cost', 
                'direct_cost', 'cash_tracking', s.sheet_name, r.source_row,
                r.service_date, 'completed', 'CLP', jsonb_build_object('service', r.service_names, 'is_estimate', true), r.import_run_id, :run_id,
                r.operating_expenses
            FROM v_cash_tracking_latest r
            JOIN qs_sheet_import_runs run ON run.id = r.import_run_id
            JOIN qs_sheet_sources s ON s.id = run.source_id
            WHERE lower(trim(r.service_status)) IN ('realizada', 'confirmado', 'realizado', 'confirmada')
            AND r.operating_expenses > 0
        ";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['run_id' => $syncRunId]);
    }

    private function projectRefunds(int $syncRunId): void
    {
        $sql = "
            INSERT INTO qs_finance_entries (
                external_id, entry_type, source_type, source_sheet, source_row, 
                occurred_on, status, currency, metadata, import_run_id, sync_run_id, amount
            )
            SELECT 
                r.stable_external_id || '-refund', 'refund', 'operational_expense', s.sheet_name, r.source_row,
                r.expense_date, 'completed', 'CLP', jsonb_build_object('category', r.concept, 'description', r.observations), r.import_run_id, :run_id,
                r.amount
            FROM v_operational_expense_latest r
            JOIN qs_sheet_import_runs run ON run.id = r.import_run_id
            JOIN qs_sheet_sources s ON s.id = run.source_id
            WHERE lower(trim(r.expense_status)) IN ('devolucion', 'devolución', 'reembolso')
            AND r.amount > 0
        ";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['run_id' => $syncRunId]);
    }
}
