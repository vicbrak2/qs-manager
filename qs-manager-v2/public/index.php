<?php

declare(strict_types=1);

use QSManager\Infrastructure\Http\AppFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = AppFactory::create();
$app->run();

