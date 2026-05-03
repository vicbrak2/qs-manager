<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Infrastructure\N8n;

use DateTimeImmutable;
use QS\Core\Logging\Logger;
use QS\Modules\Booking\Domain\Service\SheetRowData;
use QS\Modules\Booking\Domain\Service\SheetsSyncGateway;
use RuntimeException;

final class N8nSheetsSyncGateway implements SheetsSyncGateway
{
    private string $checkConflictUrl;
    private string $appendRowUrl;

    public function __construct(private readonly Logger $logger)
    {
        $base = rtrim((string) get_option('qs_n8n_base_url', 'https://n8n.qamilunastudio.com'), '/');
        $this->checkConflictUrl = $base . '/webhook/sheets/check-conflict';
        $this->appendRowUrl     = $base . '/webhook/sheets/append-row';
    }

    public function checkConflict(DateTimeImmutable $startTime, DateTimeImmutable $endTime): ?string
    {
        $sheetName = $this->sheetNameForDate($startTime);

        $payload = [
            'start_time' => $startTime->format('Y-m-d H:i:s'),
            'end_time'   => $endTime->format('Y-m-d H:i:s'),
            'sheet_name' => $sheetName,
        ];

        $this->logger->info('N8nSheetsSyncGateway: checkConflict → ' . (string) wp_json_encode($payload));

        $response = wp_remote_post($this->checkConflictUrl, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => (string) wp_json_encode($payload),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            throw new RuntimeException('Error al verificar conflicto en n8n: ' . $response->get_error_message());
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $body       = wp_remote_retrieve_body($response);
        $data       = json_decode($body, true);

        $this->logger->info("N8nSheetsSyncGateway: checkConflict response ($statusCode): $body");

        if (!is_array($data)) {
            throw new RuntimeException('Respuesta inesperada del webhook check-conflict: ' . $body);
        }

        $conflict = (bool) ($data['conflict'] ?? false);

        return $conflict ? (string) ($data['conflicting_event'] ?? 'Evento desconocido') : null;
    }

    public function appendRow(SheetRowData $data): string
    {
        $sheetName = $this->sheetNameForDate($data->startTime);

        $payload = [
            'sheet_name'    => $sheetName,
            'encargada'     => $data->encargada,
            'client_name'   => $data->clientName,
            'client_email'  => $data->clientEmail,
            'client_phone'  => $data->clientPhone,
            'service_name'  => $data->serviceName,
            'start_time'    => $data->startTime->format('Y-m-d H:i:s'),
            'end_time'      => $data->endTime->format('Y-m-d H:i:s'),
            'direccion'     => $data->direccion,
            'comuna'        => $data->comuna,
            'traslado'      => $data->traslado,
            'valor_servicio' => $data->valorServicio,
            'cantidad'      => $data->cantidad,
            'abono'         => $data->abono ? 'Sí' : 'No',
            'monto_abono'   => $data->montoAbono,
            'fecha_abono'   => $data->fechaAbono,
        ];

        $this->logger->info('N8nSheetsSyncGateway: appendRow → ' . (string) wp_json_encode($payload));

        $response = wp_remote_post($this->appendRowUrl, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => (string) wp_json_encode($payload),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            throw new RuntimeException('Error al insertar fila en n8n: ' . $response->get_error_message());
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $body       = wp_remote_retrieve_body($response);
        $result     = json_decode($body, true);

        $this->logger->info("N8nSheetsSyncGateway: appendRow response ($statusCode): $body");

        if ($statusCode < 200 || $statusCode >= 300 || !(isset($result['success']) && $result['success'] === true)) {
            throw new RuntimeException('El webhook append-row retornó error: ' . $body);
        }

        return (string) ($result['sheet_name'] ?? $sheetName);
    }

    /**
     * Mapea una fecha al nombre de la pestaña del mes en la hoja de cálculo (ej. "Mayo", "Junio").
     */
    private function sheetNameForDate(DateTimeImmutable $date): string
    {
        $months = [
            1  => 'Enero', 2  => 'Febrero', 3  => 'Marzo',
            4  => 'Abril', 5  => 'Mayo',    6  => 'Junio',
            7  => 'Julio', 8  => 'Agosto',  9  => 'Septiembre',
            10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return $months[(int) $date->format('n')];
    }
}
