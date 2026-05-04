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

        // Calcular totales: total_servicio = valor × cantidad
        // total_por_pagar = total_servicio - monto_abono (si aplica)
        $valorNum      = (int) preg_replace('/\D/', '', $data->valorServicio);
        $totalServicio = $valorNum * $data->cantidad;
        $montoAbonoNum = $data->abono ? (int) preg_replace('/\D/', '', $data->montoAbono) : 0;
        $totalPorPagar = $totalServicio - $montoAbonoNum;

        // Campos exactos que espera la planilla de Google Sheets.
        // Columnas: Encargada, Día, Fecha, Hora, Servicio, Cantidad, Clienta, Teléfono,
        //           Dirección, Comuna, Traslado, Abono, Fecha Abono, Valor Servicio,
        //           Total servicio, Total por pagar, Acción, Estado Evento, ID Evento
        $payload = [
            'sheet_name'      => $sheetName,
            'encargada'       => $data->encargada,
            'dia'             => $this->diaSemana($data->startTime),
            'fecha'           => $data->startTime->format('d/m/Y'),   // formato chileno d/m/Y
            'hora'            => $data->startTime->format('H:i'),
            'servicio'        => $data->serviceName,
            'cantidad'        => $data->cantidad,
            'clienta'         => $data->clientName,
            'telefono'        => $data->clientPhone,
            'direccion'       => $data->direccion,
            'comuna'          => $data->comuna,
            'traslado'        => $data->traslado,
            'abono'           => $data->abono ? $montoAbonoNum : '',
            'fecha_abono'     => $data->abono ? $data->fechaAbono : '',
            'valor_servicio'  => $valorNum,
            'total_servicio'  => $totalServicio,
            'total_por_pagar' => $totalPorPagar,
            'accion'          => '',           // La planilla lo gestiona
            'estado_evento'   => 'Pendiente',  // Dispara la creación del evento en GCal
            'id_evento'       => '',           // Lo completa la planilla tras crear el evento
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
     * Retorna el nombre del día de la semana en español.
     */
    private function diaSemana(DateTimeImmutable $date): string
    {
        $dias = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];

        return $dias[(int) $date->format('N')];
    }

    /**
     * Mapea una fecha al nombre de la pestaña del mes en la hoja de cálculo (ej. "Mayo", "Junio").
     */
    private function sheetNameForDate(DateTimeImmutable $date): string
    {
        $months = [
            1  => 'Enero',     2  => 'Febrero',   3  => 'Marzo',
            4  => 'Abril',     5  => 'Mayo',       6  => 'Junio',
            7  => 'Julio',     8  => 'Agosto',     9  => 'Septiembre',
            10 => 'Octubre',   11 => 'Noviembre',  12 => 'Diciembre',
        ];

        return $months[(int) $date->format('n')];
    }
}
