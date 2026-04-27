<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Shared\Bus;

use Psr\Container\ContainerInterface;
use RuntimeException;
use QS\Core\Errors\QsException;
use QS\Shared\Bus\QueryBus;
use QS\Shared\Bus\QueryHandlerInterface;
use QS\Shared\Testing\TestCase;

final class QueryBusTest extends TestCase
{
    public function testAskResolvesAndExecutesRegisteredHandler(): void
    {
        $handler = new QueryBusTestHandler();
        $container = new QueryBusTestContainer([
            QueryBusTestHandler::class => $handler,
        ]);
        $bus = new QueryBus($container);
        $bus->register(QueryBusTestQuery::class, QueryBusTestHandler::class);

        self::assertSame([], $container->requestedIds());

        $result = $bus->ask(new QueryBusTestQuery(42));

        self::assertSame(['id' => 42], $result);
        self::assertSame([QueryBusTestHandler::class], $container->requestedIds());
        self::assertSame(42, $handler->lastQueryId());
    }

    public function testAskThrowsWhenNoHandlerIsRegistered(): void
    {
        $container = new QueryBusTestContainer([]);
        $bus = new QueryBus($container);

        $this->expectException(QsException::class);
        $this->expectExceptionMessage('No handler registered for query ' . QueryBusTestQuery::class . '.');

        $bus->ask(new QueryBusTestQuery(10));
    }

    public function testContainerIsNotConsultedUntilAsk(): void
    {
        $container = new QueryBusTestContainer([
            QueryBusTestHandler::class => new QueryBusTestHandler(),
        ]);
        $bus = new QueryBus($container);
        $bus->register(QueryBusTestQuery::class, QueryBusTestHandler::class);

        self::assertSame([], $container->requestedIds());

        $bus->ask(new QueryBusTestQuery(7));

        self::assertSame([QueryBusTestHandler::class], $container->requestedIds());
    }
}

final class QueryBusTestQuery
{
    public function __construct(public readonly int $id)
    {
    }
}

final class QueryBusTestHandler implements QueryHandlerInterface
{
    private ?int $lastQueryId = null;

    /**
     * @return array{id: int}
     */
    public function handle(object $query): array
    {
        assert($query instanceof QueryBusTestQuery);

        $this->lastQueryId = $query->id;

        return ['id' => $query->id];
    }

    public function lastQueryId(): ?int
    {
        return $this->lastQueryId;
    }
}

final class QueryBusTestContainer implements ContainerInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $entries;

    /**
     * @var array<int, string>
     */
    private array $requestedIds = [];

    /**
     * @param array<string, mixed> $entries
     */
    public function __construct(array $entries)
    {
        $this->entries = $entries;
    }

    public function get(string $id): mixed
    {
        $this->requestedIds[] = $id;

        if (! array_key_exists($id, $this->entries)) {
            throw new RuntimeException(sprintf('Container entry "%s" was not configured.', $id));
        }

        return $this->entries[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }

    /**
     * @return array<int, string>
     */
    public function requestedIds(): array
    {
        return $this->requestedIds;
    }
}
