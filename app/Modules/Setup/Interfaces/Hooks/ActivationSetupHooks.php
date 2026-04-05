<?php

declare(strict_types=1);

namespace QS\Modules\Setup\Interfaces\Hooks;

use QS\Core\Logging\Logger;
use QS\Modules\Setup\Application\Command\SetupSiteCommand;
use QS\Modules\Setup\Application\CommandHandler\SetupSiteHandler;

final class ActivationSetupHooks
{
    public function __construct(
        private readonly SetupSiteHandler $setupSiteHandler,
        private readonly Logger $logger
    ) {
    }

    public function run(): void
    {
        if (! function_exists('get_option')) {
            return;
        }

        if ((bool) get_option('qs_setup_completed', false)) {
            $this->logger->info('QS setup skipped on activation because it was already completed.');
            return;
        }

        try {
            $this->setupSiteHandler->handle(SetupSiteCommand::defaults());
        } catch (\Throwable $exception) {
            $this->logger->warning('QS setup on activation failed: ' . $exception->getMessage());
        }
    }
}
