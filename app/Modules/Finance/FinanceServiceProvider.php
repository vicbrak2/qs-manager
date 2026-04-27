<?php

declare(strict_types=1);

namespace QS\Modules\Finance;

use function DI\autowire;

use QS\Core\Contracts\ModuleServiceProviderInterface;
use QS\Modules\Finance\Application\Command\RegisterPayment;
use QS\Modules\Finance\Application\CommandHandler\RegisterPaymentHandler;
use QS\Modules\Finance\Application\Query\GetExpenses;
use QS\Modules\Finance\Application\Query\GetMonthlyFinanceSummary;
use QS\Modules\Finance\Application\Query\GetPayments;
use QS\Modules\Finance\Application\Query\GetServiceMargin;
use QS\Modules\Finance\Application\QueryHandler\GetExpensesHandler;
use QS\Modules\Finance\Application\QueryHandler\GetMonthlyFinanceSummaryHandler;
use QS\Modules\Finance\Application\QueryHandler\GetPaymentsHandler;
use QS\Modules\Finance\Application\QueryHandler\GetServiceMarginHandler;
use QS\Modules\Finance\Domain\Repository\ExpenseRepository;
use QS\Modules\Finance\Domain\Repository\PaymentRepository;
use QS\Modules\Finance\Domain\Repository\ServiceCostRepository;
use QS\Modules\Finance\Domain\Service\MarginCalculator;
use QS\Modules\Finance\Domain\Service\MonthlySummaryBuilder;
use QS\Modules\Finance\Infrastructure\Persistence\ExpenseCptRepository;
use QS\Modules\Finance\Infrastructure\Persistence\PaymentCptRepository;
use QS\Modules\Finance\Infrastructure\Persistence\WpServiceCostRepository;
use QS\Modules\Finance\Interfaces\Rest\FinanceController;

final class FinanceServiceProvider implements ModuleServiceProviderInterface
{
    public static function definitions(): array
    {
        return [
            PaymentRepository::class => autowire(PaymentCptRepository::class),
            ExpenseRepository::class => autowire(ExpenseCptRepository::class),
            ServiceCostRepository::class => autowire(WpServiceCostRepository::class),
            MarginCalculator::class => autowire(),
            MonthlySummaryBuilder::class => autowire(),
            GetMonthlyFinanceSummaryHandler::class => autowire(),
            GetServiceMarginHandler::class => autowire(),
            GetPaymentsHandler::class => autowire(),
            RegisterPaymentHandler::class => autowire(),
            GetExpensesHandler::class => autowire(),
            FinanceController::class => autowire(),
        ];
    }

    public static function commandHandlers(): array
    {
        return [
            RegisterPayment::class => RegisterPaymentHandler::class,
        ];
    }

    public static function queryHandlers(): array
    {
        return [
            GetExpenses::class => GetExpensesHandler::class,
            GetMonthlyFinanceSummary::class => GetMonthlyFinanceSummaryHandler::class,
            GetPayments::class => GetPaymentsHandler::class,
            GetServiceMargin::class => GetServiceMarginHandler::class,
        ];
    }

    public static function hookables(): array
    {
        return [];
    }

    public static function activationHooks(): array
    {
        return [];
    }
}
