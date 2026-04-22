<?php
// Run once: C:\php\php.exe database/init.php
require_once __DIR__ . '/../config/bootstrap.php';

$schemas = [
    'accounts'  => __DIR__ . '/schema_accounts.sql',
    'trips'     => __DIR__ . '/schema_trips.sql',
    'financial' => __DIR__ . '/schema_financial.sql',
    'social'    => __DIR__ . '/schema_social.sql',
    'documents' => __DIR__ . '/schema_documents.sql',
];

foreach ($schemas as $dbName => $schemaFile) {
    $db  = Database::getInstance($dbName);
    $sql = file_get_contents($schemaFile);

    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        $db->exec($stmt);
    }

    echo "{$dbName}.db initialized.\n";
}

echo "\nAll databases ready.\n";
