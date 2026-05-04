<?php

declare(strict_types=1);

namespace QS\Modules\Setup\Application\CommandHandler;

use QS\Core\Logging\Logger;
use QS\Modules\Setup\Application\Command\SetupSiteCommand;
use QS\Modules\Setup\Infrastructure\Wordpress\MenuProvisioner;
use QS\Modules\Setup\Infrastructure\Wordpress\OptionProvisioner;
use QS\Modules\Setup\Infrastructure\Wordpress\PageProvisioner;
use QS\Modules\Setup\Infrastructure\Wordpress\PermalinkProvisioner;
use QS\Shared\Bus\CommandHandlerInterface;
use QS\Shared\Bus\CommandInterface;

final class SetupSiteHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly PageProvisioner $pageProvisioner,
        private readonly OptionProvisioner $optionProvisioner,
        private readonly MenuProvisioner $menuProvisioner,
        private readonly PermalinkProvisioner $permalinkProvisioner,
        private readonly Logger $logger
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(CommandInterface $command): array
    {
        assert($command instanceof SetupSiteCommand);

        $pageResult = $this->pageProvisioner->provision($command->pages, $command->force);
        $frontPageId = (int) ($pageResult['pages'][$command->frontPageSlug]['id'] ?? 0);

        $optionsResult = $this->optionProvisioner->provision(
            $command->siteName,
            $command->siteDescription,
            $frontPageId > 0 ? $frontPageId : null
        );

        $menuResult = $this->menuProvisioner->provision(
            $command->menuName,
            $command->menuLocation,
            $pageResult['pages']
        );

        $permalinkResult = $this->permalinkProvisioner->provision($command->permalinkStructure);

        $completedAt = gmdate('c');

        if (function_exists('update_option')) {
            update_option('qs_setup_completed', [
                'completed_at' => $completedAt,
                'site_name' => $command->siteName,
                'front_page_id' => $frontPageId,
            ], false);

            // Persist sync secret if provided (used by n8n → WP upsert endpoint)
            if ($command->syncSecret !== '') {
                update_option('qs_sync_secret', $command->syncSecret, false);
                $this->logger->info('SetupSiteHandler: qs_sync_secret actualizado.');
            }
        }

        $this->logger->info('QS site setup completed.');

        return [
            'completed' => true,
            'completed_at' => $completedAt,
            'site' => [
                'name' => $command->siteName,
                'description' => $command->siteDescription,
            ],
            'front_page_id' => $frontPageId > 0 ? $frontPageId : null,
            'pages' => array_values($pageResult['pages']),
            'options' => $optionsResult,
            'menu' => $menuResult,
            'permalinks' => $permalinkResult,
            'sync_secret_set' => $command->syncSecret !== '',
        ];
    }
}
