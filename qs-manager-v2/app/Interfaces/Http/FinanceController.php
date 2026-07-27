<?php

declare(strict_types=1);

namespace QSManager\Interfaces\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use QSManager\Domain\Finance\AccountingBasis;
use QSManager\Domain\Finance\FinancePeriod;
use QSManager\Domain\Finance\FinanceReadRepository;
use Slim\App;

final class FinanceController
{
    public function __construct(private readonly FinanceReadRepository $repository)
    {
    }

    public function register(App $app): void
    {
        $app->get('/api/v1/finance/dashboard', [$this, 'dashboard']);
        $app->get('/api/v1/finance/available-details', [$this, 'availableDetails']);
        $app->get('/api/v1/finance/fixed-expense-details', [$this, 'fixedExpenseDetails']);
        $app->get('/api/v1/finance/contracted-sales-details', [$this, 'contractedSalesDetails']);
        $app->get('/api/v1/finance/collected-revenue-details', [$this, 'collectedRevenueDetails']);
        $app->get('/api/v1/finance/committed-deposits-details', [$this, 'committedDepositsDetails']);
        $app->get('/api/v1/finance/released-revenue-details', [$this, 'releasedRevenueDetails']);
        $app->get('/api/v1/finance/accounts-receivable-details', [$this, 'accountsReceivableDetails']);
    }

    public function dashboard(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        
        $tz = new \DateTimeZone('America/Santiago');
        $from = $params['from'] ?? (new \DateTimeImmutable('now', $tz))->format('Y-m-01');
        $to = $params['to'] ?? (new \DateTimeImmutable('now', $tz))->format('Y-m-t');
        $basisRaw = $params['basis'] ?? 'cash_estimated';

        if ($basisRaw === 'accrual') {
            return $this->json($response, [
                'error' => 'Basis accrual is not currently supported.',
                'details' => 'Since there are no real payment dates yet, only cash_estimated is allowed.'
            ], 422);
        }

        if ($basisRaw !== 'cash_estimated') {
            return $this->json($response, [
                'error' => 'Invalid basis.',
                'details' => 'basis must be cash_estimated'
            ], 422);
        }

        try {
            $period = FinancePeriod::create($from, $to);
        } catch (\InvalidArgumentException $e) {
            return $this->json($response, [
                'error' => 'Invalid date format or range.',
                'details' => $e->getMessage()
            ], 422);
        }

        // Limit the range to 24 months
        $diff = $period->from()->diff($period->to());
        $months = $diff->y * 12 + $diff->m;
        if ($months > 24) {
            return $this->json($response, [
                'error' => 'Date range too large.',
                'details' => 'The maximum allowed date range is 24 months.'
            ], 422);
        }

        $basis = AccountingBasis::CASH_ESTIMATED;

        $metrics = $this->repository->dashboard($period, $basis);
        $reconciliation = $this->repository->reconciliation($period, $basis);
        $quality = $this->repository->quality($period, $basis);

        $payload = [
            'period' => [
                'from' => $period->from()->format('Y-m-d'),
                'to' => $period->to()->format('Y-m-d'),
                'basis' => $basis->value,
                'timezone' => 'America/Santiago'
            ],
            'metrics' => $metrics->toArray(),
            'reconciliation' => $reconciliation,
            'quality' => $quality
        ];

        return $this->json($response, $payload);
    }

    public function availableDetails(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $validation = $this->validatedPeriod($request);
        if ($validation instanceof ResponseInterface) {
            return $validation;
        }

        [$period, $basis] = $validation;

        return $this->json($response, [
            'period' => [
                'from' => $period->from()->format('Y-m-d'),
                'to' => $period->to()->format('Y-m-d'),
                'basis' => $basis->value,
            ],
            ...$this->repository->availableDetails($period, $basis),
        ]);
    }

    public function fixedExpenseDetails(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $validation = $this->validatedPeriod($request);
        if ($validation instanceof ResponseInterface) {
            return $validation;
        }

        [$period, $basis] = $validation;

        return $this->json($response, [
            'period' => [
                'from' => $period->from()->format('Y-m-d'),
                'to' => $period->to()->format('Y-m-d'),
                'basis' => $basis->value,
            ],
            ...$this->repository->fixedExpenseDetails($period, $basis),
        ]);
    }

