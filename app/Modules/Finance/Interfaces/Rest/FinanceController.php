<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Interfaces\Rest;

use InvalidArgumentException;
use QS\Core\Security\CapabilityChecker;
use QS\Core\Security\RequestSanitizer;
use QS\Modules\Finance\Application\Command\RegisterPayment;
use QS\Modules\Finance\Application\DTO\ExpenseDTO;
use QS\Modules\Finance\Application\DTO\MonthlySummaryDTO;
use QS\Modules\Finance\Application\DTO\PaymentDTO;
use QS\Modules\Finance\Application\DTO\ServiceMarginDTO;
use QS\Modules\Finance\Application\Query\GetExpenses;
use QS\Modules\Finance\Application\Query\GetMonthlyFinanceSummary;
use QS\Modules\Finance\Application\Query\GetPayments;
use QS\Modules\Finance\Application\Query\GetServiceMargin;
use QS\Modules\Finance\Domain\ValueObject\PaymentMethod;
use QS\Shared\Bus\CommandBus;
use QS\Shared\Bus\QueryBus;
use QS\Shared\DTO\RestResponse;

final class FinanceController
{
    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly CommandBus $commandBus,
        private readonly RequestSanitizer $requestSanitizer,
        private readonly CapabilityChecker $capabilityChecker
    ) {
    }

    public function monthlySummary(\WP_REST_Request $request): \WP_REST_Response
    {
        $summary = $this->queryBus->ask(
            new GetMonthlyFinanceSummary($this->normalizeMonth($request->get_param('month')))
        );
        assert($summary instanceof MonthlySummaryDTO);

        return $this->respond($summary->toArray());
    }

    public function serviceMargin(\WP_REST_Request $request): \WP_REST_Response
    {
        /** @var array<int, ServiceMarginDTO> $margins */
        $margins = $this->queryBus->ask(
            new GetServiceMargin($this->normalizeMonth($request->get_param('month')))
        );

        return $this->respond(array_map(static fn ($dto): array => $dto->toArray(), $margins));
    }

    public function payments(\WP_REST_Request $request): \WP_REST_Response
    {
        $month = $this->requestSanitizer->sanitizeNullableText($request->get_param('month'));
        /** @var array<int, PaymentDTO> $payments */
        $payments = $this->queryBus->ask(new GetPayments($month !== null ? $this->normalizeMonth($month) : null));

        return $this->respond(array_map(static fn ($dto): array => $dto->toArray(), $payments));
    }

    public function registerPayment(\WP_REST_Request $request): \WP_REST_Response
    {
        $payload = $this->payload($request);

        try {
            $payment = $this->commandBus->dispatch(
                new RegisterPayment(
                    $this->requestSanitizer->sanitizeInt($payload['reservation_id'] ?? null),
                    $this->requestSanitizer->sanitizeNullableText($payload['concept'] ?? null),
                    $this->requestSanitizer->sanitizeInt($payload['amount_clp'] ?? null, 0) ?? 0,
                    PaymentMethod::fromNullable($this->requestSanitizer->sanitizeNullableText($payload['method'] ?? null)),
                    $this->requestSanitizer->sanitizeText($payload['status'] ?? 'registered'),
                    $this->requestSanitizer->sanitizeNullableText($payload['paid_at'] ?? null) ?? gmdate('Y-m-d H:i:s'),
                    $this->normalizeMonth($payload['closing_month'] ?? null)
                )
            );
            assert($payment instanceof PaymentDTO);

            return $this->respond($payment->toArray(), 201);
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }
    }

    public function expenses(\WP_REST_Request $request): \WP_REST_Response
    {
        $month = $this->requestSanitizer->sanitizeNullableText($request->get_param('month'));
        /** @var array<int, ExpenseDTO> $expenses */
        $expenses = $this->queryBus->ask(new GetExpenses($month !== null ? $this->normalizeMonth($month) : null));

        return $this->respond(array_map(static fn ($dto): array => $dto->toArray(), $expenses));
    }

    public function canViewFinance(\WP_REST_Request $request): bool
    {
        return $this->capabilityChecker->currentUserCan('qs_view_finance');
    }

    public function canManageFinance(\WP_REST_Request $request): bool
    {
        return $this->canViewFinance($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(\WP_REST_Request $request): array
    {
        $payload = $this->requestSanitizer->sanitizeArray($request->get_json_params());

        if ($payload !== []) {
            return $payload;
        }

        $raw = $request->get_params();

        return $raw;
    }

    private function normalizeMonth(mixed $value): string
    {
        $month = $this->requestSanitizer->sanitizeNullableText($value);

        if ($month !== null && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            return $month;
        }

        return gmdate('Y-m');
    }

    /**
     * @param array<string, mixed>|array<int, array<string, mixed>> $data
     */
    private function respond(array $data, int $status = 200): \WP_REST_Response
    {
        return new \WP_REST_Response((new RestResponse('ok', $data))->toArray(), $status);
    }

    private function error(string $message, int $status): \WP_REST_Response
    {
        return new \WP_REST_Response((new RestResponse('error', ['message' => $message]))->toArray(), $status);
    }
}
