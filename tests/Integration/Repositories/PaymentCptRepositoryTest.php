<?php

declare(strict_types=1);

namespace QS\Tests\Integration\Repositories;

use QS\Modules\Finance\Infrastructure\Persistence\PaymentCptRepository;
use QS\Shared\Testing\WpTestCase;

final class PaymentCptRepositoryTest extends WpTestCase
{
    public function testFindAllReturnsAnArray(): void
    {
        $this->requireWordPressRuntime();

        global $wpdb;

        $repository = new PaymentCptRepository($wpdb);

        self::assertIsArray($repository->findAll());
    }
}
