<?php

class Member extends User
{
    public function __construct(int $id, string $email, string $role = 'member')
    {
        parent::__construct($id, $email, $role);
    }

    
    
    
    public function joinTrip(int $tripId): bool
    {
        $db = Database::getInstance('trips');

        
        $stmt = $db->prepare('SELECT id FROM trip_members WHERE trip_id = ? AND user_id = ?');
        $stmt->execute([$tripId, $this->id]);
        if ($stmt->fetch()) return false;

        $stmt = $db->prepare(
            "INSERT INTO trip_members (trip_id, user_id, role, status) VALUES (?, ?, 'member', 'accepted')"
        );
        return $stmt->execute([$tripId, $this->id]);
    }

    
    
    
    public function inviteUser(string $email, int $tripId): bool
    {
        $accountsDb = Database::getInstance('accounts');
        $stmt       = $accountsDb->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $target = $stmt->fetch();

        if (!$target) return false;

        $tripsDb = Database::getInstance('trips');
        $stmt   = $tripsDb->prepare(
            "INSERT OR IGNORE INTO trip_members (trip_id, user_id, role, status) VALUES (?, ?, 'member', 'pending')"
        );
        $result = $stmt->execute([$tripId, $target['id']]);

        return $result && $stmt->rowCount() > 0;
    }

    
    
    
    public function setAttendance(int $activityId, string $status): bool
    {
        if (!in_array($status, ['in', 'out', 'pending'])) return false;

        $db   = Database::getInstance('trips');
        $stmt = $db->prepare(
            'INSERT INTO activity_attendance (activity_id, user_id, status)
             VALUES (?, ?, ?)
             ON CONFLICT(activity_id, user_id) DO UPDATE SET status = excluded.status'
        );
        return $stmt->execute([$activityId, $this->id, $status]);
    }

    
    
    
    public function logExpense(int $tripId, array $data): int
    {
        $db = Database::getInstance('financial');

        $stmt = $db->prepare(
            'INSERT INTO expenses (trip_id, title, amount, original_currency, converted_amount, type, paid_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $tripId,
            $data['title'],
            $data['amount'],
            $data['original_currency'] ?? 'EGP',
            $data['converted_amount']  ?? $data['amount'],
            $data['type']              ?? 'general',
            $this->id,
        ]);

        return (int)$db->lastInsertId();
    }

    
    
    
    public function approveSettlement(int $settlementId): bool
    {
        $db = Database::getInstance('financial');

        $check = $db->prepare('SELECT trip_id FROM settlements WHERE id = ?');
        $check->execute([$settlementId]);
        $tripId = $check->fetchColumn();
        if (!$tripId) return false;

        $stmt = $db->prepare(
            'UPDATE expense_splits SET is_settled = 1
             WHERE user_id = ?
             AND expense_id IN (SELECT id FROM expenses WHERE trip_id = ?)'
        );
        return $stmt->execute([$this->id, $tripId]);
    }

    
    
    
    public function isTripLeader(int $tripId): bool
    {
        $db = Database::getInstance('trips');
        $stmt = $db->prepare(
            "SELECT 1 FROM trip_members WHERE trip_id = ? AND user_id = ? AND role = 'leader' AND status = 'accepted'"
        );
        $stmt->execute([$tripId, $this->id]);
        return (bool)$stmt->fetchColumn();
    }

    public function getDocuments(int $tripId): array
    {
        $db   = Database::getInstance('documents');
        $stmt = $db->prepare(
            'SELECT * FROM documents WHERE trip_id = ? AND user_id = ?'
        );
        $stmt->execute([$tripId, $this->id]);
        return $stmt->fetchAll();
    }

    
    
    
    public function proposeActivity(int $tripId, array $data): int
    {
        $db   = Database::getInstance('trips');
        $stmt = $db->prepare(
            'INSERT INTO activities (trip_id, title, location, lat, lng, datetime, duration_min, transport_mode, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $tripId,
            $data['title'],
            $data['location']       ?? null,
            $data['lat']            ?? null,
            $data['lng']            ?? null,
            $data['datetime'],
            $data['duration_min']   ?? 60,
            $data['transport_mode'] ?? 'car',
            $this->id,
        ]);

        return (int)$db->lastInsertId();
    }

    
    
    
    public function addComment(int $activityId, string $content): bool
    {
        $db   = Database::getInstance('trips');
        $stmt = $db->prepare(
            'INSERT INTO comments (activity_id, user_id, content) VALUES (?, ?, ?)'
        );
        return $stmt->execute([$activityId, $this->id, $content]);
    }

    
    
    
    public function castVote(int $pollId, int $optionId): bool
    {
        $db = Database::getInstance('social');

        
        $stmt = $db->prepare('SELECT status FROM polls WHERE id = ?');
        $stmt->execute([$pollId]);
        $poll = $stmt->fetch();
        if (!$poll || $poll['status'] !== 'open') return false;

        $stmt = $db->prepare(
            'INSERT OR IGNORE INTO votes (poll_id, option_id, user_id) VALUES (?, ?, ?)'
        );
        return $stmt->execute([$pollId, $optionId, $this->id]);
    }
}
