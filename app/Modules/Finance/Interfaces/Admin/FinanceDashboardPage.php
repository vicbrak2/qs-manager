<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Interfaces\Admin;

use QS\Core\Contracts\HookableInterface;

final class FinanceDashboardPage implements HookableInterface
{
    public function register(): void
    {
        if (function_exists('add_action')) {
            add_action('admin_menu', [$this, 'registerPage']);
        }
    }

    public function registerPage(): void
    {
        if (! function_exists('add_menu_page')) {
            return;
        }

        add_menu_page(
            'Finance QS',
            'Finance QS',
            'qs_view_finance',
            'qs-finance',
            [$this, 'render'],
            'dashicons-chart-bar',
            58
        );
    }

    public function render(): void
    {
        echo '<div class="wrap"><h1>Finance QS</h1><p>El dashboard financiero se implementara sobre React en una iteracion posterior.</p></div>';
    }
}
