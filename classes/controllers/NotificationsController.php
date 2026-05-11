<?php

class NotificationsController
{
    public function handle(User $user, string $action): void
    {
        try {
            switch ($action) {

                case 'list':
                    $unreadOnly = ($_GET['unread_only'] ?? '') === '1';
                    $data = $unreadOnly
                        ? Notification::getUnread($user->getId())
                        : Notification::getAll($user->getId());
                    ApiResponse::success($data);

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

                case 'delete':
                    $id = (int)($_POST['id'] ?? 0);
                    if (!$id) ApiResponse::error('id required.');
                    Database::getInstance('trips')
                        ->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?')
                        ->execute([$id, $user->getId()]);
                    ApiResponse::success(null, 'Notification deleted.');

                case 'delete_all':
                    Database::getInstance('trips')
                        ->prepare('DELETE FROM notifications WHERE user_id = ?')
                        ->execute([$user->getId()]);
                    ApiResponse::success(null, 'All notifications cleared.');

                case 'broadcast':
                    $title   = trim($_POST['title']   ?? '');
                    $message = trim($_POST['message'] ?? '');
                    if (empty($title))   ApiResponse::error('Title required.');
                    if (empty($message)) ApiResponse::error('Message required.');

                    $isAdmin = $user->getRole() === 'admin';
                    $tripId  = (int)($_POST['trip_id'] ?? 0);
                    $target  = $_POST['target'] ?? '';

                    if ($isAdmin && !$tripId) {
                        $roleMap = ['admins' => 'admin', 'leaders' => 'leader', 'members' => 'member', 'all' => null];
                        if (!array_key_exists($target, $roleMap)) ApiResponse::error('Invalid target. Use: admins, leaders, members, all.');

                        $db         = Database::getInstance('accounts');
                        $roleFilter = $roleMap[$target];
                        if ($roleFilter) {
                            $stmt = $db->prepare('SELECT id FROM users WHERE role = ?');
                            $stmt->execute([$roleFilter]);
                        } else {
                            $stmt = $db->query('SELECT id FROM users');
                        }

                        $count = 0;
                        foreach ($stmt->fetchAll() as $row) {
                            if ((int)$row['id'] !== $user->getId()) {
                                Notification::send((int)$row['id'], 'announcement', $message, $title);
                                $count++;
                            }
                        }
                        ApiResponse::success(['notified_count' => $count], "Sent to {$count} user(s).");
                    }

                    if (!$tripId) ApiResponse::error('trip_id required.');
                    if (!$user->viewTrip($tripId)) ApiResponse::error('Access denied.', 403);
                    if ($user->getRole() === 'member') {
                        ApiResponse::error('Only leaders and admins can send trip notifications.', 403);
                    }

                    $trip    = Trip::findById($tripId);
                    $members = $trip->getMembers();
                    $count   = 0;
                    foreach ($members as $m) {
                        if ((int)$m['id'] !== $user->getId()) {
                            Notification::send((int)$m['id'], 'announcement', $message, $title);
                            $count++;
                        }
                    }
                    ApiResponse::success(['notified_count' => $count], "Sent to {$count} member(s).");

                default:
                    ApiResponse::error('Unknown action.', 400);
            }
        } catch (\Throwable $e) {
            ApiResponse::error($e->getMessage());
        }
    }
}
