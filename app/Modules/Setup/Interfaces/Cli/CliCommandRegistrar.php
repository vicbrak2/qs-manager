<?php

declare(strict_types=1);

namespace QS\Modules\Setup\Interfaces\Cli;

use QS\Core\Contracts\HookableInterface;

final class CliCommandRegistrar implements HookableInterface
{
    private bool $registered = false;

    public function __construct(
        private readonly QsCommand $command
    ) {
    }

    public function register(): void
    {
        if (! defined('WP_CLI') || ! WP_CLI) {
            return;
        }

        if (function_exists('add_action')) {
            add_action('cli_init', [$this, 'registerCommands']);
        }

        if (function_exists('did_action') && did_action('cli_init') > 0) {
            $this->registerCommands();
        }
    }

    public function registerCommands(): void
    {
        if ($this->registered || ! class_exists('\WP_CLI')) {
            return;
        }

        if (! is_callable(['\WP_CLI', 'add_command'])) {
            return;
        }

        call_user_func(['\WP_CLI', 'add_command'], 'qs', $this->command);
        $this->registered = true;
    }
}
