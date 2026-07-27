<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Persistence\Postgres;

use PDO;
use QSManager\Domain\Finance\AccountingBasis;
use QSManager\Domain\Finance\FinancePeriod;
use QSManager\Domain\Finance\FinanceReadRepository;
use QSManager\Domain\Finance\FinancialMetrics;
use QSManager\Domain\Finance\Money;

final class PostgresFinanceReadRepository implements FinanceReadRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }
    public function dashboard(FinancePeriod $period, AccountingBasis $basis): FinancialMetrics
    {
        $statement = $this->connection->prepare(
            'SELECT entry_type, COALESCE(SUM(amount), 0) as total
             FROM qs_finance_entries
             WHERE occurred_on >= :from AND occurred_on <= :to
             GROUP BY entry_type'
        );

        $statement->execute([
            'from' => $period->from()->format('Y-m-d'),
            'to' => $period->to()->format('Y-m-d')
        ]);

        $totals = $statement->fetchAll(PDO::FETCH_KEY_PAIR);
        $realizedStatement = $this->connection->prepare("
            SELECT COALESCE(SUM(amount), 0)
            FROM qs_finance_entries
            WHERE entry_type = 'service_revenue'
              AND lower(trim(status)) IN ('realizada', 'realizado', 'terminado', 'terminada', 'ejecutado', 'ejecutada', 'completed')
              AND occurred_on BETWEEN :from AND :to
        ");
        $realizedStatement->execute([
            'from' => $period->from()->format('Y-m-d'),
            'to' => $period->to()->format('Y-m-d'),
        ]);

        $committedStatement = $this->connection->prepare("
            WITH agenda_open AS (
                SELECT COALESCE(SUM(a.deposit_amount), 0) AS amount
                FROM v_agenda_latest a
                WHERE a.deposit_amount > 0
                  AND COALESCE(a.deposit_date, a.service_date) <= :to
                  AND lower(trim(COALESCE(a.event_status, ''))) !~ '^(realizada|realizado|terminado|terminada|ejecutado|ejecutada|completed|anulado|anulada|cancelado|cancelada|no asiste)'
                  AND NOT EXISTS (SELECT 1 FROM v_bitacora_latest b
                      WHERE lower(trim(COALESCE(b.service_status, ''))) ~ '^(realizada|realizado|terminado|terminada|ejecutado|ejecutada|completed)'
                        AND (a.stable_external_id = b.stable_external_id OR (a.calendar_event_id IS NOT NULL AND b.calendar_event_id IS NOT NULL AND a.calendar_event_id = b.calendar_event_id) OR b.agenda_reference = 'Agenda: ' || a.source_sheet || '!' || a.source_row)
                  )
                  AND NOT EXISTS (SELECT 1 FROM v_cash_tracking_latest c
                      WHERE lower(trim(COALESCE(c.service_status, ''))) ~ '^(realizada|realizado|terminado|terminada|ejecutado|ejecutada|completed)'
                        AND (a.stable_external_id = c.stable_external_id OR (a.calendar_event_id IS NOT NULL AND c.stable_external_id = a.calendar_event_id))
                  )
            ), bitacora_open AS (
                SELECT COALESCE(SUM(b.deposit_amount), 0) AS amount
                FROM v_bitacora_latest b
                WHERE b.deposit_amount > 0
                  AND b.service_date <= :to
                  AND lower(trim(COALESCE(b.service_status, ''))) !~ '^(realizada|realizado|terminado|terminada|ejecutado|ejecutada|completed|anulado|anulada|cancelado|cancelada|no asiste)'
                  AND NOT EXISTS (
                      SELECT 1 FROM v_agenda_latest a
                      WHERE a.stable_external_id = b.stable_external_id
                         OR (a.calendar_event_id IS NOT NULL AND b.calendar_event_id IS NOT NULL AND a.calendar_event_id = b.calendar_event_id)
                         OR b.agenda_reference = 'Agenda: ' || a.source_sheet || '!' || a.source_row
                  )
            ), cash_open AS (
                SELECT COALESCE(SUM(c.deposit_amount), 0) AS amount
                FROM v_cash_tracking_latest c
                WHERE c.deposit_amount > 0
                  AND c.service_date <= :to
                  AND lower(trim(COALESCE(c.service_status, ''))) !~ '^(realizada|realizado|terminado|terminada|ejecutado|ejecutada|completed|anulado|anulada|cancelado|cancelada|no asiste)'
                  AND NOT EXISTS (
                      SELECT 1 FROM v_bitacora_latest b
                      WHERE b.stable_external_id = c.stable_external_id
                         OR (b.calendar_event_id IS NOT NULL AND c.stable_external_id = b.calendar_event_id)
                  )
                  AND NOT EXISTS (
                      SELECT 1 FROM v_agenda_latest a
                      WHERE a.stable_external_id = c.stable_external_id
                  )
            )
            SELECT (agenda_open.amount + bitacora_open.amount + cash_open.amount)
            FROM agenda_open, bitacora_open, cash_open
        ");
        $committedStatement->execute([
            'to' => $period->to()->format('Y-m-d'),
        ]);
        $receivableStatement = $this->connection->prepare("WITH service_totals AS (SELECT external_id, amount::bigint AS total_amount FROM qs_finance_entries WHERE entry_type = 'service_revenue' AND occurred_on BETWEEN :from AND :to AND lower(trim(COALESCE(status, ''))) !~ '^(anulado|anulada|cancelado|cancelada|no asiste)'), payment_totals AS (SELECT regexp_replace(external_id, '-pay$', '') AS base_external_id, SUM(amount)::bigint AS paid_amount FROM qs_finance_entries WHERE entry_type = 'customer_payment' GROUP BY regexp_replace(external_id, '-pay$', '')) SELECT COALESCE(SUM(GREATEST(s.total_amount - COALESCE(p.paid_amount, 0), 0)), 0)::bigint FROM service_totals s LEFT JOIN payment_totals p ON p.base_external_id = s.external_id");
        $receivableStatement->execute(['from' => $period->from()->format('Y-m-d'), 'to' => $period->to()->format('Y-m-d')]);

        return new FinancialMetrics(
            Money::fromInt((int) ($totals['service_revenue'] ?? 0)),
            Money::fromInt((int) ($totals['customer_payment'] ?? 0)),
            Money::fromInt((int) $committedStatement->fetchColumn()),
            Money::fromInt((int) $realizedStatement->fetchColumn()),
            Money::fromInt((int) ($totals['direct_cost'] ?? 0)),
            Money::fromInt((int) ($totals['operational_expense'] ?? 0)),
            Money::fromInt((int) ($totals['fixed_expense'] ?? 0)),
            Money::fromInt((int) ($totals['refund'] ?? 0)),
            Money::fromInt((int) $receivableStatement->fetchColumn()),
        );
    }

    public function reconciliation(FinancePeriod $period, AccountingBasis $basis): array
    {
        // 1. Service Revenue
        $revenueSheet = $this->connection->prepare("
            SELECT COALESCE(SUM(total_services), 0) 
            FROM v_cash_tracking_latest 
            WHERE lower(trim(service_status)) IN ('realizada', 'confirmado', 'realizado', 'confirmada', 'terminado', 'terminada', 'ejecutado', 'ejecutada')
            AND service_date >= :from AND service_date <= :to
        ");
        
        $revenueExcluded = $this->connection->prepare("
            SELECT COUNT(*) 
            FROM v_cash_tracking_latest 
            WHERE lower(trim(service_status)) NOT IN ('realizada', 'confirmado', 'realizado', 'confirmada', 'terminado', 'terminada', 'ejecutado', 'ejecutada')
            AND service_date >= :from AND service_date <= :to
        ");

        $revenueProjected = $this->connection->prepare("
            SELECT COALESCE(SUM(amount), 0) 
            FROM qs_finance_entries 
            WHERE entry_type = 'service_revenue'
            AND occurred_on >= :from AND occurred_on <= :to
        ");

        $params = [
            'from' => $period->from()->format('Y-m-d'),
            'to' => $period->to()->format('Y-m-d')
        ];

        // La proyección también genera ingresos desde Talleres (v_workshop_latest);
        // el lado Sheets debe sumarlos con el mismo filtro o la reconciliación
        // reporta un falso descuadre por cada taller pagado.
        $revenueWorkshopSheet = $this->connection->prepare("
            SELECT COALESCE(SUM(payment_amount), 0)
            FROM v_workshop_latest
            WHERE payment_amount > 0
            AND workshop_date >= :from AND workshop_date <= :to
        ");

        $revenueBitacoraSheet = $this->connection->prepare("
            SELECT COALESCE(SUM(b.total_service), 0)
            FROM v_bitacora_latest b
            WHERE lower(trim(b.service_status)) IN ('realizada', 'confirmado', 'realizado', 'confirmada', 'terminado', 'terminada', 'ejecutado', 'ejecutada')
            AND b.total_service > 0
            AND b.service_date >= :from AND b.service_date <= :to
            AND NOT EXISTS (
                SELECT 1 FROM v_cash_tracking_latest c
                WHERE c.stable_external_id = b.stable_external_id
                   OR (b.calendar_event_id IS NOT NULL AND c.stable_external_id = b.calendar_event_id)
            )
            AND NOT EXISTS (
                SELECT 1 FROM v_workshop_latest w
                WHERE w.workshop_date = b.service_date
                  AND lower(trim(w.customer_name)) = lower(trim(b.customer_name))
                  AND w.payment_amount > 0
            )
        ");

        $revenueAgendaSheet = $this->connection->prepare("
            SELECT COALESCE(SUM(a.total_service), 0)
            FROM v_agenda_latest a
            WHERE lower(trim(a.event_status)) IN ('realizada', 'confirmado', 'realizado', 'confirmada', 'terminado', 'terminada', 'ejecutado', 'ejecutada')
            AND a.total_service > 0
            AND a.service_date >= :from AND a.service_date <= :to
            AND NOT EXISTS (
                SELECT 1 FROM v_bitacora_latest b
                WHERE b.stable_external_id = a.stable_external_id
                   OR (b.calendar_event_id IS NOT NULL AND a.calendar_event_id IS NOT NULL AND b.calendar_event_id = a.calendar_event_id)
                   OR (
                       lower(trim(split_part(b.agenda_reference, '!', 1))) = lower('Agenda: ' || a.source_sheet)
                       AND b.agenda_reference ~ '![0-9]+\s*-\s*[0-9]+$'
                       AND a.source_row BETWEEN
                           ((regexp_match(b.agenda_reference, '!([0-9]+)\s*-\s*([0-9]+)$'))[1])::integer
                           AND ((regexp_match(b.agenda_reference, '!([0-9]+)\s*-\s*([0-9]+)$'))[2])::integer
                   )
            )
            AND NOT EXISTS (
                SELECT 1 FROM v_cash_tracking_latest c
                WHERE c.stable_external_id = a.stable_external_id
            )
            AND NOT EXISTS (
                SELECT 1 FROM v_workshop_latest w
                WHERE w.workshop_date = a.service_date
                  AND lower(trim(w.customer_name)) = lower(trim(a.customer_name))
                  AND w.payment_amount > 0
            )
        ");

        $revenueSheet->execute($params);
        $revenueExcluded->execute($params);
        $revenueProjected->execute($params);
        $revenueWorkshopSheet->execute($params);
        $revenueBitacoraSheet->execute($params);
        $revenueAgendaSheet->execute($params);

        $srSheet = (int) $revenueSheet->fetchColumn()
            + (int) $revenueBitacoraSheet->fetchColumn()
            + (int) $revenueAgendaSheet->fetchColumn()
            + (int) $revenueWorkshopSheet->fetchColumn();
        $srProjected = (int) $revenueProjected->fetchColumn();

        // 2. Customer Payments
        $paymentSheet = $this->connection->prepare("
            SELECT COALESCE(SUM(CASE WHEN lower(trim(payment_status)) = 'pagado' THEN total_services ELSE deposit_amount END), 0) 
            FROM v_cash_tracking_latest 
            WHERE lower(trim(service_status)) NOT IN ('anulado', 'cancelado', 'cancelada', 'anulada', 'no asiste')
            AND (deposit_amount > 0 OR lower(trim(payment_status)) = 'pagado')
            AND service_date >= :from AND service_date <= :to
        ");
        $paymentWorkshopSheet = $this->connection->prepare("
            SELECT COALESCE(SUM(payment_amount), 0)
            FROM v_workshop_latest
            WHERE payment_amount > 0
            AND COALESCE(payment_date, workshop_date) >= :from
            AND COALESCE(payment_date, workshop_date) <= :to
        ");
        $paymentBitacoraSheet = $this->connection->prepare("
            SELECT COALESCE(SUM(
                CASE WHEN lower(trim(b.payment_status)) = 'pagado' THEN b.total_service ELSE b.deposit_amount END
            ), 0)
            FROM v_bitacora_latest b
            LEFT JOIN v_agenda_latest a
              ON a.stable_external_id = b.stable_external_id
              OR (a.calendar_event_id IS NOT NULL AND b.calendar_event_id IS NOT NULL AND a.calendar_event_id = b.calendar_event_id)
              OR b.agenda_reference = 'Agenda: ' || a.source_sheet || '!' || a.source_row
            WHERE lower(trim(b.service_status)) NOT IN ('anulado', 'cancelado', 'cancelada', 'anulada', 'no asiste')
              AND (b.deposit_amount > 0 OR lower(trim(b.payment_status)) = 'pagado')
              AND COALESCE(a.deposit_date, b.service_date) >= :from
              AND COALESCE(a.deposit_date, b.service_date) <= :to
              AND NOT EXISTS (
                  SELECT 1 FROM v_cash_tracking_latest c
                  WHERE c.stable_external_id = b.stable_external_id
                     OR (b.calendar_event_id IS NOT NULL AND c.stable_external_id = b.calendar_event_id)
              )
              AND NOT EXISTS (
                  SELECT 1 FROM v_workshop_latest w
                  WHERE w.stable_external_id = b.stable_external_id
                     OR (
                         w.workshop_date = b.service_date
                         AND lower(trim(w.customer_name)) = lower(trim(b.customer_name))
                         AND w.payment_amount > 0
                     )
              )
        ");

        $paymentSheet->execute($params);
        $paymentWorkshopSheet->execute($params);
        $paymentBitacoraSheet->execute($params);
        $cpSheet = (int) $paymentSheet->fetchColumn()
            + (int) $paymentWorkshopSheet->fetchColumn()
            + (int) $paymentBitacoraSheet->fetchColumn();

        $paymentProjected = $this->connection->prepare("
            SELECT COALESCE(SUM(amount), 0) 
            FROM qs_finance_entries 
            WHERE entry_type = 'customer_payment'
            AND occurred_on >= :from AND occurred_on <= :to
        ");
        $paymentProjected->execute($params);
        $cpProjected = (int) $paymentProjected->fetchColumn();

        // 3. Direct Costs
        $costSheet = $this->connection->prepare("
            SELECT COALESCE(SUM(operating_expenses), 0) 
            FROM v_cash_tracking_latest 
            WHERE lower(trim(service_status)) IN ('realizada', 'realizado', 'terminado', 'terminada', 'ejecutado', 'ejecutada')
            AND operating_expenses > 0
            AND service_date >= :from AND service_date <= :to
        ");
        $costSheet->execute($params);
        $costBitacoraSheet = $this->connection->prepare("
            SELECT COALESCE(SUM(COALESCE(local_bitacora.costo_staff_clp, 0) + COALESCE(b.transfer_value, 0)), 0)
            FROM v_bitacora_latest b
            LEFT JOIN LATERAL (
                SELECT lb.costo_staff_clp
                FROM qs_bitacoras lb
                WHERE (
                    lb.booking_external_id IS NOT NULL
                    AND lb.booking_external_id IN (b.stable_external_id, b.calendar_event_id)
                )
                OR (
                    lb.fecha_servicio = b.service_date::text
                    AND lower(trim(lb.clienta_nombre)) = lower(trim(b.customer_name))
                )
                ORDER BY
                    CASE
                        WHEN lb.booking_external_id IS NOT NULL
                         AND lb.booking_external_id IN (b.stable_external_id, b.calendar_event_id) THEN 1
                        ELSE 2
                    END,
                    lb.updated_at DESC,
                    lb.id DESC
                LIMIT 1
            ) local_bitacora ON true
            WHERE lower(trim(b.service_status)) IN ('realizada', 'realizado', 'terminado', 'terminada', 'ejecutado', 'ejecutada')
              AND b.service_date >= :from AND b.service_date <= :to
              AND COALESCE(local_bitacora.costo_staff_clp, 0) + COALESCE(b.transfer_value, 0) > 0
              AND NOT EXISTS (
                  SELECT 1 FROM v_cash_tracking_latest c
                  WHERE lower(trim(c.service_status)) IN ('realizada', 'realizado', 'terminado', 'terminada', 'ejecutado', 'ejecutada')
                    AND c.operating_expenses > 0
                    AND (
                        c.stable_external_id = b.stable_external_id
                        OR c.stable_external_id = b.calendar_event_id
                    )
              )
        ");
        $costBitacoraSheet->execute($params);
        $dcSheet = (int) $costSheet->fetchColumn() + (int) $costBitacoraSheet->fetchColumn();

        $costProjected = $this->connection->prepare("
            SELECT COALESCE(SUM(amount), 0) 
            FROM qs_finance_entries 
            WHERE entry_type = 'direct_cost'
            AND occurred_on >= :from AND occurred_on <= :to
        ");
        $costProjected->execute($params);
        $dcProjected = (int) $costProjected->fetchColumn();

        // 4. Operational Expenses
        $expenseSheet = $this->connection->prepare("
            SELECT COALESCE(SUM(amount), 0) 
            FROM v_operational_expense_latest 
            WHERE lower(trim(expense_status)) IN ('pagado', 'aprobado', 'pagada', 'aprobada')
            AND expense_date >= :from AND expense_date <= :to
        ");
        $expenseSheet->execute($params);
        $oeSheet = (int) $expenseSheet->fetchColumn();

        $expenseProjected = $this->connection->prepare("
            SELECT COALESCE(SUM(amount), 0) 
            FROM qs_finance_entries 
            WHERE entry_type = 'operational_expense'
            AND occurred_on >= :from AND occurred_on <= :to
        ");
        $expenseProjected->execute($params);
        $oeProjected = (int) $expenseProjected->fetchColumn();

        $fixedExpenseSheet = $this->connection->prepare("
            SELECT COALESCE(SUM(r.amount), 0)
            FROM v_fixed_expense_latest r
            CROSS JOIN LATERAL generate_series(
                to_date(coalesce(r.base_period, to_char(current_date, 'YYYY-MM')) || '-01', 'YYYY-MM-DD'),
                date_trunc('month', current_date) + interval '24 months',
                interval '1 month'
            ) AS month(month_start)
            WHERE lower(trim(r.expense_status)) = 'confirmado'
              AND lower(trim(r.periodicity)) = 'mensual'
              AND r.amount > 0
              AND month.month_start::date BETWEEN :from AND :to
        ");
        $fixedExpenseSheet->execute($params);
        $feSheet = (int) $fixedExpenseSheet->fetchColumn();

        $fixedExpenseProjected = $this->connection->prepare("
            SELECT COALESCE(SUM(amount), 0)
            FROM qs_finance_entries
            WHERE entry_type = 'fixed_expense'
              AND occurred_on BETWEEN :from AND :to
        ");
        $fixedExpenseProjected->execute($params);
        $feProjected = (int) $fixedExpenseProjected->fetchColumn();

        $fixedExpensePending = (int) $this->connection->query("
            SELECT COUNT(*)
            FROM v_fixed_expense_latest
            WHERE lower(trim(expense_status)) = 'pendiente monto'
               OR (lower(trim(expense_status)) = 'confirmado' AND coalesce(amount, 0) <= 0)
        ")->fetchColumn();

        // 5. Refunds
        $refundSheet = $this->connection->prepare("
            SELECT COALESCE(SUM(amount), 0) 
            FROM v_operational_expense_latest 
            WHERE lower(trim(expense_status)) IN ('devolucion', 'devolución', 'reembolso')
            AND expense_date >= :from AND expense_date <= :to
        ");
        $refundSheet->execute($params);
        $rSheet = (int) $refundSheet->fetchColumn();

        $refundProjected = $this->connection->prepare("
            SELECT COALESCE(SUM(amount), 0) 
            FROM qs_finance_entries 
            WHERE entry_type = 'refund'
            AND occurred_on >= :from AND occurred_on <= :to
        ");
        $refundProjected->execute($params);
        $rProjected = (int) $refundProjected->fetchColumn();

        return [
            'service_revenue' => [
                'sheet_total' => $srSheet,
                'projected_total' => $srProjected,
                'difference' => $srSheet - $srProjected,
                'excluded_rows' => (int) $revenueExcluded->fetchColumn()
            ],
            'customer_payment' => [
                'sheet_total' => $cpSheet,
                'projected_total' => $cpProjected,
                'difference' => $cpSheet - $cpProjected,
                'excluded_rows' => 0
            ],
            'direct_cost' => [
                'sheet_total' => $dcSheet,
                'projected_total' => $dcProjected,
                'difference' => $dcSheet - $dcProjected,
                'excluded_rows' => 0
            ],
            'operational_expense' => [
                'sheet_total' => $oeSheet,
                'projected_total' => $oeProjected,
                'difference' => $oeSheet - $oeProjected,
                'excluded_rows' => 0
            ],
            'fixed_expense' => [
                'sheet_total' => $feSheet,
                'projected_total' => $feProjected,
                'difference' => $feSheet - $feProjected,
                'excluded_rows' => $fixedExpensePending,
            ],
            'refund' => [
                'sheet_total' => $rSheet,
                'projected_total' => $rProjected,
                'difference' => $rSheet - $rProjected,
                'excluded_rows' => 0
            ]
        ];
    }

    public function quality(FinancePeriod $period, AccountingBasis $basis): array
    {
        $recon = $this->reconciliation($period, $basis);
        $isReconciled = true;
        foreach ($recon as $cat) {
            if ($cat['difference'] !== 0) {
                $isReconciled = false;
                break;
            }
        }

        $missingIdsStmt = $this->connection->prepare("
            SELECT COUNT(*) FROM qs_finance_entries 
            WHERE occurred_on >= :from AND occurred_on <= :to 
            AND metadata->>'confidence' = 'low'
        ");
        $missingIdsStmt->execute([
            'from' => $period->from()->format('Y-m-d'),
            'to' => $period->to()->format('Y-m-d')
        ]);
        $missingIds = (int) $missingIdsStmt->fetchColumn();

        $estimatedCostsStmt = $this->connection->prepare("
            SELECT COUNT(*) FROM qs_finance_entries 
            WHERE entry_type = 'direct_cost' 
            AND occurred_on >= :from AND occurred_on <= :to 
            AND metadata->>'is_estimate' = 'true'
        ");
        $estimatedCostsStmt->execute([
            'from' => $period->from()->format('Y-m-d'),
            'to' => $period->to()->format('Y-m-d')
        ]);
        $estimatedCosts = (int) $estimatedCostsStmt->fetchColumn();

        $fixedExpenseStatus = $this->connection->query("
            SELECT
                COALESCE(SUM(amount) FILTER (
                    WHERE lower(trim(expense_status)) = 'confirmado' AND amount > 0
                ), 0)::bigint AS confirmed_monthly,
                COUNT(*) FILTER (
                    WHERE lower(trim(expense_status)) = 'pendiente monto'
                       OR (lower(trim(expense_status)) = 'confirmado' AND coalesce(amount, 0) <= 0)
                )::int AS pending_count
            FROM v_fixed_expense_latest
        ")->fetch(PDO::FETCH_ASSOC) ?: ['confirmed_monthly' => 0, 'pending_count' => 0];

        return [
            'is_reconciled' => $isReconciled,
            'missing_external_ids' => $missingIds,
            'estimated_cost_rows' => $estimatedCosts,
            'fixed_expenses_confirmed_monthly' => (int) $fixedExpenseStatus['confirmed_monthly'],
            'fixed_expenses_pending_count' => (int) $fixedExpenseStatus['pending_count'],
        ];
    }

    public function availableDetails(FinancePeriod $period, AccountingBasis $basis): array
    {
        $params = [
            'from' => $period->from()->format('Y-m-d'),
            'to' => $period->to()->format('Y-m-d'),
        ];

        $statement = $this->connection->prepare("
            WITH realized AS (
                SELECT
                    external_id AS base_external_id,
                    occurred_on,
                    amount,
                    metadata,
                    source_type,
                    source_sheet,
                    source_row
                FROM qs_finance_entries
                WHERE entry_type = 'service_revenue'
                  AND occurred_on BETWEEN :from AND :to
                  AND amount > 0
                  AND lower(trim(status)) IN ('realizada', 'realizado', 'terminado', 'terminada', 'ejecutado', 'ejecutada', 'completed')
            ), costs AS (
                SELECT
                    regexp_replace(external_id, '-cost$', '') AS base_external_id,
                    SUM(amount)::bigint AS amount
                FROM qs_finance_entries
                WHERE entry_type = 'direct_cost'
                  AND occurred_on BETWEEN :from AND :to
                GROUP BY regexp_replace(external_id, '-cost$', '')
            )
            SELECT
                r.base_external_id AS external_id,
                r.occurred_on,
                COALESCE(NULLIF(r.metadata->>'customer', ''), 'Cliente no informado') AS customer,
                COALESCE(NULLIF(r.metadata->>'service', ''), NULLIF(r.metadata->>'income_type', ''), 'Servicio no informado') AS service,
                r.amount::bigint AS realized_revenue,
                COALESCE(c.amount, 0)::bigint AS direct_cost,
                (r.amount - COALESCE(c.amount, 0))::bigint AS available_amount,
                r.source_type,
                r.source_sheet,
                r.source_row
            FROM realized r
            LEFT JOIN costs c ON c.base_external_id = r.base_external_id
            ORDER BY r.occurred_on DESC, customer, service
        ");
        $statement->execute($params);
        $services = $statement->fetchAll(PDO::FETCH_ASSOC);

        $deductionsStatement = $this->connection->prepare("
            SELECT entry_type, COALESCE(SUM(amount), 0)::bigint AS amount
            FROM qs_finance_entries
            WHERE entry_type IN ('operational_expense', 'fixed_expense', 'refund')
              AND occurred_on BETWEEN :from AND :to
            GROUP BY entry_type
        ");
        $deductionsStatement->execute($params);
        $deductions = array_fill_keys(['operational_expense', 'fixed_expense', 'refund'], 0);
        foreach ($deductionsStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $deductions[$row['entry_type']] = (int) $row['amount'];
        }

        $unmatchedCostStatement = $this->connection->prepare("
            SELECT COALESCE(SUM(c.amount), 0)::bigint
            FROM qs_finance_entries c
            WHERE c.entry_type = 'direct_cost'
              AND c.occurred_on BETWEEN :from AND :to
              AND NOT EXISTS (
                  SELECT 1 FROM qs_finance_entries r
                  WHERE r.entry_type = 'service_revenue'
                    AND r.occurred_on BETWEEN :from AND :to
                    AND lower(trim(r.status)) IN ('realizada', 'realizado', 'terminado', 'terminada', 'ejecutado', 'ejecutada', 'completed')
                    AND r.external_id = regexp_replace(c.external_id, '-cost$', '')
              )
        ");
        $unmatchedCostStatement->execute($params);
        $unmatchedCosts = (int) $unmatchedCostStatement->fetchColumn();

        $serviceAvailable = array_sum(array_map(static fn (array $row): int => (int) $row['available_amount'], $services));
        $netAvailable = max(
            0,
            $serviceAvailable - $unmatchedCosts - $deductions['operational_expense'] - $deductions['fixed_expense'] - $deductions['refund']
        );

        return [
            'services' => array_map(static fn (array $row): array => [
                ...$row,
                'realized_revenue' => (int) $row['realized_revenue'],
                'direct_cost' => (int) $row['direct_cost'],
                'available_amount' => (int) $row['available_amount'],
                'source_row' => $row['source_row'] !== null ? (int) $row['source_row'] : null,
            ], $services),
            'deductions' => [
                'unmatched_direct_costs' => $unmatchedCosts,
                'operating_expenses' => $deductions['operational_expense'],
                'fixed_expenses' => $deductions['fixed_expense'],
                'refunds' => $deductions['refund'],
            ],
            'totals' => [
                'realized_revenue' => array_sum(array_map(static fn (array $row): int => (int) $row['realized_revenue'], $services)),
                'matched_direct_costs' => array_sum(array_map(static fn (array $row): int => (int) $row['direct_cost'], $services)),
                'service_available' => $serviceAvailable,
                'net_available' => $netAvailable,
            ],
        ];
    }

    public function fixedExpenseDetails(FinancePeriod $period, AccountingBasis $basis): array
    {
        $params = [
            'from' => $period->from()->format('Y-m-d'),
            'to' => $period->to()->format('Y-m-d'),
        ];

        $statement = $this->connection->prepare("
            SELECT
                occurred_on,
                COALESCE(NULLIF(metadata->>'concept', ''), 'Gasto fijo sin concepto') AS concept,
                COALESCE(NULLIF(metadata->>'category', ''), 'Sin categoría') AS category,
                COALESCE(NULLIF(metadata->>'periodicity', ''), 'Sin periodicidad') AS periodicity,
                NULLIF(metadata->>'notes', '') AS notes,
                amount::bigint AS amount,
                status,
                source_sheet,
                source_row
            FROM qs_finance_entries
            WHERE entry_type = 'fixed_expense'
              AND occurred_on BETWEEN :from AND :to
              AND amount > 0
            ORDER BY amount DESC, concept
        ");
        $statement->execute($params);

        $items = array_map(static fn (array $row): array => [
            ...$row,
            'amount' => (int) $row['amount'],
            'source_row' => $row['source_row'] !== null ? (int) $row['source_row'] : null,
        ], $statement->fetchAll(PDO::FETCH_ASSOC));

        return [
            'items' => $items,
            'total' => array_sum(array_map(static fn (array $row): int => (int) $row['amount'], $items)),
            'count' => count($items),
        ];
    }

    public function contractedSalesDetails(FinancePeriod $period, AccountingBasis $basis): array
    {
        $params = [
            'from' => $period->from()->format('Y-m-d'),
            'to' => $period->to()->format('Y-m-d'),
        ];

        $statement = $this->connection->prepare("
            SELECT
                occurred_on,
                COALESCE(NULLIF(metadata->>'customer', ''), 'Cliente no informado') AS customer,
                COALESCE(NULLIF(metadata->>'service', ''), NULLIF(metadata->>'income_type', ''), 'Servicio no informado') AS service,
                status,
                amount::bigint AS amount,
                source_sheet,
                source_row
            FROM qs_finance_entries
            WHERE entry_type = 'service_revenue'
              AND occurred_on BETWEEN :from AND :to
              AND amount > 0
            ORDER BY occurred_on DESC, customer, service
        ");
        $statement->execute($params);

        $items = array_map(static fn (array $row): array => [
            ...$row,
            'amount' => (int) $row['amount'],
            'source_row' => $row['source_row'] !== null ? (int) $row['source_row'] : null,
        ], $statement->fetchAll(PDO::FETCH_ASSOC));

        return [
            'items' => $items,
            'total' => array_sum(array_map(static fn (array $row): int => (int) $row['amount'], $items)),
            'count' => count($items),
        ];
    }

    public function collectedRevenueDetails(FinancePeriod $period, AccountingBasis $basis): array
    {
        $params = [
            'from' => $period->from()->format('Y-m-d'),
            'to' => $period->to()->format('Y-m-d'),
        ];

        $statement = $this->connection->prepare("
            SELECT
                occurred_on,
                COALESCE(NULLIF(metadata->>'customer', ''), 'Cliente no informado') AS customer,
                COALESCE(NULLIF(metadata->>'service', ''), NULLIF(metadata->>'income_type', ''), 'Pago de cliente') AS service,
                COALESCE(NULLIF(metadata->>'payment_status', ''), 'Confirmado') AS payment_status,
                amount::bigint AS amount,
                source_sheet,
                source_row
            FROM qs_finance_entries
            WHERE entry_type = 'customer_payment'
              AND occurred_on BETWEEN :from AND :to
              AND amount > 0
            ORDER BY occurred_on DESC, customer, service
        ");
        $statement->execute($params);

        $items = array_map(static fn (array $row): array => [
            ...$row,
            'amount' => (int) $row['amount'],
            'source_row' => $row['source_row'] !== null ? (int) $row['source_row'] : null,
        ], $statement->fetchAll(PDO::FETCH_ASSOC));

        return [
            'items' => $items,
            'total' => array_sum(array_map(static fn (array $row): int => (int) $row['amount'], $items)),
            'count' => count($items),
        ];
    }

    public function committedDepositsDetails(FinancePeriod $period, AccountingBasis $basis): array
    {
        $params = [
            'to' => $period->to()->format('Y-m-d'),
        ];

        $statement = $this->connection->prepare("
            WITH agenda_open AS (
                SELECT
                    COALESCE(a.deposit_date, a.service_date) AS occurred_on,
                    a.customer_name AS customer,
                    a.service_name AS service,
                    'Agenda' AS source_type,
                    a.event_status AS status,
                    a.deposit_amount::bigint AS amount,
                    s.sheet_name AS source_sheet,
                    a.source_row
                FROM v_agenda_latest a
                JOIN qs_sheet_import_runs run ON run.id = a.import_run_id
                JOIN qs_sheet_sources s ON s.id = run.source_id
                WHERE a.deposit_amount > 0
                  AND COALESCE(a.deposit_date, a.service_date) <= :to
                  AND lower(trim(COALESCE(a.event_status, ''))) !~ '^(realizada|realizado|terminado|terminada|ejecutado|ejecutada|completed|anulado|anulada|cancelado|cancelada|no asiste)'
                  AND NOT EXISTS (SELECT 1 FROM v_bitacora_latest b
                      WHERE lower(trim(COALESCE(b.service_status, ''))) ~ '^(realizada|realizado|terminado|terminada|ejecutado|ejecutada|completed)'
                        AND (a.stable_external_id = b.stable_external_id OR (a.calendar_event_id IS NOT NULL AND b.calendar_event_id IS NOT NULL AND a.calendar_event_id = b.calendar_event_id) OR b.agenda_reference = 'Agenda: ' || a.source_sheet || '!' || a.source_row)
                  )
                  AND NOT EXISTS (SELECT 1 FROM v_cash_tracking_latest c
                      WHERE lower(trim(COALESCE(c.service_status, ''))) ~ '^(realizada|realizado|terminado|terminada|ejecutado|ejecutada|completed)'
                        AND (a.stable_external_id = c.stable_external_id OR (a.calendar_event_id IS NOT NULL AND c.stable_external_id = a.calendar_event_id))
                  )
            ), bitacora_open AS (
                SELECT
                    b.service_date AS occurred_on,
                    b.customer_name AS customer,
                    b.service_name AS service,
                    'Bitácora' AS source_type,
                    b.service_status AS status,
                    b.deposit_amount::bigint AS amount,
                    s.sheet_name AS source_sheet,
                    b.source_row
                FROM v_bitacora_latest b
                JOIN qs_sheet_import_runs run ON run.id = b.import_run_id
                JOIN qs_sheet_sources s ON s.id = run.source_id
                WHERE b.deposit_amount > 0
                  AND b.service_date <= :to
                  AND lower(trim(COALESCE(b.service_status, ''))) !~ '^(realizada|realizado|terminado|terminada|ejecutado|ejecutada|completed|anulado|anulada|cancelado|cancelada|no asiste)'
                  AND NOT EXISTS (
                      SELECT 1 FROM v_agenda_latest a
                      JOIN qs_sheet_import_runs arun ON arun.id = a.import_run_id
                      JOIN qs_sheet_sources asrc ON asrc.id = arun.source_id
                      WHERE a.stable_external_id = b.stable_external_id
                         OR (a.calendar_event_id IS NOT NULL AND b.calendar_event_id IS NOT NULL AND a.calendar_event_id = b.calendar_event_id)
                         OR b.agenda_reference = 'Agenda: ' || asrc.sheet_name || '!' || a.source_row
                  )
            ), cash_open AS (
                SELECT
                    c.service_date AS occurred_on,
                    c.customer_name AS customer,
                    c.service_names AS service,
                    'Caja' AS source_type,
                    c.service_status AS status,
                    c.deposit_amount::bigint AS amount,
                    s.sheet_name AS source_sheet,
                    c.source_row
                FROM v_cash_tracking_latest c
                JOIN qs_sheet_import_runs run ON run.id = c.import_run_id
                JOIN qs_sheet_sources s ON s.id = run.source_id
                WHERE c.deposit_amount > 0
                  AND c.service_date <= :to
                  AND lower(trim(COALESCE(c.service_status, ''))) !~ '^(realizada|realizado|terminado|terminada|ejecutado|ejecutada|completed|anulado|anulada|cancelado|cancelada|no asiste)'
                  AND NOT EXISTS (
                      SELECT 1 FROM v_bitacora_latest b
                      WHERE b.stable_external_id = c.stable_external_id
                         OR (b.calendar_event_id IS NOT NULL AND c.stable_external_id = b.calendar_event_id)
                  )
                  AND NOT EXISTS (
                      SELECT 1 FROM v_agenda_latest a
                      WHERE a.stable_external_id = c.stable_external_id
                  )
            )
            SELECT occurred_on, customer, service, source_type, status, amount, source_sheet, source_row
            FROM (
                SELECT * FROM agenda_open
                UNION ALL
                SELECT * FROM bitacora_open
                UNION ALL
                SELECT * FROM cash_open
            ) combined
            ORDER BY occurred_on DESC, customer, service
        ");
        $statement->execute($params);

        $items = array_map(static fn (array $row): array => [
            ...$row,
            'amount' => (int) $row['amount'],
            'source_row' => $row['source_row'] !== null ? (int) $row['source_row'] : null,
        ], $statement->fetchAll(PDO::FETCH_ASSOC));

        return [
            'items' => $items,
            'total' => array_sum(array_map(static fn (array $row): int => (int) $row['amount'], $items)),
            'count' => count($items),
        ];
    }

    public function releasedRevenueDetails(FinancePeriod $period, AccountingBasis $basis): array
    {
        $params = [
            'from' => $period->from()->format('Y-m-d'),
            'to' => $period->to()->format('Y-m-d'),
        ];

        $statement = $this->connection->prepare("
            SELECT
                p.occurred_on,
                COALESCE(NULLIF(r.metadata->>'customer', ''), 'Cliente no informado') AS customer,
                COALESCE(NULLIF(r.metadata->>'service', ''), NULLIF(r.metadata->>'income_type', ''), 'Servicio no informado') AS service,
                r.status AS service_status,
                p.amount::bigint AS amount,
                p.source_sheet,
                p.source_row
            FROM qs_finance_entries p
            JOIN qs_finance_entries r ON r.entry_type = 'service_revenue'
              AND r.external_id = regexp_replace(p.external_id, '-pay$', '')
            WHERE p.entry_type = 'customer_payment'
              AND p.occurred_on BETWEEN :from AND :to
              AND p.amount > 0
              AND lower(trim(r.status)) IN ('realizada', 'realizado', 'terminado', 'terminada', 'ejecutado', 'ejecutada', 'completed')
            ORDER BY p.occurred_on DESC, customer, service
        ");
        $statement->execute($params);

        $items = array_map(static fn (array $row): array => [
            ...$row,
            'amount' => (int) $row['amount'],
            'source_row' => $row['source_row'] !== null ? (int) $row['source_row'] : null,
        ], $statement->fetchAll(PDO::FETCH_ASSOC));

        return [
            'items' => $items,
            'total' => array_sum(array_map(static fn (array $row): int => (int) $row['amount'], $items)),
            'count' => count($items),
        ];
    }

    public function accountsReceivableDetails(FinancePeriod $period, AccountingBasis $basis): array
    {
        $params = [
            'from' => $period->from()->format('Y-m-d'),
            'to' => $period->to()->format('Y-m-d'),
        ];

        $statement = $this->connection->prepare("
            WITH service_totals AS (
                SELECT
                    external_id,
                    occurred_on,
                    COALESCE(NULLIF(metadata->>'customer', ''), 'Cliente no informado') AS customer,
                    COALESCE(NULLIF(metadata->>'service', ''), NULLIF(metadata->>'income_type', ''), 'Servicio no informado') AS service,
                    amount::bigint AS total_amount,
                    status,
                    source_sheet,
                    source_row
                FROM qs_finance_entries
                WHERE entry_type = 'service_revenue'
                  AND occurred_on BETWEEN :from AND :to
                  AND lower(trim(COALESCE(status, ''))) !~ '^(anulado|anulada|cancelado|cancelada|no asiste)'
            ), payment_totals AS (
                SELECT
                    regexp_replace(external_id, '-pay$', '') AS base_external_id,
                    SUM(amount)::bigint AS paid_amount
                FROM qs_finance_entries
                WHERE entry_type = 'customer_payment'
                GROUP BY regexp_replace(external_id, '-pay$', '')
            )
            SELECT
                s.occurred_on,
                s.customer,
                s.service,
                s.status,
                s.total_amount,
                COALESCE(p.paid_amount, 0) AS paid_amount,
                (s.total_amount - COALESCE(p.paid_amount, 0))::bigint AS pending_amount,
                s.source_sheet,
                s.source_row
            FROM service_totals s
            LEFT JOIN payment_totals p ON p.base_external_id = s.external_id
            WHERE (s.total_amount - COALESCE(p.paid_amount, 0)) > 0
            ORDER BY s.occurred_on DESC, customer, service
        ");
        $statement->execute($params);

        $items = array_map(static fn (array $row): array => [
            ...$row,
            'total_amount' => (int) $row['total_amount'],
            'paid_amount' => (int) $row['paid_amount'],
            'pending_amount' => (int) $row['pending_amount'],
            'source_row' => $row['source_row'] !== null ? (int) $row['source_row'] : null,
        ], $statement->fetchAll(PDO::FETCH_ASSOC));

        return [
            'items' => $items,
            'total' => array_sum(array_map(static fn (array $row): int => (int) $row['pending_amount'], $items)),
            'count' => count($items),
        ];
    }

    public function cashFlow(FinancePeriod $period, AccountingBasis $basis, string $granularity = 'month'): array
    {
        throw new \BadMethodCallException('Not implemented yet.');
    }

    public function expenses(FinancePeriod $period, ?string $status = null, ?string $category = null, int $page = 1, int $perPage = 50): array
    {
        throw new \BadMethodCallException('Not implemented yet.');
    }
}
