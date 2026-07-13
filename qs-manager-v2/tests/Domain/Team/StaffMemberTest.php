<?php

declare(strict_types=1);

namespace QSManager\Tests\Domain\Team;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use QSManager\Domain\Team\StaffDisplayName;
use QSManager\Domain\Team\StaffMember;
use QSManager\Domain\Team\StaffRole;

final class StaffMemberTest extends TestCase
{
    public function testCreatesValidStaffMember(): void
    {
        $staffMember = StaffMember::create('Camila Villalobos', 'coordinadora');

        self::assertSame('Camila Villalobos', $staffMember->displayName()->value());
        self::assertSame('coordinadora', $staffMember->role()->value());
        self::assertTrue($staffMember->active());
        self::assertNull($staffMember->id());
    }

    public function testRejectsEmptyDisplayName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Staff display name is required.');

        StaffDisplayName::fromString('   ');
    }

    public function testRejectsShortDisplayName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Staff display name must be at least 3 characters.');

        StaffDisplayName::fromString('Ca');
    }

    public function testRejectsUnsupportedRole(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Staff role must be one of: admin, coordinadora, staff.');

        StaffRole::fromString('owner');
    }
}
