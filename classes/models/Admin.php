<?php

class Admin extends User
{
    public function manageUsers(int $userId = 0): array
    {
        if ($userId > 0) {
            $stmt = Database::getInstance('accounts')
                ->prepare('SELECT id, email, role, created_at FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            return $stmt->fetch() ?: [];
        }
        return Database::getInstance('accounts')
            ->query('SELECT id, email, role, created_at FROM users ORDER BY created_at DESC')
            ->fetchAll();
    }

    public function viewGlobalStatistics(): array
    {
        return [
            'users'           => (int)Database::getInstance('accounts')->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'trips'           => (int)Database::getInstance('trips')->query('SELECT COUNT(*) FROM trips')->fetchColumn(),
            'expenses'        => (int)Database::getInstance('financial')->query('SELECT COUNT(*) FROM expenses')->fetchColumn(),
            'pending_docs'    => (int)Database::getInstance('documents')->query("SELECT COUNT(*) FROM profile_documents WHERE status = 'pending'")->fetchColumn(),
            'active_sessions' => (int)Database::getInstance('accounts')->query("SELECT COUNT(*) FROM sessions WHERE datetime(expires_at) > datetime('now')")->fetchColumn(),
        ];
    }

    public function monitorSystemHealth(): array
    {
        return [
            'db_ok'        => true,
            'uploads_ok'   => is_writable(__DIR__ . '/../../storage/uploads/'),
            'pending_docs' => (int)Database::getInstance('documents')->query("SELECT COUNT(*) FROM profile_documents WHERE status = 'pending'")->fetchColumn(),
            'last_purge'   => date('Y-m-d H:i:s'),
        ];
    }

    public function updateExchangeRates(string $baseCurrency): void
    {
        try {
            Database::getInstance('financial')
                ->prepare("INSERT OR REPLACE INTO currency_rates (base, last_updated) VALUES (?, datetime('now'))")
                ->execute([$baseCurrency]);
        } catch (\Throwable $ignored) {}
    }

    public function managePermissionsAndRoles(User $user, string $role): void
    {
        if (!in_array($role, ['member', 'leader', 'admin'], true)) return;
        Database::getInstance('accounts')
            ->prepare('UPDATE users SET role = ? WHERE id = ?')
            ->execute([$role, $user->getId()]);
    }

    public function deleteUser(int $userId): bool
    {
        return Database::getInstance('accounts')
            ->prepare('DELETE FROM users WHERE id = ?')
            ->execute([$userId]);
    }

    public function purgeExpiredSessions(): void
    {
        Auth::purgeExpired();
    }
}
