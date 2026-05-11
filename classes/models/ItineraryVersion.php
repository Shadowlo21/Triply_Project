<?php

class ItineraryVersion
{
    private int    $id;
    private int    $tripId;
    private string $snapshot;
    private int    $changedBy;
    private string $changedAt;
    private ?string $note;

    public function __construct(array $row)
    {
        $this->id        = (int)$row['id'];
        $this->tripId    = (int)$row['trip_id'];
        $this->snapshot  = $row['snapshot'];
        $this->changedBy = (int)$row['changed_by'];
        $this->changedAt = $row['changed_at'];
        $this->note      = $row['note'] ?? null;
    }

    
    
    
    public static function snapshot(int $tripId, int $changedBy, ?string $note = null): self
    {
        $db   = Database::getInstance('trips');
        $stmt = $db->prepare(
            'SELECT * FROM activities WHERE trip_id = ? ORDER BY datetime ASC'
        );
        $stmt->execute([$tripId]);
        $activities = $stmt->fetchAll();

        $compressed = gzcompress(json_encode($activities), 6);

        $stmt = $db->prepare(
            'INSERT INTO itinerary_versions (trip_id, snapshot, changed_by, note) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$tripId, $compressed, $changedBy, $note]);

        return new self([
            'id'         => (int)$db->lastInsertId(),
            'trip_id'    => $tripId,
            'snapshot'   => $compressed,
            'changed_by' => $changedBy,
            'changed_at' => date('Y-m-d H:i:s'),
            'note'       => $note,
        ]);
    }

    
    
    
    public function rollback(): bool
    {
        $activities = $this->decode();
        if (empty($activities)) return false;

        $db = Database::getInstance('trips');
        $db->beginTransaction();

        try {
            $db->prepare('DELETE FROM activities WHERE trip_id = ?')->execute([$this->tripId]);

            $stmt = $db->prepare(
                'INSERT INTO activities (id, trip_id, title, location, lat, lng, datetime, duration_min, status, transport_mode, created_by, created_at)
                 VALUES (:id, :trip_id, :title, :location, :lat, :lng, :datetime, :duration_min, :status, :transport_mode, :created_by, :created_at)'
            );

            foreach ($activities as $a) {
                $stmt->execute([
                    ':id'             => $a['id'],
                    ':trip_id'        => $a['trip_id'],
                    ':title'          => $a['title'],
                    ':location'       => $a['location'],
                    ':lat'            => $a['lat'],
                    ':lng'            => $a['lng'],
                    ':datetime'       => $a['datetime'],
                    ':duration_min'   => $a['duration_min'],
                    ':status'         => $a['status'],
                    ':transport_mode' => $a['transport_mode'],
                    ':created_by'     => $a['created_by'],
                    ':created_at'     => $a['created_at'],
                ]);
            }

            $db->commit();
            return true;
        } catch (\Throwable $e) {
            $db->rollBack();
            return false;
        }
    }

    
    
    
    public function decode(): array
    {
        return json_decode(gzuncompress($this->snapshot), true) ?? [];
    }

    
    
    
    public static function listByTrip(int $tripId): array
    {
        $db   = Database::getInstance('trips');
        $stmt = $db->prepare(
            'SELECT id, trip_id, changed_by, changed_at, note FROM itinerary_versions
             WHERE trip_id = ? ORDER BY changed_at DESC'
        );
        $stmt->execute([$tripId]);
        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?self
    {
        $db   = Database::getInstance('trips');
        $stmt = $db->prepare('SELECT * FROM itinerary_versions WHERE id = ?');
        $stmt->execute([$id]);
        $row  = $stmt->fetch();
        return $row ? new self($row) : null;
    }

    
    public function getId(): int           { return $this->id; }
    public function getTripId(): int       { return $this->tripId; }
}
