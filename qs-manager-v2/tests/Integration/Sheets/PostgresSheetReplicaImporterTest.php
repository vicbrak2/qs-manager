<?php

declare(strict_types=1);

namespace QSManager\Tests\Integration\Sheets;

use PDO;
use PHPUnit\Framework\TestCase;
use QSManager\Application\Sheets\SheetCsvReader;
use QSManager\Infrastructure\Database\ConnectionFactory;
use QSManager\Infrastructure\Sheets\BookingProjectionWriter;
use QSManager\Infrastructure\Sheets\Importers\BitacoraImporter;
use QSManager\Infrastructure\Sheets\Importers\ExpensesImporter;
use QSManager\Infrastructure\Sheets\PostgresSheetReplicaImporter;
use QSManager\Infrastructure\Sheets\SheetRowMapper;

final class PostgresSheetReplicaImporterTest extends TestCase
{
    private PDO $connection;

    protected function setUp(): void
    {
        $this->connection = ConnectionFactory::fromEnvironment();
        $this->connection->exec('TRUNCATE TABLE qs_sync_runs, qs_sheet_import_runs, qs_sheet_service_catalog_rows, qs_services CASCADE');
        $this->connection->exec('TRUNCATE TABLE qs_sheet_sources CASCADE');
        
        $this->connection->exec("
            INSERT INTO qs_sheet_sources (spreadsheet_title, sheet_name, purpose, spreadsheet_id, sheet_gid) 
            VALUES ('Doc', 'Servicios_Master', 'services_master', 'test-doc', 1)
        ");
    }

    public function testLockPreventsConcurrentExecution(): void
    {
        $mockReader = $this->createMock(SheetCsvReader::class);
        
        $importer = new PostgresSheetReplicaImporter($this->connection, $mockReader);

        // Simulate a lock acquired in another session
        $pdo2 = ConnectionFactory::fromEnvironment();
        $pdo2->exec('SELECT pg_advisory_lock(987654321)');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Sync is already running concurrently.');

        try {
            $importer->importAll();
        } finally {
            $pdo2->exec('SELECT pg_advisory_unlock(987654321)');
        }
    }
    
    public function testIdempotencyOnImportingMasterServices(): void
    {
        $mockReader = $this->createMock(SheetCsvReader::class);
        $mockReader->method('read')->willReturn([
            ['service_id', 'nombre_canonico', 'duracion', 'requiere_evaluacion', 'categoria', 'precio_venta', 'servicio', 'valor', 'fecha', 'nombre', 'pago', 'encargada', 'clienta', 'dia', 'hora', 'cantidad', 'telefono', 'direccion', 'comuna', 'fecha prueba', 'hora prueba', 'estado prueba', 'traslado', 'abono', 'fecha abono', 'valor servicio', 'total servicio', 'total por pagar', 'accion', 'estado evento', 'id evento', 'estado pago', 'precio venta', 'activo', 'pago mua', 'pago estilista', 'prueba mua', 'prueba estilista', 'materiales', 'traslado / logistica', 'valor traslado', 'otros costos', 'costo total', 'utilidad', 'margen %', 'estado', 'observaciones', 'id servicios', 'servicio(s)', 'abono reserva', 'total servicios', 'saldo por cobrar', 'gastos operativos', 'estado servicios', 'concepto', 'monto gasto ($)', 'seleccionar servicio', 'id', 'id contrato', 'tipo de servicio', 'fecha gasto', 'estado gasto', 'tipo', 'forma de pago', 'id calendar', 'referencia agenda', 'hito', 'grupo caja', 'estado contrato', 'reserva contrato', 'periodicidad', 'monto clp', 'notas', 'periodo base'],
            ['S-001', 'Corte Hombre', '30', 'no', 'Peluquería', '15000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '']
        ]);
        
        $importer = new PostgresSheetReplicaImporter($this->connection, $mockReader);
        
        // First execution
        $result1 = $importer->importAll();
        $this->assertSame('completed', $result1->toArray()['sources']['Servicios_Master']['status']);
        $this->assertNotNull(
            $this->connection->query("SELECT last_synced_at FROM qs_sheet_sources WHERE sheet_name = 'Servicios_Master'")->fetchColumn()
        );
        
        $count1 = (int) $this->connection->query('SELECT COUNT(*) FROM qs_services')->fetchColumn();
        $this->assertSame(1, $count1);

        $this->connection->exec(
            "INSERT INTO qs_services (sheet_external_id, name, quantity, active)
             VALUES ('S-001', 'Corte Hombre antiguo', 1, true)"
        );
        $this->connection->exec(
            "INSERT INTO qs_services (sheet_external_id, name, quantity, active)
             VALUES ('S-STALE', 'Servicio retirado', 1, false)"
        );
        $this->assertSame(3, (int) $this->connection->query('SELECT COUNT(*) FROM qs_services')->fetchColumn());
        
        // Second execution (Idempotency)
        $result2 = $importer->importAll();
        $this->assertSame('completed', $result2->toArray()['sources']['Servicios_Master']['status']);
        
        $qsServicesCount = (int) $this->connection->query('SELECT COUNT(*) FROM qs_services')->fetchColumn();
        $this->assertSame(1, $qsServicesCount);
    }

    public function testScheduledForUsesSantiagoTimezoneAcrossDstSeasons(): void
    {
        // scheduledFor() vive en SheetRowMapper desde Fase 4 (es logica pura,
        // sin PDO) -- ya no en PostgresSheetReplicaImporter.
        $mapper = new SheetRowMapper();

        // Verano chileno (diciembre): offset -03. Un offset fijo -04 producía 13:00Z.
        $summer = $mapper->scheduledFor(['fecha' => '29/12/2026', 'hora' => '09:00']);
        $this->assertSame(
            '2026-12-29 12:00:00',
            (new \DateTimeImmutable($summer))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')
        );

        // Invierno chileno (julio): offset -04.
        $winter = $mapper->scheduledFor(['fecha' => '15/07/2026', 'hora' => '09:00']);
        $this->assertSame(
            '2026-07-15 13:00:00',
            (new \DateTimeImmutable($winter))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')
        );
    }

    public function testAgendaLegacyBrideServiceResolvesToCanonicalMasterService(): void
    {
        $this->connection->exec(
            "INSERT INTO qs_services (sheet_external_id, name, quantity, active, source_sheet)
             VALUES ('S-NOVIA-FIESTA-MP', 'Novia Fiesta Maquillaje Peinado (prueba presencial)', 1, true, 'Servicios_Master')"
        );

        // serviceIdByName() vive en BookingProjectionWriter desde Fase 4.
        $writer = new BookingProjectionWriter($this->connection, new SheetRowMapper());

        $serviceId = $writer->serviceIdByName('Novia Fiesta (prueba presencial)');

        self::assertSame(
            (int) $this->connection->query("SELECT id FROM qs_services WHERE sheet_external_id = 'S-NOVIA-FIESTA-MP'")->fetchColumn(),
            $serviceId
        );
    }

    public function testAgendaLegacyBrideServiceIsProjectedAsBooking(): void
    {
        $this->connection->exec(
            "INSERT INTO qs_services (sheet_external_id, name, quantity, active, source_sheet)
             VALUES ('S-NOVIA-FIESTA-MP', 'Novia Fiesta Maquillaje Peinado (prueba presencial)', 1, true, 'Servicios_Master')"
        );

        // upsertAgendaBookingProjection() vive en BookingProjectionWriter desde Fase 4.
        $writer = new BookingProjectionWriter($this->connection, new SheetRowMapper());
        $writer->upsertAgendaBookingProjection('Septiembre', 3, [
            'fecha' => '12/9/2026',
            'hora' => '9:00',
            'servicio' => 'Novia Fiesta (prueba presencial)',
            'clienta' => 'Camila Soto',
            'telefono' => '56946135598',
            'direccion' => 'Pendiente',
            'comuna' => 'Buin',
            'traslado' => '$19.500',
            'abono' => '$60.000',
            'valor servicio' => '$225.000',
            'total servicio' => '$244.500',
            'total por pagar' => '$184.500',
            'estado evento' => 'CONFIRMADO',
            'id evento' => 'h7gsraa6ktnbqggdo6emibm9cg@google.com',
        ]);

        $booking = $this->connection->query(
            "SELECT customer_name, customer_phone, scheduled_for::date AS scheduled_date, service_id, total_service, balance_due
             FROM qs_bookings WHERE source_sheet = 'Septiembre' AND source_row = 3"
        )->fetch(PDO::FETCH_ASSOC);

        self::assertSame('Camila Soto', $booking['customer_name']);
        self::assertSame('56946135598', $booking['customer_phone']);
        self::assertSame('2026-09-12', $booking['scheduled_date']);
        self::assertSame('244500.00', $booking['total_service']);
        self::assertSame('184500.00', $booking['balance_due']);
        self::assertNotNull($booking['service_id']);
    }

    public function testAccountingAggregateAgendaRangeIsNotProjectedAsBooking(): void
    {
        // shouldProjectBitacoraBooking() vive en SheetRowMapper desde Fase 4.
        $mapper = new SheetRowMapper();

        self::assertFalse($mapper->shouldProjectBitacoraBooking([
            'referencia agenda' => 'Agenda: Julio!8-9',
        ]));
        self::assertTrue($mapper->shouldProjectBitacoraBooking([
            'referencia agenda' => 'Agenda: Julio!8',
        ]));
        self::assertTrue($mapper->shouldProjectBitacoraBooking([
            'referencia agenda' => null,
        ]));
    }

    public function testCashTrackingStopsBeforeClosedServicesSummary(): void
    {
        // isCashTrackingFooterRow() vive en SheetRowMapper desde Fase 4.
        $mapper = new SheetRowMapper();

        self::assertTrue($mapper->isCashTrackingFooterRow(['Ganancia por servicios cerrados']));
        self::assertFalse($mapper->isCashTrackingFooterRow(['QS-106', '17/07/2026']));
    }

    public function testBitacoraPaymentMethodIsNormalizedOnImport(): void
    {
        $runId = (int) $this->connection->query(
            "INSERT INTO qs_sheet_import_runs (status) VALUES ('running') RETURNING id"
        )->fetchColumn();

        $mapper = new SheetRowMapper();
        $importer = new BitacoraImporter(
            $this->connection,
            $mapper,
            new BookingProjectionWriter($this->connection, $mapper)
        );

        // "referencia agenda" con rango (Julio!8-9) evita la proyeccion a
        // qs_bookings -- el test solo mira la fila replica.
        $imported = $importer->import($runId, 'Bitácora QS — Servicios', [
            ['id', 'fecha', 'servicio', 'forma de pago', 'referencia agenda'],
            ['QS-1', '10/07/2026', 'Corte', ' TRANSFERENCIA ', 'Agenda: Julio!8-9'],
            ['QS-2', '11/07/2026', 'Corte', 'Débito', 'Agenda: Julio!8-9'],
            ['QS-3', '12/07/2026', 'Corte', '', 'Agenda: Julio!8-9'],
        ]);

        self::assertSame(3, $imported);

        $methods = $this->connection->query(
            'SELECT qs_external_id, payment_method FROM qs_sheet_bitacora_rows ORDER BY source_row'
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        self::assertSame(
            ['QS-1' => 'transferencia', 'QS-2' => 'otro', 'QS-3' => 'otro'],
            $methods
        );
    }

    public function testFixedExpensesSheetIsImportedIntoReplicaTable(): void
    {
        $runId = (int) $this->connection->query(
            "INSERT INTO qs_sheet_import_runs (status) VALUES ('running') RETURNING id"
        )->fetchColumn();

        $importer = new ExpensesImporter($this->connection, new SheetRowMapper());
        $imported = $importer->importFixed($runId, 'Gastos_Fijos', [
            ['concepto', 'categoria', 'monto clp', 'tipo', 'periodicidad', 'estado', 'notas', 'periodo base'],
            ['Arriendo estudio', 'Infraestructura', '$350.000', 'fijo', 'Mensual', 'Confirmado', 'nota x', '2026-06'],
            ['', '', '', '', '', '', '', ''],
        ]);

        self::assertSame(1, $imported);

        $row = $this->connection->query(
            'SELECT concept, category, amount, periodicity, expense_status, base_period
             FROM qs_sheet_fixed_expense_rows'
        )->fetch(PDO::FETCH_ASSOC);

        self::assertSame(
            [
                'concept' => 'Arriendo estudio',
                'category' => 'Infraestructura',
                'amount' => '350000.00',
                'periodicity' => 'Mensual',
                'expense_status' => 'Confirmado',
                'base_period' => '2026-06',
            ],
            $row
        );
    }

    public function testExecutedSheetStatusMapsToCompletedBooking(): void
    {
        // bookingStatus() vive en SheetRowMapper desde Fase 4.
        $mapper = new SheetRowMapper();

        self::assertSame('completed', $mapper->bookingStatus('Ejecutado'));
        self::assertSame('completed', $mapper->bookingStatus('Ejecutada'));
    }

    public function testBitacoraBookingLinkIsRestoredAfterImportedBookingReinsert(): void
    {
        $serviceId = (int) $this->connection->query(
            "INSERT INTO qs_services (name, quantity, active) VALUES ('Novia', 1, true) RETURNING id"
        )->fetchColumn();

        $bookingId = (int) $this->connection->query(
            "INSERT INTO qs_bookings (service_id, customer_name, status, sheet_external_id, source_sheet, source_row)
             VALUES ($serviceId, 'Camila Soto', 'confirmed', 'QS-123', 'Bitácora QS — Servicios', 2)
             RETURNING id"
        )->fetchColumn();

        $this->connection->exec(
            "INSERT INTO qs_bitacoras (
                booking_id, booking_external_id, fecha_servicio, tipo_servicio, clienta_nombre,
                direccion_servicio, punto_salida, tiempo_traslado_min
             ) VALUES (
                $bookingId, 'QS-123', '2026-09-12', 'Novia', 'Camila Soto',
                'Av. Siempre Viva 123', 'Estudio Qamiluna', 0
             )"
        );

        $this->connection->exec("DELETE FROM qs_bookings WHERE source_sheet = 'Bitácora QS — Servicios'");
        self::assertNull($this->connection->query('SELECT booking_id FROM qs_bitacoras')->fetchColumn());

        $newBookingId = (int) $this->connection->query(
            "INSERT INTO qs_bookings (service_id, customer_name, status, sheet_external_id, source_sheet, source_row)
             VALUES ($serviceId, 'Camila Soto', 'confirmed', 'QS-123', 'Bitácora QS — Servicios', 2)
             RETURNING id"
        )->fetchColumn();

        $importer = new PostgresSheetReplicaImporter($this->connection, $this->createMock(SheetCsvReader::class));
        $importer->relinkBitacorasToImportedBookings();

        self::assertSame(
            $newBookingId,
            (int) $this->connection->query('SELECT booking_id FROM qs_bitacoras')->fetchColumn()
        );
    }
}
