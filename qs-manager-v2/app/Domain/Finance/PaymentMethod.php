<?php

declare(strict_types=1);

namespace QSManager\Domain\Finance;

/**
 * Metodo de pago normalizado. Portado desde V1
 * (Modules/Finance/Domain/ValueObject/PaymentMethod.php). Conectado al
 * importador: BitacoraImporter normaliza la columna "forma de pago" con
 * fromNullable() antes de persistirla en qs_sheet_bitacora_rows, para no
 * propagar valores arbitrarios de la hoja hacia el resto del sistema.
 */
enum PaymentMethod: string
{
    case Transferencia = 'transferencia';
    case Efectivo = 'efectivo';
    case Otro = 'otro';

    /**
     * Normaliza un valor crudo (de planilla, formulario, etc.) a un caso
     * valido. Cualquier valor desconocido, vacio o null cae en Otro en vez
     * de fallar -- la fuente de datos (Sheets) no es confiable como para
     * lanzar una excepcion por un typo en la columna "forma de pago".
     */
    public static function fromNullable(?string $value): self
    {
        $normalized = $value !== null ? mb_strtolower(trim($value)) : null;

        return match ($normalized) {
            self::Transferencia->value => self::Transferencia,
            self::Efectivo->value => self::Efectivo,
            default => self::Otro,
        };
    }
}
