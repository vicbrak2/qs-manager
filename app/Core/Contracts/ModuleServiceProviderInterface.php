<?php

declare(strict_types=1);

namespace QS\Core\Contracts;

use QS\Shared\Bus\CommandHandlerInterface;
use QS\Shared\Bus\CommandInterface;
use QS\Shared\Bus\QueryHandlerInterface;

interface ModuleServiceProviderInterface
{
    /**
     * @return array<string, mixed>
     */
    public static function definitions(): array;

    /**
     * @return array<class-string<CommandInterface>, class-string<CommandHandlerInterface>>
     */
    public static function commandHandlers(): array;

    /**
     * @return array<class-string, class-string<QueryHandlerInterface>>
     */
    public static function queryHandlers(): array;

    /**
     * @return array<class-string<HookableInterface>>
     */
    public static function hookables(): array;

    /**
     * @return array<class-string<ActivationHookInterface>>
     */
    public static function activationHooks(): array;
}
