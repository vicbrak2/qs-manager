<?php

declare(strict_types=1);

namespace QS\Tests\Integration\Repositories;

use QS\Modules\Booking\Domain\Service\ReservationNormalizer;
use QS\Modules\Booking\Infrastructure\Persistence\WpdbLatepointRepository;
use QS\Modules\Booking\Infrastructure\Wordpress\LatepointTableMap;
use QS\Shared\Testing\WpTestCase;

final class WpdbLatepointRepositoryTest extends WpTestCase
{
    public function testRepositoryReturnsArrayWhenLatePointIsUnavailable(): void
    {
        $this->requireWordPressRuntime();

        global $wpdb;

        $repository = new WpdbLatepointRepository($wpdb, new LatepointTableMap($wpdb), new ReservationNormalizer());

        self::assertIsArray($repository->findAll());
    }
}
