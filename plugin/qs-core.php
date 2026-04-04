<?php
/**
 * Plugin Name: QS Core
 * Plugin URI:  https://github.com/vicbrak2/qs-manager
 * Description: Sistema central de gestion para QS bajo arquitectura modular.
 * Version:     1.0.0
 * Author:      Victor Brak
 * Text Domain: qs-core
 * Domain Path: /languages
 *
 * @package QS
 */

declare(strict_types=1);

use QS\Core\Bootstrap\PluginBootstrapper;

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('QS_CORE_ROOT_DIR')) {
    define('QS_CORE_ROOT_DIR', dirname(__DIR__));
}

if (! defined('QS_CORE_PLUGIN_DIR')) {
    define('QS_CORE_PLUGIN_DIR', __DIR__);
}

if (! defined('QS_CORE_PLUGIN_FILE')) {
    define('QS_CORE_PLUGIN_FILE', __FILE__);
}

if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    add_action(
        'admin_notices',
        static function (): void {
            printf(
                '<div class="notice notice-error"><p><strong>QS Core</strong> requiere PHP 8.1 o superior. Version detectada: %s.</p></div>',
                esc_html(PHP_VERSION)
            );
        }
    );

    return;
}

$autoload = QS_CORE_ROOT_DIR . '/vendor/autoload.php';

if (! file_exists($autoload)) {
    add_action(
        'admin_notices',
        static function (): void {
            echo '<div class="notice notice-error"><p><strong>QS Core</strong> no encontro <code>vendor/autoload.php</code>. Ejecuta <code>composer install</code> en la raiz del plugin.</p></div>';
        }
    );

    return;
}

require_once $autoload;

$bootstrapper = new PluginBootstrapper(QS_CORE_ROOT_DIR, QS_CORE_PLUGIN_FILE);

register_activation_hook(
    QS_CORE_PLUGIN_FILE,
    static function () use ($bootstrapper): void {
        $bootstrapper->activate();
    }
);

register_deactivation_hook(
    QS_CORE_PLUGIN_FILE,
    static function () use ($bootstrapper): void {
        $bootstrapper->deactivate();
    }
);

add_action(
    'plugins_loaded',
    static function () use ($bootstrapper): void {
        $bootstrapper->boot();
    }
);
