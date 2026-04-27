<?php

declare(strict_types=1);

namespace QS\Modules\Bitacora;

use function DI\autowire;

use QS\Core\Contracts\ModuleServiceProviderInterface;
use QS\Modules\Bitacora\Application\Command\AddBitacoraNote;
use QS\Modules\Bitacora\Application\Command\CreateBitacora;
use QS\Modules\Bitacora\Application\Command\UpdateBitacora;
use QS\Modules\Bitacora\Application\CommandHandler\AddBitacoraNoteHandler;
use QS\Modules\Bitacora\Application\CommandHandler\CreateBitacoraHandler;
use QS\Modules\Bitacora\Application\CommandHandler\UpdateBitacoraHandler;
use QS\Modules\Bitacora\Application\Query\GetBitacoraById;
use QS\Modules\Bitacora\Application\Query\GetBitacoras;
use QS\Modules\Bitacora\Application\Query\GetBitacoraSummary;
use QS\Modules\Bitacora\Application\QueryHandler\GetBitacoraByIdHandler;
use QS\Modules\Bitacora\Application\QueryHandler\GetBitacorasHandler;
use QS\Modules\Bitacora\Application\QueryHandler\GetBitacoraSummaryHandler;
use QS\Modules\Bitacora\Domain\Policy\BitacoraPolicy;
use QS\Modules\Bitacora\Domain\Repository\BitacoraRepository;
use QS\Modules\Bitacora\Infrastructure\Persistence\CptBitacoraRepository;
use QS\Modules\Bitacora\Infrastructure\Persistence\MetaFieldMapper;
use QS\Modules\Bitacora\Interfaces\Admin\BitacoraAdminPage;
use QS\Modules\Bitacora\Interfaces\Rest\BitacoraController;

final class BitacoraServiceProvider implements ModuleServiceProviderInterface
{
    public static function definitions(): array
    {
        return [
            MetaFieldMapper::class => autowire(),
            BitacoraPolicy::class => autowire(),
            BitacoraRepository::class => autowire(CptBitacoraRepository::class),
            GetBitacorasHandler::class => autowire(),
            GetBitacoraByIdHandler::class => autowire(),
            GetBitacoraSummaryHandler::class => autowire(),
            CreateBitacoraHandler::class => autowire(),
            UpdateBitacoraHandler::class => autowire(),
            AddBitacoraNoteHandler::class => autowire(),
            BitacoraController::class => autowire(),
            BitacoraAdminPage::class => autowire(),
        ];
    }

    public static function commandHandlers(): array
    {
        return [
            AddBitacoraNote::class => AddBitacoraNoteHandler::class,
            CreateBitacora::class => CreateBitacoraHandler::class,
            UpdateBitacora::class => UpdateBitacoraHandler::class,
        ];
    }

    public static function queryHandlers(): array
    {
        return [
            GetBitacoraById::class => GetBitacoraByIdHandler::class,
            GetBitacoras::class => GetBitacorasHandler::class,
            GetBitacoraSummary::class => GetBitacoraSummaryHandler::class,
        ];
    }

    public static function hookables(): array
    {
        return [];
    }

    public static function activationHooks(): array
    {
        return [];
    }
}
