<?php

class TripLeader extends Member
{
    public function __construct(int $id, string $email, string $role = 'leader')
    {
        parent::__construct($id, $email, $role);
    }

    
    
    
    public function createTrip(array $data): int
    {
        $db   = Database::getInstance('trips');
        $stmt = $db->prepare(
            'INSERT INTO trips (title, destination, start_date, end_date, base_currency, budget_limit, max_slots, departure_point, departure_time, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['title'],
            $data['destination'],
            $data['start_date'],
            $data['end_date'],
            $data['base_currency']   ?? 'EGP',
            $data['budget_limit']    ?? null,
            $data['max_slots']       ?? null,
            $data['departure_point'] ?? null,
            $data['departure_time']  ?? null,
            $this->id,
        ]);

        $tripId = (int)$db->lastInsertId();

        
        $stmt = $db->prepare(
            'INSERT INTO trip_members (trip_id, user_id, role, can_edit) VALUES (?, ?, "leader", 1)'
        );
        $stmt->execute([$tripId, $this->id]);

        return $tripId;
    }

    
    
    
    public function confirmActivity(int $activityId): bool
    {
        $db   = Database::getInstance('trips');
        $stmt = $db->prepare(
            'UPDATE activities SET status = "confirmed" WHERE id = ? AND status = "draft"'
        );
        return $stmt->execute([$activityId]) && $stmt->rowCount() > 0;
    }

    
    
    
    public function rejectActivity(int $activityId): bool
    {
        $db   = Database::getInstance('trips');
        $stmt = $db->prepare(
            'UPDATE activities SET status = "cancelled" WHERE id = ?'
        );
        return $stmt->execute([$activityId]);
    }

    
    
    
    public function editPermission(int $tripId, int $userId, bool $canEdit): bool
    {
        $db   = Database::getInstance('trips');
        $stmt = $db->prepare(
            'UPDATE trip_members SET can_edit = ? WHERE trip_id = ? AND user_id = ?'
        );
        return $stmt->execute([(int)$canEdit, $tripId, $userId]);
    }

    
    
    
    public function setBudgetLimit(int $tripId, float $limit): bool
    {
        $db   = Database::getInstance('trips');
        $stmt = $db->prepare('UPDATE trips SET budget_limit = ? WHERE id = ? AND created_by = ?');
        return $stmt->execute([$limit, $tripId, $this->id]);
    }

    
    
    
    public function getAllDocuments(int $tripId): array
    {
        $db   = Database::getInstance('documents');
        $stmt = $db->prepare(
            'SELECT * FROM documents WHERE trip_id = ?'
        );
        $stmt->execute([$tripId]);
        return $stmt->fetchAll();
    }

    
    
    
    public function closeTrip(int $tripId): bool
    {
        $db   = Database::getInstance('trips');
        $stmt = $db->prepare(
            'UPDATE trips SET status = "settled" WHERE id = ? AND created_by = ?'
        );
        return $stmt->execute([$tripId, $this->id]);
    }

    
    
    
    public function awardPoints(int $userId, int $points): void
    {
        $user = User::findById($userId);
        if (!$user) return;

        $newPoints = $user->getPoints() + $points;
        $user->updateProfile(['points' => $newPoints]);
    }
}
