<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Sheets;

/**
 * Configuracion de las hojas replicadas -- extraida de
 * PostgresSheetReplicaImporter (Fase 4). Mismo contenido exacto, solo
 * movido de lugar.
 */
final class SheetImportSource
{
    public const MAIN_SPREADSHEET_ID = '1Nl9hsMADAjJFJ_GPLGxvHenXjb91hLzp5EO-sDar0WE';
    public const BITACORA_SPREADSHEET_ID = '1cXOd9imvZGA5oj-Hlk8QHwB48cbx5hNptnGi0HdjMWE';
    public const AGENDA_2026_SPREADSHEET_ID = '1-tEocDyAbAb6muckm7X8JwJbbb2__uFhc49RP_lf6A4';

    /**
     * @return array<string, array{spreadsheet_id: string, gid: int, purpose: string, handler: string, is_critical: bool}>
     */
    public static function all(): array
    {
        return [
            'Servicios_Master' => [
                'spreadsheet_id' => self::BITACORA_SPREADSHEET_ID,
                'gid' => 901001001,
                'purpose' => 'services_master',
                'handler' => 'importServicesMaster',
                'is_critical' => true,
            ],
            'Talleres' => [
                'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
                'gid' => 1004626842,
                'purpose' => 'workshops',
                'handler' => 'importWorkshops',
                'is_critical' => false,
            ],
            'Enero' => [
                'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
                'gid' => 1600012026,
                'purpose' => 'agenda_month',
                'handler' => 'importAgendaMonth',
                'is_critical' => true,
            ],
            'Febrero' => [
                'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
                'gid' => 297232105,
                'purpose' => 'agenda_month',
                'handler' => 'importAgendaMonth',
                'is_critical' => true,
            ],
            'Marzo' => [
                'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
                'gid' => 817931728,
                'purpose' => 'agenda_month',
                'handler' => 'importAgendaMonth',
                'is_critical' => true,
            ],
            'Abril' => [
                'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
                'gid' => 1913010066,
                'purpose' => 'agenda_month',
                'handler' => 'importAgendaMonth',
                'is_critical' => true,
            ],
            'Mayo' => [
                'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
                'gid' => 2068172479,
                'purpose' => 'agenda_month',
                'handler' => 'importAgendaMonth',
                'is_critical' => true,
            ],
            'Junio' => [
                'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
                'gid' => 544909107,
                'purpose' => 'agenda_month',
                'handler' => 'importAgendaMonth',
                'is_critical' => true,
            ],
            'Julio' => [
                'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
                'gid' => 2073502017,
                'purpose' => 'agenda_month',
                'handler' => 'importAgendaMonth',
                'is_critical' => true,
            ],
            'Agosto' => [
                'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
                'gid' => 301380220,
                'purpose' => 'agenda_month',
                'handler' => 'importAgendaMonth',
                'is_critical' => true,
            ],
            'Septiembre' => [
                'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
                'gid' => 2086235780,
                'purpose' => 'agenda_month',
                'handler' => 'importAgendaMonth',
                'is_critical' => true,
            ],
            'Octubre' => [
                'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
                'gid' => 1600102026,
                'purpose' => 'agenda_month',
                'handler' => 'importAgendaMonth',
                'is_critical' => true,
            ],
            'Noviembre' => [
                'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
                'gid' => 1600112026,
                'purpose' => 'agenda_month',
                'handler' => 'importAgendaMonth',
                'is_critical' => true,
            ],
            'Diciembre' => [
                'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
                'gid' => 1600122026,
                'purpose' => 'agenda_month',
                'handler' => 'importAgendaMonth',
                'is_critical' => true,
            ],
            'Servicios' => [
                'spreadsheet_id' => self::MAIN_SPREADSHEET_ID,
                'gid' => 839064078,
                'purpose' => 'service_catalog',
                'handler' => 'importServices',
                'is_critical' => true,
            ],
            'Seguimiento Caja' => [
                'spreadsheet_id' => self::MAIN_SPREADSHEET_ID,
                'gid' => 513021861,
                'purpose' => 'cash_tracking',
                'handler' => 'importCashTracking',
                'is_critical' => false,
            ],
            'Gastos Operativos' => [
                'spreadsheet_id' => self::MAIN_SPREADSHEET_ID,
                'gid' => 1642061717,
                'purpose' => 'operational_expenses',
                'handler' => 'importOperationalExpenses',
                'is_critical' => false,
            ],
            'Gastos_Fijos' => [
                'spreadsheet_id' => self::MAIN_SPREADSHEET_ID,
                'gid' => 1900014001,
                'purpose' => 'fixed_expenses',
                'handler' => 'importFixedExpenses',
                'is_critical' => true,
            ],
            'Bitácora QS — Servicios' => [
                'spreadsheet_id' => self::BITACORA_SPREADSHEET_ID,
                'gid' => 1880538608,
                'purpose' => 'bitacora',
                'handler' => 'importBitacora',
                'is_critical' => true,
            ],
        ];
    }

    public static function spreadsheetTitleFor(string $spreadsheetId): string
    {
        return match ($spreadsheetId) {
            self::BITACORA_SPREADSHEET_ID => 'Bitácora QS — Servicios',
            self::AGENDA_2026_SPREADSHEET_ID => 'Agenda 2026',
            default => 'Seguimiento Contable - Margen por Servicio',
        };
    }
}
