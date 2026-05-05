<?php

// Load .env if it exists (local dev). On Render/production env vars come from the dashboard.
$envFile = __DIR__ . '/../.env';
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
    }
}
foreach ($_SERVER as $k => $v) {
    if (!isset($_ENV[$k]) && is_string($v)) $_ENV[$k] = $v;
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
