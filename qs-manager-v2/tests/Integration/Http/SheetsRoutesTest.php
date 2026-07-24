<?php

declare(strict_types=1);

namespace QSManager\Tests\Integration\Http;

use QSManager\Tests\Support\HttpTestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Extraido de HttpRoutesTest.php (Fase 5 del plan de migracion). Rutas de
 * sincronizacion de Sheets (/api/v1/sync/sheets/status) y el dashboard web
 * general ("/", assets estaticos) -- se agrupan aca por ser rutas de
 * infraestructura/plataforma, no de un modulo de dominio especifico.
 */
final class SheetsRoutesTest extends HttpTestCase
{
    public function testSheetsSyncStatusIsReadOnly(): void
    {
        $response = $this->json('GET', '/api/v1/sync/sheets/status');

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->payload($response);
        self::assertSame('read_only', $payload['mode']);
        self::assertFalse($payload['writes_to_sheets']);
    }

    public function testStaticAssetsAreServedCorrectly(): void
    {
        self::markTestSkipped('Requires local php -S server running; not suitable for integration test suite in CI/Docker');
        // bypassando Slim framework y definiendo el Content-Type adecuado.

        $cssHeaders = get_headers('http://localhost:8080/assets/css/tokens.css', true);
        self::assertStringContainsString('200 OK', $cssHeaders[0]);
        self::assertStringContainsString('text/css', $cssHeaders['Content-Type']);

        $jsHeaders = get_headers('http://localhost:8080/assets/js/app.js', true);
        self::assertStringContainsString('200 OK', $jsHeaders[0]);
        self::assertStringContainsString('application/javascript', $jsHeaders['Content-Type'] ?? $jsHeaders['content-type'] ?? '');

        $notFoundHeaders = get_headers('http://localhost:8080/assets/css/does-not-exist.css', true);
        self::assertStringContainsString('404 Not Found', $notFoundHeaders[0]);
    }

    public function testWebDashboardHtmlContent(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $response = $this->app->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));

        $html = (string) $response->getBody();

        // 1. Google Font Outfit
        self::assertStringContainsString('fonts.googleapis.com', $html);
        self::assertStringContainsString('Outfit', $html);

        // 2. Pagination controls
        self::assertStringContainsString('id="booking-per-page"', $html);
        self::assertStringContainsString('id="booking-prev-page"', $html);
        self::assertStringContainsString('Anterior', $html);
        self::assertStringContainsString('id="booking-next-page"', $html);
        self::assertStringContainsString('Siguiente', $html);
        self::assertStringContainsString('id="booking-page-indicator"', $html);

        // 3. Dropdowns for Service, Staff, and Status
        self::assertStringContainsString('id="booking-filter-service"', $html);
        self::assertStringContainsString('id="booking-filter-staff"', $html);
        self::assertStringContainsString('id="booking-filter-status"', $html);

        // 5. Verificamos que se carga el CSS externo
        self::assertMatchesRegularExpression('/href="\/assets\/css\/main\.css\?v=\d+"/', $html);

        // 6. Vanilla JS esta desacoplado en app.js
        self::assertStringContainsString('src="/assets/js/app.js"', $html);
        self::assertStringContainsString('data-booking-sort="scheduled_for"', $html);
        self::assertStringContainsString('aria-sort="ascending"', $html);
        self::assertStringContainsString('data-booking-view="upcoming"', $html);
        self::assertStringContainsString('Próximas reservas', $html);
        self::assertStringContainsString('data-booking-view="history"', $html);

        // Global read-only Sheets sync and unambiguous local refresh actions.
        self::assertStringContainsString('id="sync-all"', $html);
        self::assertStringContainsString('Sincronizar todo', $html);
        self::assertStringContainsString('id="refresh-services">Recargar', $html);
        self::assertStringContainsString('id="refresh-bookings">Recargar', $html);
    }
}
