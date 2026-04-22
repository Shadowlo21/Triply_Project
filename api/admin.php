<?php

require_once __DIR__ . '/../config/bootstrap.php';

$user = Auth::require();
if ($user->getRole() !== 'admin') {
    ApiResponse::error('Forbidden.', 403);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        
        case 'users':
            $stmt = Database::getInstance('accounts')
                ->query('SELECT id, email, role, created_at FROM users ORDER BY created_at DESC');
            ApiResponse::success($stmt->fetchAll());

        
        case 'trips':
            $stmt = Database::getInstance('trips')
                ->query('SELECT id, title, destination, status, created_by, created_at FROM trips ORDER BY created_at DESC');
            ApiResponse::success($stmt->fetchAll());

        
        case 'sessions':
            $db   = Database::getInstance('accounts');
            $stmt = $db->query(
                "SELECT s.user_id, s.expires_at, s.created_at, u.email
                 FROM sessions s JOIN users u ON u.id = s.user_id
                 WHERE datetime(s.expires_at) > datetime('now')
                 ORDER BY s.created_at DESC"
            );
            ApiResponse::success($stmt->fetchAll());

        
        case 'stats':
            $expCount  = Database::getInstance('financial')->query('SELECT COUNT(*) FROM expenses')->fetchColumn();
            $pollCount = Database::getInstance('social')->query('SELECT COUNT(*) FROM polls')->fetchColumn();
            ApiResponse::success([
                'expense_count' => (int)$expCount,
                'poll_count'    => (int)$pollCount,
            ]);

        
        case 'set_role':
            $userId  = (int)($_POST['user_id'] ?? 0);
            $newRole = $_POST['role'] ?? '';
            if (!$userId || !in_array($newRole, ['member','leader','admin'])) {
                ApiResponse::error('user_id and valid role required.');
            }
            if ($userId === $user->getId()) ApiResponse::error('Cannot change your own role.');

            $db = Database::getInstance('accounts');
            $db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$newRole, $userId]);
            ApiResponse::success(null, 'Role updated.');

        case 'delete_user':
            $userId = (int)($_POST['user_id'] ?? 0);
            if (!$userId) ApiResponse::error('user_id required.');
            if ($userId === $user->getId()) ApiResponse::error('Cannot delete your own account.');

            $db   = Database::getInstance('accounts');
            $row  = $db->prepare('SELECT role FROM users WHERE id = ?');
            $row->execute([$userId]);
            $target = $row->fetch();
            if (!$target) ApiResponse::error('User not found.', 404);

            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
            ApiResponse::success(null, 'User deleted.');

        
        case 'purge_sessions':
            Database::getInstance('accounts')
                ->exec("DELETE FROM sessions WHERE datetime(expires_at) <= datetime('now')");
            ApiResponse::success(null, 'Expired sessions purged.');

        default:
            ApiResponse::error('Unknown action.', 400);
    }
} catch (\Throwable $e) {
    ApiResponse::error($e->getMessage());
}

