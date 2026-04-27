<?php

declare(strict_types=1);

namespace QS\Shared\Bus;

use Psr\Container\ContainerInterface;
use QS\Core\Errors\QsException;

final class QueryBus
{
    /**
     * @var array<class-string, class-string<QueryHandlerInterface>>
     */
    private array $handlers = [];

    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /**
     * @param class-string $queryClass
     * @param class-string<QueryHandlerInterface> $handlerClass
     */
    public function register(string $queryClass, string $handlerClass): void
    {
        $this->handlers[$queryClass] = $handlerClass;
    }

    public function ask(object $query): mixed
    {
        $class = $query::class;

        if (! isset($this->handlers[$class])) {
            throw new QsException(sprintf('No handler registered for query %s.', $class));
        }

        /** @var QueryHandlerInterface $handler */
        $handler = $this->container->get($this->handlers[$class]);

        return $handler->handle($query);
    }
}
