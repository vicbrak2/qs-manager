<?php

declare(strict_types=1);

namespace QSManager\Tests\Integration\Http;

use QSManager\Tests\Support\HttpTestCase;

/**
 * Extraido de HttpRoutesTest.php (Fase 5 del plan de migracion).
 * Rutas /api/v1/finance.
 */
final class FinanceRoutesTest extends HttpTestCase
{
    public function testFinanceDashboardRouteAndValidation(): void
    {
        // Default basis is cash_estimated. Should work.
        $response = $this->json('GET', '/api/v1/finance/dashboard?from=2026-07-01&to=2026-07-31');
        self::assertSame(200, $response->getStatusCode());

        $payload = $this->payload($response);
        self::assertSame('cash_estimated', $payload['period']['basis']);
        self::assertSame('2026-07-01', $payload['period']['from']);
        self::assertSame('2026-07-31', $payload['period']['to']);
        self::assertArrayHasKey('metrics', $payload);
        self::assertArrayHasKey('reconciliation', $payload);
        self::assertArrayHasKey('quality', $payload);

        $detailsResponse = $this->json('GET', '/api/v1/finance/available-details?from=2026-07-01&to=2026-07-31');
        self::assertSame(200, $detailsResponse->getStatusCode());
        $details = $this->payload($detailsResponse);
        self::assertArrayHasKey('services', $details);
        self::assertArrayHasKey('deductions', $details);
        self::assertArrayHasKey('totals', $details);
        self::assertSame(
            $details['totals']['net_available'],
            $payload['metrics']['net_result'],
            'Available detail must close against the dashboard result.'
        );

        // Test accrual rejection
        $accrualResponse = $this->json('GET', '/api/v1/finance/dashboard?from=2026-07-01&to=2026-07-31&basis=accrual');
        self::assertSame(422, $accrualResponse->getStatusCode());

        // Test invalid dates
        $invalidDates = $this->json('GET', '/api/v1/finance/dashboard?from=not-a-date&to=2026-07-31');
        self::assertSame(422, $invalidDates->getStatusCode());

        // Test range too large
        $largeRange = $this->json('GET', '/api/v1/finance/dashboard?from=2020-01-01&to=2026-07-31');
        self::assertSame(422, $largeRange->getStatusCode());

        $invalidDetails = $this->json('GET', '/api/v1/finance/available-details?from=bad-date&to=2026-07-31');
        self::assertSame(422, $invalidDetails->getStatusCode());
    }
}
