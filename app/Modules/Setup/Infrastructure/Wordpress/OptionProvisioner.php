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
    public function provision(string $siteName, string $siteDescription, ?int $frontPageId, bool $force = false): array
    {
        update_option('blogname', $siteName);
        update_option('blogdescription', $siteDescription);

        $currentShowOnFront = (string) get_option('show_on_front', 'posts');
        $currentFrontPageId = (int) get_option('page_on_front', 0);
        $frontPageChanged = false;

        if ($frontPageId !== null && $frontPageId > 0) {
            // Only change the front page if forced, or if no static front page is set yet.
            // Never silently override an already-configured static homepage (e.g. Elementor).
            $hasStaticFrontPage = $currentShowOnFront === 'page' && $currentFrontPageId > 0;
      