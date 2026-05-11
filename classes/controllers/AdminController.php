<?php

class AdminController
{
    public function handle(User $user, string $action): void
    {
        if ($user->getRole() !== 'admin') {
            ApiResponse::error('Forbidden.', 403);
        }

        try {
            switch ($action) {
                case 'users':          $this->users(); break;
                case 'trips':          $this->trips(); break;
                case 'sessions':       $this->sessions(); break;
                case 'stats':          $this->stats(); break;
                case 'set_role':       $this->setRole($user); break;
                case 'delete_user':    $this->deleteUser($user); break;
                case 'purge_sessions': $this->purgeSessions(); break;
                default: ApiResponse::error('Unknown action.', 400);
            }
        } catch (\Throwable $e) {
            ApiResponse::error($e->getMessage());
        }
    }

    private function users(): void
    {
        $stmt = Database::getInstance('accounts')
            ->query('SELECT id, email, role, created_at FROM users ORDER BY created_at DESC');
        ApiResponse::success($stmt->fetchAll());
    }

    private function trips(): void
    {
        $stmt = Database::getInstance('trips')
            ->query('SELECT id, title, destination, status, created_by, created_at FROM trips ORDER BY created_at DESC');
        ApiResponse::success($stmt->fetchAll());
    }

    private function sessions(): void
    {
        $db   = Database::getInstance('accounts');
        $stmt = $db->query(
            "SELECT s.user_id, s.expires_at, s.created_at, u.email
             FROM sessions s JOIN users u ON u.id = s.user_id
             WHERE datetime(s.expires_at) > datetime('now')
             ORDER BY s.created_at DESC"
        );
        ApiResponse::success($stmt->fetchAll());
    }

    private function stats(): void
    {
        $expCount    = Database::getInstance('financial')->query('SELECT COUNT(*) FROM expenses')->fetchColumn();
        $pollCount   = Database::getInstance('social')->query('SELECT COUNT(*) FROM polls')->fetchColumn();
        $pendingDocs = Database::getInstance('documents')
            ->query("SELECT COUNT(*) FROM profile_documents WHERE status = 'pending'")->fetchColumn();
        ApiResponse::success([
            'expense_count' => (int)$expCount,
            'poll_count'    => (int)$pollCount,
            'pending_docs'  => (int)$pendingDocs,
        ]);
    }

    private function setRole(User $user): void
    {
        $userId  = (int)($_POST['user_id'] ?? 0);
        $newRole = $_POST['role'] ?? '';
        if (!$userId || !in_array($newRole, ['member','leader','admin'])) {
            ApiResponse::error('user_id and valid role required.');
        }
        if ($userId === $user->getId()) ApiResponse::error('Cannot change your own role.');

        $db         = Database::getInstance('accounts');
        $targetStmt = $db->prepare('SELECT role FROM users WHERE id = ?');
        $targetStmt->execute([$userId]);
        $targetRole = $targetStmt->fetchColumn();
        if (!$targetRole) ApiResponse::error('User not found.', 404);

        if ($targetRole === 'admin' && $user->getEmail() !== 'admin@admin.com') {
            ApiResponse::error('Only the owner can change another admin\'s role.', 403);
        }

        $db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$newRole, $userId]);
        ApiResponse::success(null, 'Role updated.');
    }

    private function deleteUser(User $user): void
    {
        $userId = (int)($_POST['user_id'] ?? 0);
        if (!$userId) ApiResponse::error('user_id required.');
        if ($userId === $user->getId()) ApiResponse::error('Cannot delete your own account.');

        $db  = Database::getInstance('accounts');
        $row = $db->prepare('SELECT role, email FROM users WHERE id = ?');
        $row->execute([$userId]);
        $target = $row->fetch();
        if (!$target) ApiResponse::error('User not found.', 404);

        if ($target['role'] === 'admin' && $user->getEmail() !== 'admin@admin.com') {
            ApiResponse::error('Only the owner can delete another admin.', 403);
        }

        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
        ApiResponse::success(null, 'User deleted.');
    }

    private function purgeSessions(): void
    {
        Database::getInstance('accounts')
            ->exec("DELETE FROM sessions WHERE datetime(expires_at) <= datetime('now')");
        ApiResponse::success(null, 'Expired sessions purged.');
    }
}
