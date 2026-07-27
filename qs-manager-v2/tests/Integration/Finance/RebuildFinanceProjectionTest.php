<?php

declare(strict_types=1);

namespace QSManager\Tests\Integration\Finance;

use PDO;
use PHPUnit\Framework\TestCase;
use QSManager\Application\Finance\RebuildFinanceProjection;
use QSManager\Domain\Finance\AccountingBasis;
use QSManager\Domain\Finance\FinancePeriod;
use QSManager\Infrastructure\Database\ConnectionFactory;
use QSManager\Infrastructure\Persistence\Postgres\PostgresFinanceReadRepository;

final class RebuildFinanceProjectionTest extends TestCase
{
    private PDO $pdo;
    private RebuildFinanceProjection $projection;

    protected function setUp(): void
    {
        $this->pdo = ConnectionFactory::fromEnvironment();
        $this->projection = new RebuildFinanceProjection($this->pdo);

        // Limpiar base de datos
        $this->pdo->exec('TRUNCATE qs_finance_entries, qs_sync_runs, qs_sheet_agenda_month_rows, qs_sheet_bitacora_rows, qs_sheet_cash_tracking_rows, qs_sheet_operational_expense_rows, qs_sheet_workshop_rows, qs_sheet_import_runs, qs_sheet_sources RESTART IDENTITY CASCADE');
        
        // Crear fuentes
        $this->pdo->exec("
            INSERT INTO qs_sheet_sources (id, spreadsheet_id, spreadsheet_title, sheet_name, purpose) 
            VALUES 
                (1, 'mock', 'mock', 'Seguimiento Caja', 'cash_tracking'), 
                (2, 'mock', 'mock', 'Gastos Operativos', 'operational_expense'),
                (3, 'mock', 'mock', 'Bitacora', 'bitacora'),
                (4, 'mock', 'mock', 'Agenda', 'agenda'),
                (5, 'mock', 'mock', 'Talleres', 'workshop')
        ");
    }

    public function testDeduplicationAndMatrixLogic(): void
    {
        // Creamos un import exitoso
        $this->pdo->exec("INSERT INTO qs_sheet_import_runs (id, source_id, status) VALUES (1, 1, 'completed')");
        $this->pdo->exec("INSERT INTO qs_sheet_import_runs (id, source_id, status) VALUES (2, 2, 'completed')");

        // Fila 5: Reserva en Bitacora que ya existe en Caja (external_id = 'EXT-1').
        // La consulta de deduplicación hace join con qs_external_id = service_external_id.
        $this->pdo->exec("
            INSERT INTO qs_sheet_cash_tracking_rows 
            (id, import_run_id, source_row, service_external_id, service_date, service_status, payment_status, deposit_amount, total_services, operating_expenses) 
            VALUES (1, 1, 2, 'EXT-1', '2026-01-01', 'Realizada', 'Pagado', 5000, 10000, 2000)
        ");

        $this->pdo->exec("INSERT INTO qs_sheet_import_runs (id, source_id, status) VALUES (3, 3, 'completed')");
        $this->pdo->exec("
            INSERT INTO qs_sheet_bitacora_rows
            (import_run_id, source_row, qs_external_id, service_date, service_status, total_service)
            VALUES (3, 2, 'EXT-1', '2026-01-01', 'Realizada', 10000)
        ");

        // Fila 6: Reserva en Bitacora que NO existe en Caja (external_id = 'EXT-2') -> Debería crear service_revenue por fallback.
        $this->pdo->exec("
            INSERT INTO qs_sheet_bitacora_rows
            (import_run_id, source_row, qs_external_id, calendar_event_id, service_date, service_status, total_service)
            VALUES (3, 3, 'EXT-2', 'CAL-2', '2026-01-01', 'Realizada', 8000)
        ");

        $this->pdo->exec("INSERT INTO qs_sheet_import_runs (id, source_id, status) VALUES (4, 4, 'completed')");
        // Fila 7: Reserva en Agenda que existe en Bitacora (CAL-2). No debe duplicar.
        $this->pdo->exec("
            INSERT INTO qs_sheet_agenda_month_rows
            (import_run_id, source_sheet, source_row, calendar_event_id, service_date, event_status, total_service)
            VALUES (4, 'Agenda', 2, 'CAL-2', '2026-01-01', 'Realizada', 8000)
        ");

        // Fila 8: Reserva en Agenda que NO existe en Bitacora ni Caja (CAL-3). Debería crear fallback.
        $this->pdo->exec("
            INSERT INTO qs_sheet_agenda_month_rows
            (import_run_id, source_sheet, source_row, calendar_event_id, service_date, event_status, total_service)
            VALUES (4, 'Agenda', 3, 'CAL-3', '2026-01-01', 'Realizada', 4000)
        ");

        // Fila 7: Gasto Operativo Pagado
        $this->pdo->exec("
            INSERT INTO qs_sheet_operational_expense_rows
            (import_run_id, source_row, expense_date, expense_status, amount, concept)
            VALUES (2, 2, '2026-01-01', 'Pagado', 3000, 'Insumos')
        ");

        // Fila 8: Devolucion
        $this->pdo->exec("
            INSERT INTO qs_sheet_operational_expense_rows
            (import_run_id, source_row, expense_date, expense_status, amount, concept)
            VALUES (2, 3, '2026-01-02', 'Devolución', 5000, 'Reembolso Cliente')
        ");

        $this->pdo->exec("INSERT INTO qs_sheet_import_runs (id, source_id, status) VALUES (5, 5, 'completed')");
        $this->pdo->exec("
            INSERT INTO qs_sheet_workshop_rows
            (import_run_id, source_row, workshop_date, customer_name, payment_amount, payment_date)
            VALUES (5, 1, '2026-01-05', 'Tallerista 1', 12000, '2026-01-05')
        ");

        $this->pdo->exec("INSERT INTO qs_sync_runs (id, status, mode) VALUES (99, 'completed', 'write')");

        $this->projection->rebuild(99); 

        $stmt = $this->pdo->query("SELECT entry_type, amount, metadata->>'fallback' as fallback FROM qs_finance_entries ORDER BY entry_type, amount");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $expected = [
            ['entry_type' => 'customer_payment', 'amount' => '10000.00', 'fallback' => null],
            ['entry_type' => 'customer_payment', 'amount' => '12000.00', 'fallback' => null], // Taller
            ['entry_type' => 'direct_cost', 'amount' => '2000.00', 'fallback' => null],
            ['entry_type' => 'operational_expense', 'amount' => '3000.00', 'fallback' => null],
            ['entry_type' => 'refund', 'amount' => '5000.00', 'fallback' => null],
            ['entry_type' => 'service_revenue', 'amount' => '4000.00', 'fallback' => 'true'], // De Agenda (CAL-3)
            ['entry_type' => 'service_revenue', 'amount' => '8000.00', 'fallback' => 'true'], // De Bitacora (EXT-2)
            ['entry_type' => 'service_revenue', 'amount' => '10000.00', 'fallback' => null], // De Caja (EXT-1)
            ['entry_type' => 'service_revenue', 'amount' => '12000.00', 'fallback' => null], // Taller
        ];

        $this->assertSame($expected, $results);

        // Test Idempotencia: Ejecutar de nuevo no debe duplicar nada
        $this->projection->rebuild(99);
        $stmt2 = $this->pdo->query("SELECT entry_type, amount, metadata->>'fallback' as fallback FROM qs_finance_entries ORDER BY entry_type, amount");
        $this->assertSame($expected, $stmt2->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testRollbackOnFailure(): void
    {
        $this->pdo->exec("INSERT INTO qs_sheet_import_runs (id, source_id, status) VALUES (1, 1, 'completed')");
        $this->pdo->exec("
            INSERT INTO qs_sheet_cash_tracking_rows 
            (import_run_id, source_row, service_date, service_status, payment_status, total_services) 
            VALUES (1, 2, '2026-01-01', 'Realizada', 'Pagado', 5000)
        ");
        
        $this->pdo->exec("INSERT INTO qs_sync_runs (id, status, mode) VALUES (99, 'completed', 'write')");
        $this->projection->rebuild(99);

        $countBefore = $this->pdo->query("SELECT COUNT(*) FROM qs_finance_entries")->fetchColumn();
        $this->assertEquals(2, $countBefore); // 1 revenue, 1 payment

        // Romper el esquema deliberadamente para forzar una excepcion durante rebuild
        $this->pdo->exec("ALTER TABLE qs_finance_entries RENAME COLUMN amount TO broken_amount");

        $this->expectException(\RuntimeException::class);

        try {
            $this->projection->rebuild(99);
        } finally {
            // Restaurar para comprobar y no romper el esquema
            $this->pdo->exec("ALTER TABLE qs_finance_entries RENAME COLUMN broken_amount TO amount");

            // El conteo debe ser exactamente el mismo gracias al rollback
            $countAfter = $this->pdo->query("SELECT COUNT(*) FROM qs_finance_entries")->fetchColumn();
            $this->assertEquals($countBefore, $countAfter);
        }
    }

    public function testReconciliationToleranceZero(): void
    {
        $this->pdo->exec("INSERT INTO qs_sheet_import_runs (id, source_id, status) VALUES (1, 1, 'completed')");
        $this->pdo->exec("INSERT INTO qs_sheet_import_runs (id, source_id, status) VALUES (2, 2, 'completed')");
        
        // Insert multiple rows, including some that should be excluded
        $this->pdo->exec("
            INSERT INTO qs_sheet_cash_tracking_rows 
            (import_run_id, source_row, service_date, service_status, payment_status, total_services, deposit_amount, operating_expenses) 
            VALUES 
            (1, 2, '2026-01-01', 'Realizada', 'Pagado', 15000, 5000, 1000),
            (1, 3, '2026-01-02', 'Cancelado', 'Pendiente', 5000, 0, 0), -- Should be excluded for revenue and costs
            (1, 4, '2026-01-03', 'Confirmado', 'Pendiente', 12000, 3000, 2000)
        ");

        $this->pdo->exec("
            INSERT INTO qs_sheet_operational_expense_rows
            (import_run_id, source_row, expense_date, expense_status, amount, concept)
            VALUES 
            (2, 2, '2026-01-01', 'Pagado', 3000, 'Insumos'),
            (2, 3, '2026-01-02', 'Devolución', 5000, 'Reembolso'),
            (2, 4, '2026-01-03', 'Rechazado', 10000, 'Error') -- Should be excluded
        ");

        $this->pdo->exec("INSERT INTO qs_sync_runs (id, status, mode) VALUES (99, 'completed', 'write')");
        $this->projection->rebuild(99);

        // 1. Service Revenue
        $sheetTotalRevenue = $this->pdo->query("
            SELECT COALESCE(SUM(total_services), 0)::numeric(12,2)::text 
            FROM qs_sheet_cash_tracking_rows 
            WHERE lower(trim(service_status)) IN ('realizada', 'confirmado', 'realizado', 'confirmada')
        ")->fetchColumn();

        $projectedTotalRevenue = $this->pdo->query("
            SELECT COALESCE(SUM(amount), 0)::numeric(12,2)::text 
            FROM qs_finance_entries WHERE entry_type = 'service_revenue'
        ")->fetchColumn();

        $this->assertEquals('27000.00', $sheetTotalRevenue);
        $this->assertSame($sheetTotalRevenue, $projectedTotalRevenue, "Revenue totals must match");

        // 2. Customer Payments
        $sheetTotalPayments = $this->pdo->query("
            SELECT COALESCE(SUM(CASE WHEN lower(trim(payment_status)) = 'pagado' THEN total_services ELSE deposit_amount END), 0)::numeric(12,2)::text 
            FROM qs_sheet_cash_tracking_rows 
            WHERE lower(trim(service_status)) NOT IN ('anulado', 'cancelado', 'cancelada', 'anulada', 'no asiste')
            AND (deposit_amount > 0 OR lower(trim(payment_status)) = 'pagado')
        ")->fetchColumn();

        $projectedTotalPayments = $this->pdo->query("
            SELECT COALESCE(SUM(amount), 0)::numeric(12,2)::text 
            FROM qs_finance_entries WHERE entry_type = 'customer_payment'
        ")->fetchColumn();

        $this->assertEquals('18000.00', $sheetTotalPayments); // 15000 (Pagado) + 3000 (Pendiente, Deposit)
        $this->assertSame($sheetTotalPayments, $projectedTotalPayments, "Payment totals must match");

        // 3. Direct Costs
        $sheetTotalCosts = $this->pdo->query("
            SELECT COALESCE(SUM(operating_expenses), 0)::numeric(12,2)::text 
            FROM qs_sheet_cash_tracking_rows 
            WHERE lower(trim(service_status)) IN ('realizada', 'realizado', 'terminado', 'terminada', 'ejecutado', 'ejecutada')
            AND operating_expenses > 0
        ")->fetchColumn();

        $projectedTotalCosts = $this->pdo->query("
            SELECT COALESCE(SUM(amount), 0)::numeric(12,2)::text 
            FROM qs_finance_entries WHERE entry_type = 'direct_cost'
        ")->fetchColumn();

        $this->assertEquals('1000.00', $sheetTotalCosts); // Confirmed services do not realize costs or profit yet.
        $this->assertSame($sheetTotalCosts, $projectedTotalCosts, "Cost totals must match");

        // 4. Operational Expenses
        $sheetTotalExpenses = $this->pdo->query("
            SELECT COALESCE(SUM(amount), 0)::numeric(12,2)::text 
            FROM qs_sheet_operational_expense_rows 
            WHERE lower(trim(expense_status)) IN ('pagado', 'aprobado', 'pagada', 'aprobada')
        ")->fetchColumn();

        $projectedTotalExpenses = $this->pdo->query("
            SELECT COALESCE(SUM(amount), 0)::numeric(12,2)::text 
            FROM qs_finance_entries WHERE entry_type = 'operational_expense'
        ")->fetchColumn();

        $this->assertEquals('3000.00', $sheetTotalExpenses);
        $this->assertSame($sheetTotalExpenses, $projectedTotalExpenses, "Expense totals must match");

        // 5. Refunds
        $sheetTotalRefunds = $this->pdo->query("
            SELECT COALESCE(SUM(amount), 0)::numeric(12,2)::text 
            FROM qs_sheet_operational_expense_rows 
            WHERE lower(trim(expense_status)) IN ('devolucion', 'devolución', 'reembolso')
        ")->fetchColumn();

        $projectedTotalRefunds = $this->pdo->query("
            SELECT COALESCE(SUM(amount), 0)::numeric(12,2)::text 
            FROM qs_finance_entries WHERE entry_type = 'refund'
        ")->fetchColumn();

        $this->assertEquals('5000.00', $sheetTotalRefunds);
        $this->assertSame($sheetTotalRefunds, $projectedTotalRefunds, "Refund totals must match");
    }

    public function testReconciliationIncludesFallbackSourcesAndExecutedStatuses(): void
    {
        $this->pdo->exec("INSERT INTO qs_sheet_import_runs (id, source_id, status) VALUES (1, 1, 'completed'), (2, 3, 'completed'), (3, 4, 'completed'), (4, 5, 'completed')");
        $this->pdo->exec("
            INSERT INTO qs_sheet_cash_tracking_rows
                (import_run_id, source_row, service_external_id, service_date, service_status, total_services)
            VALUES (1, 2, 'CASH-1', '2026-07-01', 'Ejecutado', 10000)
        ");
        $this->pdo->exec("
            INSERT INTO qs_sheet_bitacora_rows
                (import_run_id, source_row, qs_external_id, service_date, service_status, total_service)
            VALUES (2, 2, 'BIT-1', '2026-07-02', 'Terminado', 20000)
        ");
        $this->pdo->exec("
            INSERT INTO qs_sheet_agenda_month_rows
                (import_run_id, source_sheet, source_row, calendar_event_id, service_date, event_status, total_service)
            VALUES (3, 'Julio', 2, 'AGENDA-1', '2026-07-03', 'Realizada', 30000)
        ");
        $this->pdo->exec("
            INSERT INTO qs_sheet_workshop_rows
                (import_run_id, source_row, workshop_date, customer_name, payment_amount)
            VALUES (4, 2, '2026-07-04', 'Participante', 40000)
        ");
        $this->pdo->exec("INSERT INTO qs_sync_runs (id, status, mode) VALUES (99, 'completed', 'write')");

        $this->projection->rebuild(99);

        $repository = new PostgresFinanceReadRepository($this->pdo);
        $reconciliation = $repository->reconciliation(
            FinancePeriod::create('2026-07-01', '2026-07-31'),
            AccountingBasis::CASH_ESTIMATED,
        );

        self::assertSame(100000, $reconciliation['service_revenue']['sheet_total']);
        self::assertSame(100000, $reconciliation['service_revenue']['projected_total']);
        self::assertSame(0, $reconciliation['service_revenue']['difference']);
    }

    public function testCommittedDepositsUseOpenAgendaDepositsAsOfPeriodEnd(): void
    {
        $this->pdo->exec("INSERT INTO qs_sheet_import_runs (id, source_id, status) VALUES (1, 1, 'completed'), (2, 3, 'completed'), (3, 4, 'completed')");
        $this->pdo->exec("
            INSERT INTO qs_sheet_agenda_month_rows
                (import_run_id, source_sheet, source_row, calendar_event_id, service_date, deposit_date, event_status, deposit_amount, total_service)
            VALUES
                (3, 'Julio', 2, 'CAL-JUL', '2026-07-27', '2026-03-16', 'CONFIRMADO', 60000, 112530),
                (3, 'Agosto', 2, 'CAL-AUG', '2026-08-21', '2026-07-26', 'CREADO', 60000, 142000),
                (3, 'Septiembre', 2, 'CAL-SEP', '2026-09-25', '2026-04-14', 'CONFIRMADO', 100000, 218000),
                (3, 'Septiembre', 3, 'CAL-REAL', '2026-09-26', '2026-07-10', 'TERMINADO', 50000, 120000),
                (3, 'Noviembre', 2, 'CAL-LATE', '2026-11-06', '2026-08-01', 'CONFIRMADO', 90000, 241000),
                (3, 'Julio', 10, 'CAL-CANCELLED', '2026-07-30', '2026-07-27', 'CANCELADO - FORMULARIO', 36750, 73500),
                (3, 'Julio', 11, 'CAL-DONE', '2026-07-27', '2026-07-01', 'CONFIRMADO', 45000, 90000)
        ");
        $this->pdo->exec("
            INSERT INTO qs_sheet_bitacora_rows
                (import_run_id, source_row, qs_external_id, calendar_event_id, agenda_reference, service_date, service_status, deposit_amount, total_service)
            VALUES
                (2, 2, 'QS-JUL', 'CAL-JUL', 'Agenda: Julio!2', '2026-07-27', 'CONFIRMADO', 60000, 112530),
                (2, 3, 'QS-NO-AGENDA', 'CAL-OWN', null, '2026-07-20', 'CONFIRMADO', 25000, 80000),
                (2, 4, 'QS-DONE', 'CAL-DONE', 'Agenda: Julio!11', '2026-07-27', 'Realizado', 45000, 90000)
        ");
        $this->pdo->exec("
            INSERT INTO qs_sheet_cash_tracking_rows
                (import_run_id, source_row, service_external_id, service_date, service_status, deposit_amount, total_services)
            VALUES
                (1, 2, 'CASH-OPEN', '2026-07-22', 'Confirmado', 15000, 70000),
                (1, 3, 'CAL-OWN', '2026-07-20', 'Confirmado', 25000, 80000)
        ");

        $repository = new PostgresFinanceReadRepository($this->pdo);
        $metrics = $repository->dashboard(
            FinancePeriod::create('2026-07-01', '2026-07-31'),
            AccountingBasis::CASH_ESTIMATED,
        )->toArray();

        self::assertSame(260000, $metrics['committed_deposits']);

        $details = $repository->committedDepositsDetails(
            FinancePeriod::create('2026-07-01', '2026-07-31'),
            AccountingBasis::CASH_ESTIMATED,
        );
        self::assertSame(260000, $details['total']);
        self::assertSame(5, $details['count']);
        self::assertNotContains('CANCELADO - FORMULARIO', array_column($details['items'], 'status'));
        self::assertNotContains('Realizado', array_column($details['items'], 'status'));
    }

    public function testBitacoraFallbackPaymentUsesAgendaDepositDateWhenAvailable(): void
    {
        $this->pdo->exec("INSERT INTO qs_sheet_import_runs (id, source_id, status) VALUES (2, 3, 'completed'), (3, 4, 'completed')");
        $this->pdo->exec("
            INSERT INTO qs_sheet_agenda_month_rows
                (import_run_id, source_sheet, source_row, calendar_event_id, service_date, deposit_date, event_status, deposit_amount, total_service)
            VALUES
                (3, 'Agosto', 2, 'CAL-AUG', '2026-08-21', '2026-07-26', 'CREADO', 60000, 142000)
        ");
        $this->pdo->exec("
            INSERT INTO qs_sheet_bitacora_rows
                (import_run_id, source_row, qs_external_id, calendar_event_id, agenda_reference, service_date, service_status, payment_status, deposit_amount, total_service)
            VALUES
                (2, 985, 'QS-109', 'CAL-AUG', 'Agenda: Agosto!2', '2026-08-21', 'Pendiente', 'Parcial', 60000, 142000)
        ");
        $this->pdo->exec("INSERT INTO qs_sync_runs (id, status, mode) VALUES (99, 'completed', 'write')");

        $this->projection->rebuild(99);

        $entry = $this->pdo->query("
            SELECT occurred_on::text, amount::integer
            FROM qs_finance_entries
            WHERE entry_type = 'customer_payment'
              AND source_type = 'bitacora'
              AND source_row = 985
        ")->fetch(PDO::FETCH_ASSOC);
        self::assertSame('2026-07-26', $entry['occurred_on']);
        self::assertSame(60000, $entry['amount']);

        $repository = new PostgresFinanceReadRepository($this->pdo);
        $july = $repository->reconciliation(
            FinancePeriod::create('2026-07-01', '2026-07-31'),
            AccountingBasis::CASH_ESTIMATED,
        );
        $august = $repository->reconciliation(
            FinancePeriod::create('2026-08-01', '2026-08-31'),
            AccountingBasis::CASH_ESTIMATED,
        );

        self::assertSame(60000, $july['customer_payment']['projected_total']);
        self::assertSame(60000, $july['customer_payment']['sheet_total']);
        self::assertSame(0, $august['customer_payment']['projected_total']);
        self::assertSame(0, $august['customer_payment']['sheet_total']);
    }

    public function testFixedExpensesProjectMonthlyConfirmedEntries(): void
    {
        $this->pdo->exec("
            INSERT INTO qs_sheet_sources (id, spreadsheet_id, spreadsheet_title, sheet_name, purpose)
            VALUES (6, 'mock', 'mock', 'Gastos_Fijos', 'fixed_expenses')
        ");
        $this->pdo->exec("INSERT INTO qs_sheet_import_runs (id, source_id, status) VALUES (6, 6, 'completed')");

        // Solo la fila Confirmado + Mensual + monto > 0 debe proyectarse.
        $this->pdo->exec("
            INSERT INTO qs_sheet_fixed_expense_rows
                (import_run_id, source_row, concept, category, amount, expense_type, periodicity, expense_status, base_period)
            VALUES
                (6, 2, 'Arriendo estudio', 'Infraestructura', 350000, 'fijo', 'Mensual', 'Confirmado', '2026-06'),
                (6, 3, 'Suscripcion en evaluacion', 'Software', 20000, 'fijo', 'Mensual', 'Pendiente', '2026-06'),
                (6, 4, 'Patente anual', 'Legal', 90000, 'fijo', 'Anual', 'Confirmado', '2026-06'),
                (6, 5, 'Item sin monto', 'Otros', 0, 'fijo', 'Mensual', 'Confirmado', '2026-06')
        ");
        $this->pdo->exec("INSERT INTO qs_sync_runs (id, status, mode) VALUES (99, 'completed', 'write')");

        $this->projection->rebuild(99);

        $concepts = $this->pdo->query(
            "SELECT DISTINCT metadata->>'concept' FROM qs_finance_entries WHERE entry_type = 'fixed_expense'"
        )->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['Arriendo estudio'], $concepts);

        // Un entry por mes desde base_period hasta el mes actual + 24, todos
        // por el monto mensual. El horizonte depende de current_date, asi que
        // el conteo esperado se calcula con la misma aritmetica de Postgres.
        $summary = $this->pdo->query("
            SELECT COUNT(*) AS entries,
                   MIN(occurred_on)::text AS first_month,
                   MAX(occurred_on)::text AS last_month,
                   COUNT(*) FILTER (WHERE amount <> 350000) AS wrong_amounts
            FROM qs_finance_entries WHERE entry_type = 'fixed_expense'
        ")->fetch(PDO::FETCH_ASSOC);

        $expected = $this->pdo->query("
            SELECT ((extract(year FROM age(horizon, date '2026-06-01')) * 12
                   + extract(month FROM age(horizon, date '2026-06-01')))::int + 1) AS months,
                   horizon::date::text AS last_month
            FROM (SELECT date_trunc('month', current_date) + interval '24 months' AS horizon) h
        ")->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('2026-06-01', $summary['first_month']);
        $this->assertSame($expected['last_month'], $summary['last_month']);
        $this->assertSame((int) $expected['months'], (int) $summary['entries']);
        $this->assertSame(0, (int) $summary['wrong_amounts']);

        // El dashboard de un mes cualquiera del rango imputa exactamente una
        // mensualidad del gasto fijo.
        $repository = new PostgresFinanceReadRepository($this->pdo);
        $metrics = $repository->dashboard(
            FinancePeriod::create('2026-07-01', '2026-07-31'),
            AccountingBasis::CASH_ESTIMATED,
        )->toArray();
        $this->assertSame(350000, $metrics['fixed_expenses']);

        // Idempotencia: reconstruir no duplica mensualidades.
        $this->projection->rebuild(99);
        $count = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM qs_finance_entries WHERE entry_type = 'fixed_expense'"
        )->fetchColumn();
        $this->assertSame((int) $expected['months'], $count);
    }
}
