<?php

class Activity
{
    private int    $id;
    private int    $tripId;
    private string $title;
    private ?string $location;
    private ?float  $lat;
    private ?float  $lng;
    private string $datetime;
    private int    $durationMin;
    private string $status;       
    private string $transportMode;
    private int    $createdBy;

    public function __construct(array $row)
    {
        $this->id            = (int)$row['id'];
        $this->tripId        = (int)$row['trip_id'];
        $this->title         = $row['title'];
        $this->location      = $row['location']      ?? null;
        $this->lat           = isset($row['lat'])     ? (float)$row['lat'] : null;
        $this->lng           = isset($row['lng'])     ? (float)$row['lng'] : null;
        $this->datetime      = $row['datetime'];
        $this->durationMin   = (int)($row['duration_min'] ?? 60);
        $this->status        = $row['status'];
        $this->transportMode = $row['transport_mode'] ?? 'car';
        $this->createdBy     = (int)$row['created_by'];
    }

    public static function findById(int $id): ?self
    {
        $db   = Database::getInstance('trips');
        $stmt = $db->prepare('SELECT * FROM activities WHERE id = ?');
        $stmt->execute([$id]);
        $row  = $stmt->fetch();
        return $row ? new self($row) : null;
    }

    public function setStatus(string $status): bool
    {
        $allowed = ['draft', 'confirmed', 'cancelled'];
        if (!in_array($status, $allowed)) return false;

        $db   = Database::getInstance('trips');
        $stmt = $db->prepare('UPDATE activities SET status = ? WHERE id = ?');
        $result = $stmt->execute([$status, $this->id]);
        if ($result) $this->status = $status;
        return $result;
    }

    
    
    
    public function conflictsWith(Activity $other): bool
    {
        $aStart = strtotime($this->datetime);
        $aEnd   = $aStart + ($this->durationMin * 60);
        $bStart = strtotime($other->getDatetime());
        $bEnd   = $bStart + ($other->getDurationMin() * 60);

        return $aStart < $bEnd && $bStart < $aEnd;
    }

    
    
    
    public function addAttendee(int $userId, string $status = 'in'): bool
    {
        $db   = Database::getInstance('trips');
        $stmt = $db->prepare(
            'INSERT INTO activity_attendance (activity_id, user_id, status)
             VALUES (?, ?, ?)
             ON CONFLICT(activity_id, user_id) DO UPDATE SET status = excluded.status'
        );
        return $stmt->execute([$this->id, $userId, $status]);
    }

    public function getAttendees(): array
    {
        $db   = Database::getInstance('trips');
        $stmt = $db->prepare(
            'SELECT user_id, status FROM activity_attendance WHERE activity_id = ?'
        );
        $stmt->execute([$this->id]);
        return $stmt->fetchAll();
    }

    
    public function getId(): int          { return $this->id; }
    public function getTripId(): int      { return $this->tripId; }
    public function getTitle(): string    { return $this->title; }
    public function getDatetime(): string { return $this->datetime; }
    public function getDurationMin(): int { return $this->durationMin; }
    public function getStatus(): string   { return $this->status; }
    public function getLat(): ?float      { return $this->lat; }
    public function getLng(): ?float      { return $this->lng; }
    public function getTransportMode(): string { return $this->transportMode; }
}
