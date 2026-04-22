<?php

require_once __DIR__ . '/../config/bootstrap.php';

$user   = Auth::require();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        
        case 'list':
            ApiResponse::success(Notification::getUnread($user->getId()));

        
        case 'read':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) ApiResponse::error('id required.');
            Notification::markRead($id);
            ApiResponse::success(null, 'Marked as read.');

        
        case 'read_all':
            Database::getInstance('trips')
                ->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')
                ->execute([$user->getId()]);
            ApiResponse::success(null, 'All marked as read.');

        default:
            ApiResponse::error('Unknown action.', 400);
    }
} catch (\Throwable $e) {
    ApiResponse::error($e->getMessage());
}

