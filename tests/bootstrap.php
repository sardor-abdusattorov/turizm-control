<?php

foreach (['config.php', 'routes-v7.php', 'events.php'] as $cache) {
    $path = __DIR__.'/../bootstrap/cache/'.$cache;

    if (file_exists($path)) {
        unlink($path);
    }
}

require __DIR__.'/../vendor/autoload.php';
