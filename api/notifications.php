<?php

require_once __DIR__ . '/../config/bootstrap.php';

$user   = Auth::require();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        /**
         * Get all unread notifications for the user
         */
        case 'list':
            ApiResponse::success(Notification::getUnread($user->getId()));

        /**
         * Get unread notification count
         */
        case 'unread_count':
            $stmt = Database::getInstance('trips')
                ->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
            $stmt->execute([$user->getId()]);
            $count = $stmt->fetchColumn();
            ApiResponse::success(['count' => (int)$count]);


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
