<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Shared\Bus;

use Psr\Container\ContainerInterface;
use QS\Core\Errors\QsException;
use QS\Shared\Bus\CommandBus;
use QS\Shared\Bus\CommandHandlerInterface;
use QS\Shared\Bus\CommandInterface;
use QS\Shared\Testing\TestCase;
use RuntimeException;

final class CommandBusTest extends TestCase
{
    public function testDispatchResolvesAndExecutesRegisteredHandler(): void
    {
        $handler = new CommandBusTestHandler();
        $container = new CommandBusTestContainer([
            CommandBusTestHandler::class => $handler,
        ]);
        $bus = new CommandBus($container);
        $bus->register(CommandBusTestCommand::class, CommandBusTestHandler::class);

        self::assertSame([], $container->requestedIds());

        $result = $bus->dispatch(new CommandBusTestCommand('create'));

        self::assertSame('handled:create', $result);
        self::assertSame([CommandBusTestHandler::class], $container->requestedIds());
        self::assertSame('create', $handler->lastCommandName());
    }

    public function testDispatchThrowsWhenNoHandlerIsRegistered(): void
    {
        $container = new CommandBusTestContainer([]);
        $bus = new CommandBus($container);

        $this->expectException(QsException::class);
        $this->expectExceptionMessage('No handler registered for command ' . CommandBusTestCommand::class . '.');

        $bus->dispatch(new CommandBusTestCommand('missing'));
    }

    public function testContainerIsNotConsultedUntilDispatch(): void
    {
        $container = new CommandBusTestContainer([
            CommandBusTestHandler::class => new CommandBusTestHandler(),
        ]);
        $bus = new CommandBus($container);
        $bus->register(CommandBusTestCommand::class, CommandBusTestHandler::class);

        self::assertSame([], $container->requestedIds());

        $bus->dispatch(new CommandBusTestCommand('lazy'));

        self::assertSame([CommandBusTestHandler::class], $container->requestedIds());
    }
}

final class CommandBusTestCommand implements CommandInterface
{
    public function __construct(public readonly string $name)
    {
    }
}

final class CommandBusTestHandler implements CommandHandlerInterface
{
    private ?string $lastCommandName = null;

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof CommandBusTestCommand);

        $this->lastCommandName = $command->name;

        return 'handled:' . $command->name;
    }

    public function lastCommandName(): ?string
    {
        return $this->lastCommandName;
    }
}

final class CommandBusTestContainer implements ContainerInterface
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
