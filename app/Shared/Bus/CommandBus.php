<?php

declare(strict_types=1);

namespace QS\Shared\Bus;

use Psr\Container\ContainerInterface;
use QS\Core\Errors\QsException;

final class CommandBus
{
    /**
     * @var array<class-string, class-string<CommandHandlerInterface>>
     */
    private array $handlers = [];

    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * @param class-string $commandClass
     * @param class-string<CommandHandlerInterface> $handlerClass
     */
    public function register(string $commandClass, string $handlerClass): void
    {
        $this->handlers[$commandClass] = $handlerClass;
    }

    public function dispatch(CommandInterface $command): mixed
    {
        $class = $command::class;

        if (! isset($this->handlers[$class])) {
            throw new QsException(sprintf('No handler registered for command %s.', $class));
        }

        /** @var CommandHandlerInterface $handler */
        $handler = $this->container->get($this->handlers[$class]);

        return $handler->handle($command);
    }
}
