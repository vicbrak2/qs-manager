<?php

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (! defined('QS_CORE_ROOT_DIR')) {
    define('QS_CORE_ROOT_DIR', dirname(__DIR__));
}

$pluginFile = __DIR__ . '/qs-core.php';

if (! defined('QS_CORE_PLUGIN_DIR')) {
    define('QS_CORE_PLUGIN_DIR', __DIR__);
}

if (! defined('QS_CORE_PLUGIN_FILE')) {
    define('QS_CORE_PLUGIN_FILE', $pluginFile);
}

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (! file_exists($autoload)) {
    return;
}

require_once $autoload;

$bootstrapper = new \QS\Core\Bootstrap\PluginBootstrapper(dirname(__DIR__), $pluginFile);
$bootstrapper->uninstall();
