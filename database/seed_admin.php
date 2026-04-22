<?php
// Run once: C:\php\php.exe database/seed_admin.php
require_once __DIR__ . '/../config/bootstrap.php';

$db    = Database::getInstance('accounts');
$email = 'admin@admin.com';
$pass  = 'admin';
$role  = 'admin';

// Check if already exists
$stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo "Admin already exists.\n";
    exit;
}

$hash = password_hash($pass, PASSWORD_BCRYPT);
$db->prepare('INSERT INTO users (email, password_hash, role, data) VALUES (?, ?, ?, ?)')
   ->execute([$email, $hash, $role, '']);
$userId = (int)$db->lastInsertId();

$data = Encryption::encryptJson([
    'name'              => 'Administrator',
    'phone'             => '',
    'nationality'       => 'EG',
    'emergency_contact' => '',
    'points'            => 0,
], $userId);

$db->prepare('UPDATE users SET data = ? WHERE id = ?')->execute([$data, $userId]);

echo "Admin created: {$email} / {$pass} (id={$userId})\n";
