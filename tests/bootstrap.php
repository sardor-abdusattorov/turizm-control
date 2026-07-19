<?php

/*
 * A cached config (php artisan config:cache) makes Laravel skip env() entirely,
 * so even the force="true" variables in phpunit.xml cannot pin the suite to
 * sqlite/sync — tests would silently run against the production database and
 * queue. Drop every bootstrap cache before the app boots; redeploys rebuild
 * them with php artisan optimize.
 */
foreach (['config.php', 'routes-v7.php', 'events.php'] as $cache) {
    $path = __DIR__.'/../bootstrap/cache/'.$cache;

    if (file_exists($path)) {
        unlink($path);
    }
}

require __DIR__.'/../vendor/autoload.php';
