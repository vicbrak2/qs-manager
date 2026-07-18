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

        return new FinancialMetrics(
            Money::fromInt((int) ($totals['service_revenue'] ?? 0)),
            Money::fromInt((int) ($totals['customer_payment'] ?? 0)),
            Money::fromInt((int) ($totals['direct_cost'] ?? 0)),
            Money::fromInt((int) ($totals['operational_expense'] ?? 0)),
            Money::fromInt((int) ($totals['refund'] ?? 0)),
        );
    }

    public function reconciliation(FinancePeriod $period, AccountingBasis $basis): array
    {
        // 1. Service Revenue
        $revenueSheet = $this->connection->prepare("
            SELECT COALESCE(SUM(total_services), 0) 
            FROM v_cash_tracking_latest 
            WHERE lower(trim(service_status)) IN ('realizada', 'confirmado', 'realizado', 'confirmada')
            AND service_date >= :from AND service_date <= :to
        ");
        
        $revenueExcluded = $this->connection->prepare("
            SELECT COUNT(*) 
            FROM v_cash_tracking_latest 
            WHERE lower(trim(service_status)) NOT IN ('realizada', 'confirmado', 'realizado', 'confirmada')
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

        $revenueSheet->execute($params);
        $revenueExcluded->execute($params);
        $revenueProjected->execute($params);
        $revenueWorkshopSheet->execute($params);

        $srSheet = (int) $revenueSheet->fetchColumn() + (int) $revenueWorkshopSheet->fetchColumn();
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

        $paymentSheet->execute($params);
        $paymentWorkshopSheet->execute($params);
        $cpSheet = (int) $paymentSheet->fetchColumn() + (int) $paymentWorkshopSheet->fetchColumn();

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
            WHERE lower(trim(service_status)) IN ('realizada', 'confirmado', 'realizado', 'confirmada')
            AND operating_expenses > 0
            AND service_date >= :from AND service_date <= :to
        ");
        $costSheet->execute($params);
        $dcSheet = (int) $costSheet->fetchColumn();

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

        return [
            'is_reconciled' => $isReconciled,
            'missing_external_ids' => $missingIds,
            'estimated_cost_rows' => $estimatedCosts
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
