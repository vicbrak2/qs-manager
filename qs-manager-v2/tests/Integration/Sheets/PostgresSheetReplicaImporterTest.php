<?php

declare(strict_types=1);

namespace QSManager\Tests\Integration\Sheets;

use PDO;
use PHPUnit\Framework\TestCase;
use QSManager\Application\Sheets\SheetCsvReader;
use QSManager\Infrastructure\Database\ConnectionFactory;
use QSManager\Infrastructure\Sheets\PostgresSheetReplicaImporter;

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
            ['service_id', 'nombre_canonico', 'duracion', 'requiere_evaluacion', 'categoria', 'precio_venta', 'servicio', 'valor', 'fecha', 'nombre', 'pago', 'encargada', 'clienta', 'dia', 'hora', 'cantidad', 'telefono', 'direccion', 'comuna', 'fecha prueba', 'hora prueba', 'estado prueba', 'traslado', 'abono', 'fecha abono', 'valor servicio', 'total servicio', 'total por pagar', 'accion', 'estado evento', 'id evento', 'estado pago', 'precio venta', 'activo', 'pago mua', 'pago estilista', 'prueba mua', 'prueba estilista', 'materiales', 'traslado / logistica', 'valor traslado', 'otros costos', 'costo total', 'utilidad', 'margen %', 'estado', 'observaciones', 'id servicios', 'servicio(s)', 'abono reserva', 'total servicios', 'saldo por cobrar', 'gastos operativos', 'estado servicios', 'concepto', 'monto gasto ($)', 'seleccionar servicio', 'id', 'id contrato', 'tipo de servicio', 'fecha gasto', 'estado gasto', 'tipo', 'forma de pago', 'id calendar', 'referencia agenda', 'hito', 'grupo caja', 'estado contrato', 'reserva contrato'],
            ['S-001', 'Corte Hombre', '30', 'no', 'Peluquería', '15000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '']
        ]);
        
        $importer = new PostgresSheetReplicaImporter($this->connection, $mockReader);
        
        // First execution
        $result1 = $importer->importAll();
        $this->assertSame('completed', $result1->toArray()['sources']['Servicios_Master']['status']);
        
        $count1 = (int) $this->connection->query('SELECT COUNT(*) FROM qs_services')->fetchColumn();
        $this->assertSame(1, $count1);
        
        // Second execution (Idempotency)
        $result2 = $importer->importAll();
        $this->assertSame('completed', $result2->toArray()['sources']['Servicios_Master']['status']);
        
        $count2 = (int) $this->connection->query('SELECT COUNT(*) FROM qs_services')->fetchColumn();
        // The count should still be 1 (or 2 if we insert history per import_run, but the actual qs_services table should have 1)
        // Since the replica inserts rows for each run but the projection upserts, we should check qs_services
        
        $qsServicesCount = (int) $this->connection->query('SELECT COUNT(*) FROM qs_services')->fetchColumn();
        $this->assertSame(1, $qsServicesCount);
    }
}
