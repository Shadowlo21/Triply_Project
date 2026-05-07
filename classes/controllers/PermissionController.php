<?php

class PermissionController
{
    public function fetchMemberList(int $tripId): array
    {
        $stmt = Database::getInstance('trips')->prepare(
            'SELECT user_id, role, can_edit, status FROM trip_members WHERE trip_id = ?'
        );
        $stmt->execute([$tripId]);
        return $stmt->fetchAll();
    }

    public function processPermissionChange(int $tripId, int $memberId, string $newLevel): bool
    {
        $canEdit = ($newLevel === 'editor' || $newLevel === '1') ? 1 : 0;
        return Database::getInstance('trips')
            ->prepare('UPDATE trip_members SET can_edit = ? WHERE trip_id = ? AND user_id = ?')
            ->execute([$canEdit, $tripId, $memberId]);
    }

    public function validateAction(int $tripId, int $adminId): bool
    {
        $admin = User::findById($adminId);
        if (!$admin) return false;
        if ($admin->getRole() === 'admin') return true;
        if ($admin instanceof Member && $admin->isTripLeader($tripId)) return true;
        return false;
    }
}
