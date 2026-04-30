<?php

class Trip
{
    private int    $id;
    private string $title;
    private string $destination;
    private string $startDate;
    private string $endDate;
    private string $baseCurrency;
    private ?float $budgetLimit;
    private int    $createdBy;
    private string $status;

    public function __construct(array $row)
    {
        $this->id           = (int)$row['id'];
        $this->title        = $row['title'];
        $this->destination  = $row['destination'];
        $this->startDate    = $row['start_date'];
        $this->endDate      = $row['end_date'];
        $this->baseCurrency = $row['base_currency'];
        $this->budgetLimit  = isset($row['budget_limit']) ? (float)$row['budget_limit'] : null;
        $this->createdBy    = (int)$row['created_by'];
        $this->status       = $row['status'];
    }

    public static function findById(int $id): ?self
    {
        $db   = Database::getInstance('trips');
        $stmt = $db->prepare('SELECT * FROM trips WHERE id = ?');
        $stmt->execute([$id]);
        $row  = $stmt->fetch();
        return $row ? new self($row) : null;
    }

    public function addMember(int $userId, string $role = 'member'): bool
    {
        $db   = Database::getInstance('trips');
        $stmt = $db->prepare(
            'INSERT OR IGNORE INTO trip_members (trip_id, user_id, role) VALUES (?, ?, ?)'
        );
        return $stmt->execute([$this->id, $userId, $role]);
    }

    public function getMembers(): array
    {
        $tripsDb = Database::getInstance('trips');
        $stmt    = $tripsDb->prepare(
            'SELECT user_id, role AS trip_role, can_edit FROM trip_members WHERE trip_id = ?'
        );
        $stmt->execute([$this->id]);
        $members = $stmt->fetchAll();

        if (empty($members)) return [];


        $userIds    = array_column($members, 'user_id');
        $accountsDb = Database::getInstance('accounts');
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $userStmt   = $accountsDb->prepare(
            "SELECT id, email, role FROM users WHERE id IN ({$placeholders})"
        );
        $userStmt->execute($userIds);
        $users = [];
        foreach ($userStmt->fetchAll() as $u) {
            $users[$u['id']] = $u;
        }

        $result = [];
        foreach ($members as $m) {
            $uid = $m['user_id'];
            $result[] = [
                'id'        => $uid,
                'email'     => $users[$uid]['email'] ?? '',
                'role'      => $users[$uid]['role']  ?? '',
                'trip_role' => $m['trip_role'],
                'can_edit'  => $m['can_edit'],
            ];
        }
        return $result;
    }

    public function getActivities(): array
    {
        $db   = Database::getInstance('trips');
        $stmt = $db->prepare(
            'SELECT * FROM activities WHERE trip_id = ? ORDER BY datetime ASC'
        );
        $stmt->execute([$this->id]);
        return $stmt->fetchAll();
    }

    public function getBudgetUsed(): float
    {
        $db   = Database::getInstance('financial');
        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(converted_amount), 0) AS total FROM expenses WHERE trip_id = ?'
        );
        $stmt->execute([$this->id]);
        return (float)$stmt->fetchColumn();
    }

    public function setStatus(string $status): bool
    {
        $allowed = ['planning', 'active', 'completed', 'settled'];
        if (!in_array($status, $allowed)) return false;

        $db   = Database::getInstance('trips');
        $stmt = $db->prepare('UPDATE trips SET status = ? WHERE id = ?');
        $result = $stmt->execute([$status, $this->id]);
        if ($result) $this->status = $status;
        return $result;
    }


    public function getId(): int
    {
        return $this->id;
    }
    public function getTitle(): string
    {
        return $this->title;
    }
    public function getStatus(): string
    {
        return $this->status;
    }
    public function getBudgetLimit(): ?float
    {
        return $this->budgetLimit;
    }
    public function getBaseCurrency(): string
    {
        return $this->baseCurrency;
    }
    public function getDestination(): string
    {
        return $this->destination;
    }
    public function getCreatedBy(): int
    {
        return $this->createdBy;
    }
    public function getStartDate(): string
    {
        return $this->startDate;
    }
    public function getEndDate(): string
    {
        return $this->endDate;
    }
}
