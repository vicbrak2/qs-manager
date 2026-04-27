<?php

declare(strict_types=1);

namespace QS\Modules\Bitacora\Application\Command;

use QS\Shared\Bus\CommandInterface;

final class AddBitacoraNote implements CommandInterface
{
    public function __construct(
        public readonly int $bitacoraId,
        public readonly string $message,
        public readonly ?int $authorUserId
    ) {
    }
}
