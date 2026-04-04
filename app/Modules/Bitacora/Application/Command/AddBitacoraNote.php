<?php

declare(strict_types=1);

namespace QS\Modules\Bitacora\Application\Command;

final class AddBitacoraNote
{
    public function __construct(
        public readonly int $bitacoraId,
        public readonly string $message,
        public readonly ?int $authorUserId
    ) {
    }
}
