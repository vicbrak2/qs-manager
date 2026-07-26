<?php

declare(strict_types=1);

namespace QSManager\Tests\Unit\Domain\Team;

use PHPUnit\Framework\TestCase;
use QSManager\Domain\Team\StaffAssignment;

final class StaffAssignmentTest extends TestCase
{
    public function testSeparatesMuaFromEstilistaByPosition(): void
    {
        // Caso real de la planilla (Bitacora fila 101 / Agenda Julio fila 2).
        $assignment = StaffAssignment::fromSheetValue('Cami - Paz');

        self::assertSame('Cami', $assignment->mua());
        self::assertSame('Paz', $assignment->estilista());
        self::assertSame(['Cami', 'Paz'], $assignment->names());
    }

    public function testAcceptsTheSeparatorsUsedInThePlanillas(): void
    {
        foreach (['Cami/Paz', 'Cami, Paz', 'Cami y Paz', 'Cami  -  Paz'] as $raw) {
            $assignment = StaffAssignment::fromSheetValue($raw);
            self::assertSame('Cami', $assignment->mua(), $raw);
            self::assertSame('Paz', $assignment->estilista(), $raw);
        }
    }

    public function testSingleNameIsTreatedAsMua(): void
    {
        $assignment = StaffAssignment::fromSheetValue('  Cami  ');

        self::assertSame('Cami', $assignment->mua());
        self::assertNull($assignment->estilista());
    }

    public function testEmptyValuesProduceNoAssignment(): void
    {
        foreach ([null, '', '   ', '-'] as $raw) {
            $assignment = StaffAssignment::fromSheetValue($raw);
            self::assertTrue($assignment->isEmpty());
            self::assertNull($assignment->mua());
        }
    }
}
