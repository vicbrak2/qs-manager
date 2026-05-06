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
        $frontPage = $pageResult['pages'][$command->frontPageSlug] ?? null;

        // Only set a new front page if the page already existed (state='existing').
        // Never override the front page with a freshly created page — that would
        // destroy any Elementor/custom homepage already configured in production.
        $frontPageId = is_array($frontPage)
            && ($frontPage['state'] ?? '') === 'existing'
            && ($frontPage['status'] ?? '') === 'publish'
            ? (int) ($frontPage['id'] ?? 0)
            : 0;

        $optionsResult = $this->optionProvisioner->provision(
            $command->siteName,
            $command->siteDescription,
            $frontPageId > 0 ? $frontPageId : null,
            $command->force
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
            'completed_at' => $c