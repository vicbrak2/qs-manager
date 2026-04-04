<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Modules\IdentityAccess;

use QS\Modules\IdentityAccess\Domain\Entity\QsUser;
use QS\Modules\IdentityAccess\Domain\Policy\AccessPolicy;
use QS\Modules\IdentityAccess\Domain\ValueObject\QsRole;
use QS\Modules\IdentityAccess\Domain\ValueObject\UserId;
use QS\Shared\Testing\TestCase;

final class AccessPolicyTest extends TestCase
{
    public function testAdminCanManageAgents(): void
    {
        $policy = new AccessPolicy();
        $user = new QsUser(new UserId(1), 'admin@example.com', 'Admin', ['qs_admin'], [], QsRole::Admin);

        self::assertTrue($policy->allows($user, 'qs_manage_agents'));
    }

    public function testCoordinadoraCannotManageAgents(): void
    {
        $policy = new AccessPolicy();
        $user = new QsUser(new UserId(2), 'coord@example.com', 'Coord', ['qs_coordinadora'], [], QsRole::Coordinadora);

        self::assertFalse($policy->allows($user, 'qs_manage_agents'));
        self::assertTrue($policy->allows($user, 'qs_manage_staff'));
    }
}
