#!/usr/bin/env sh
set -eu

if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist --no-progress
fi

export PHP_CLI_SERVER_WORKERS=5
php -S 0.0.0.0:8080 -t public public/router.php
