<?php

declare(strict_types=1);

namespace QS\Modules\Setup\Infrastructure\Wordpress;

use QS\Core\Logging\Logger;

final class OptionProvisioner
{
    public function __construct(
        private readonly Logger $logger
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function provision(string $siteName, string $siteDescription, ?int $frontPageId): array
    {
        update_option('blogname', $siteName);
        update_option('blogdescription', $siteDescription);

        if ($frontPageId !== null && $frontPageId > 0) {
            update_option('show_on_front', 'page');
            update_option('page_on_front', $frontPageId);
        }

        $this->logger->info('QS option provisioning completed.');

        return [
            'blogname' => $siteName,
            'blogdescription' => $siteDescription,
            'show_on_front' => $frontPageId !== null && $frontPageId > 0 ? 'page' : (string) get_option('show_on_front', 'posts'),
            'page_on_front' => $frontPageId,
        ];
    }
}