    public function contractedSalesDetails(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $validation = $this->validatedPeriod($request);
        if ($validation instanceof ResponseInterface) {
            return $validation;
        }

        [$period, $basis] = $validation;

        return $this->json($response, [
            'period' => [
                'from' => $period->from()->format('Y-m-d'),
                'to' => $period->to()->format('Y-m-d'),
                'basis' => $basis->value,
            ],
            ...$this->repository->contractedSalesDetails($period, $basis),
        ]);
    }

    public function collectedRevenueDetails(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $validation = $this->validatedPeriod($request);
        if ($validation instanceof ResponseInterface) {
            return $validation;
        }

        [$period, $basis] = $validation;

        return $this->json($response, [
            'period' => [
                'from' => $period->from()->format('Y-m-d'),
                'to' => $period->to()->format('Y-m-d'),
                'basis' => $basis->value,
            ],
            ...$this->repository->collectedRevenueDetails($period, $basis),
        ]);
    }

    public function committedDepositsDetails(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $validation = $this->validatedPeriod($request);
        if ($validation instanceof ResponseInterface) {
            return $validation;
        }

        [$period, $basis] = $validation;

        return $this->json($response, [
            'period' => [
                'from' => $period->from()->format('Y-m-d'),
                'to' => $period->to()->format('Y-m-d'),
                'basis' => $basis->value,
            ],
            ...$this->repository->committedDepositsDetails($period, $basis),
        ]);
    }

    public function releasedRevenueDetails(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $validation = $this->validatedPeriod($request);
        if ($validation instanceof ResponseInterface) {
            return $validation;
        }

        [$period, $basis] = $validation;

        return $this->json($response, [
            'period' => [
                'from' => $period->from()->format('Y-m-d'),
                'to' => $period->to()->format('Y-m-d'),
                'basis' => $basis->value,
            ],
            ...$this->repository->releasedRevenueDetails($period, $basis),
        ]);
    }

    public function accountsReceivableDetails(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $validation = $this->validatedPeriod($request);
        if ($validation instanceof ResponseInterface) {
            return $validation;
        }

        [$period, $basis] = $validation;

        return $this->json($response, [
            'period' => [
                'from' => $period->from()->format('Y-m-d'),
                'to' => $period->to()->format('Y-m-d'),
                'basis' => $basis->value,
            ],
            ...$this->repository->accountsReceivableDetails($period, $basis),
        ]);
    }

    /** @return array{FinancePeriod, AccountingBasis}|ResponseInterface */
    private function validatedPeriod(ServerRequestInterface $request): array|ResponseInterface
    {
        $params = $request->getQueryParams();
        $tz = new \DateTimeZone('America/Santiago');
        $from = $params['from'] ?? (new \DateTimeImmutable('now', $tz))->format('Y-m-01');
        $to = $params['to'] ?? (new \DateTimeImmutable('now', $tz))->format('Y-m-t');
        $basisRaw = $params['basis'] ?? 'cash_estimated';

        if ($basisRaw !== 'cash_estimated') {
            return $this->json(new \Slim\Psr7\Response(), [
                'error' => 'Invalid basis.',
                'details' => 'basis must be cash_estimated',
            ], 422);
        }

        try {
            $period = FinancePeriod::create($from, $to);
        } catch (\InvalidArgumentException $e) {
            return $this->json(new \Slim\Psr7\Response(), [
                'error' => 'Invalid date format or range.',
                'details' => $e->getMessage(),
            ], 422);
        }

        $diff = $period->from()->diff($period->to());
        if ($diff->y * 12 + $diff->m > 24) {
            return $this->json(new \Slim\Psr7\Response(), [
                'error' => 'Date range too large.',
                'details' => 'The maximum allowed date range is 24 months.',
            ], 422);
        }

        return [$period, AccountingBasis::CASH_ESTIMATED];
    }

    private function json(ResponseInterface $response, array $payload, int $status = 200): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($payload, JSON_PRETTY_PRINT));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
