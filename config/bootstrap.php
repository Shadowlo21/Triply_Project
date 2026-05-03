<?php

$lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    [$key, $val] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($val);
}

spl_autoload_register(function (string $class): void {
    $dirs = [
        __DIR__ . '/../classes/entities/',
        __DIR__ . '/../classes/control/',
        __DIR__ . '/../classes/boundary/',
        __DIR__ . '/../config/',
    ];
    foreach ($dirs as $dir) {
        $file = $dir . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Run DB migrations on every boot (idempotent — CREATE TABLE IF NOT EXISTS)
require_once __DIR__ . '/migrate.php';
