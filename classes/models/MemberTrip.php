<?php

class MemberTrip
{
    public int    $membershipId;
    public int    $tripId;
    public int    $userId;
    public string $role;
    public string $status;
    public int    $canEdit;
    public string $joinedAt;

    public function __construct(array $row)
    {
        $this->membershipId = (int)($row['id'] ?? 0);
        $this->tripId       = (int)$row['trip_id'];
        $this->userId       = (int)$row['user_id'];
        $this->role         = $row['role']      ?? 'member';
        $this->status       = $row['status']    ?? 'accepted';
        $this->canEdit      = (int)($row['can_edit'] ?? 0);
        $this->joinedAt     = $row['joined_at'] ?? '';
    }

    public static function join(int $tripId, int $userId, string $role = 'member'): ?self
    {
        $db = Database::getInstance('trips');
        try {
            $db->prepare(
                "INSERT INTO trip_members (trip_id, user_id, role, status, can_edit) VALUES (?, ?, ?, 'accepted', 0)"
            )->execute([$tripId, $userId, $role]);
        } catch (\Throwable $ignored) {
            return null;
        }
        $stmt = $db->prepare('SELECT * FROM trip_members WHERE id = ?');
        $stmt->execute([(int)$db->lastInsertId()]);
        $row = $stmt->fetch();
        return $row ? new self($row) : null;
    }

    public static function leave(int $tripId, int $userId): bool
    {
        return Database::getInstance('trips')
            ->prepare("DELETE FROM trip_members WHERE trip_id = ? AND user_id = ? AND status = 'accepted'")
            ->execute([$tripId, $userId]);
    }

    public static function findByTrip(int $tripId): array
    {
        $stmt = Database::getInstance('trips')->prepare('SELECT * FROM trip_members WHERE trip_id = ?');
        $stmt->execute([$tripId]);
        return array_map(fn($r) => new self($r), $stmt->fetchAll());
    }

    public static function find(int $tripId, int $userId): ?self
    {
        $stmt = Database::getInstance('trips')->prepare('SELECT * FROM trip_members WHERE trip_id = ? AND user_id = ?');
        $stmt->execute([$tripId, $userId]);
        $row = $stmt->fetch();
        return $row ? new self($row) : null;
    }
}
