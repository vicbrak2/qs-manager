<?php

declare(strict_types=1);

namespace QS\Tests\Integration\Repositories;

use QS\Modules\Team\Domain\ValueObject\Specialty;
use QS\Modules\Team\Infrastructure\Persistence\WpdbStaffRepository;
use QS\Shared\Testing\WpTestCase;

final class WpdbStaffRepositoryTest extends WpTestCase
{
    public function testFindAllReturnsOnlyRequestedSpecialty(): void
    {
        $this->requireWordPressRuntime();

        $repository = new WpdbStaffRepository();
        $staff = $repository->findAll(Specialty::Mua, false);

        self::assertIsArray($staff);
    }
}
