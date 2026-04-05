<?php

declare(strict_types=1);

namespace QS\Modules\Setup\Infrastructure\Wordpress;

use QS\Core\Logging\Logger;

final class PermalinkProvisioner
{
    public function __construct(
        private readonly Logger $logger
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function provision(string $structure): array
    {
        $warnings = [];
        $resolvedStructure = trim($structure) !== '' ? trim($structure) : '/%postname%/';

        update_option('permalink_structure', $resolvedStructure);

        if (defined('ABSPATH')) {
            $htaccess = trailingslashit(ABSPATH) . '.htaccess';

            if (file_exists($htaccess) && ! is_writable($htaccess)) {
                $warnings[] = '.htaccess no es escribible; el flush puede no persistir en disco.';
                $this->logger->warning('QS permalink provisioning detected a read-only .htaccess file.');
            }
        }

        $flushed = false;

        if (function_exists('flush_rewrite_rules')) {
            try {
                flush_rewrite_rules(false);
                $flushed = true;
            } catch (\Throwable $exception) {
                $warnings[] = 'No fue posible hacer flush de rewrite rules: ' . $exception->getMessage();
                $this->logger->warning('QS permalink provisioning failed to flush rewrite rules.');
            }
        }

        $this->logger->info('QS permalink provisioning completed.');

        return [
            'structure' => $resolvedStructure,
            'flushed' => $flushed,
            'warnings' => $warnings,
        ];
    }
}
