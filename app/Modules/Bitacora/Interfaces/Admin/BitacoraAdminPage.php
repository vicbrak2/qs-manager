<?php

declare(strict_types=1);

namespace QS\Modules\Bitacora\Interfaces\Admin;

use QS\Core\Contracts\HookableInterface;

final class BitacoraAdminPage implements HookableInterface
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
            'Bitacora QS',
            'Bitacora QS',
            'qs_manage_bitacoras',
            'qs-bitacora',
            [$this, 'render'],
            'dashicons-location-alt',
            57
        );
    }

    public function render(): void
    {
        echo '<div class="wrap"><h1>Bitacora QS</h1><p>La interfaz administrativa de Bitacora se implementara sobre React en una iteracion posterior.</p></div>';
    }
}
