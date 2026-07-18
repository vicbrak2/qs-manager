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

    private function json(ResponseInterface $response, array $payload, int $status = 200): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($payload, JSON_PRETTY_PRINT));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
