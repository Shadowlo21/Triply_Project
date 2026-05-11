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

    public function editPermission(int $tripId, int $userId, bool $canEdit): bool
    {
        $db   = Database::getInstance('trips');
        $stmt = $db->prepare(
            'UPDATE trip_members SET can_edit = ? WHERE trip_id = ? AND user_id = ?'
        );
        return $stmt->execute([(int)$canEdit, $tripId, $userId]);
    }
}
