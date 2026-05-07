<?php

class Invitation
{
    public int    $id;
    public int    $tripId;
    public int    $invitedUserId;
    public string $invitedUserEmail;
    public string $status;
    public string $invitedAt;

    public function __construct(array $row)
    {
        $this->id               = (int)($row['id'] ?? 0);
        $this->tripId           = (int)$row['trip_id'];
        $this->invitedUserId    = (int)$row['user_id'];
        $this->invitedUserEmail = $row['email'] ?? '';
        $this->status           = $row['status'] ?? 'pending';
        $this->invitedAt        = $row['joined_at'] ?? '';
    }

    public static function send(int $tripId, string $invitedEmail): ?self
    {
        $accountsDb = Database::getInstance('accounts');
        $stmt = $accountsDb->prepare('SELECT id, email FROM users WHERE email = ?');
        $stmt->execute([$invitedEmail]);
        $u = $stmt->fetch();
        if (!$u) return null;

        $tripsDb = Database::getInstance('trips');
        try {
            $tripsDb->prepare(
                "INSERT INTO trip_members (trip_id, user_id, role, status, can_edit) VALUES (?, ?, 'member', 'pending', 0)"
            )->execute([$tripId, (int)$u['id']]);
        } catch (\Throwable $ignored) {
            return null;
        }

        return new self([
            'trip_id'   => $tripId,
            'user_id'   => (int)$u['id'],
            'email'     => $u['email'],
            'status'    => 'pending',
            'joined_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function accept(): bool
    {
        return Database::getInstance('trips')
            ->prepare("UPDATE trip_members SET status = 'accepted' WHERE trip_id = ? AND user_id = ? AND status = 'pending'")
            ->execute([$this->tripId, $this->invitedUserId]);
    }

    public function reject(): bool
    {
        return Database::getInstance('trips')
            ->prepare("DELETE FROM trip_members WHERE trip_id = ? AND user_id = ? AND status = 'pending'")
            ->execute([$this->tripId, $this->invitedUserId]);
    }

    public static function findPendingForUser(int $userId): array
    {
        $stmt = Database::getInstance('trips')->prepare(
            "SELECT tm.id, tm.trip_id, tm.user_id, tm.status, tm.joined_at
             FROM trip_members tm
             WHERE tm.user_id = ? AND tm.status = 'pending'"
        );
        $stmt->execute([$userId]);
        return array_map(fn($r) => new self($r), $stmt->fetchAll());
    }
}
