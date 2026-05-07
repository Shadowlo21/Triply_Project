<?php

class Emergency
{
    public int    $emergencyId;
    public int    $tripId;
    public int    $triggeredBy;
    public string $message;
    public string $timestamp;

    public function __construct(array $row)
    {
        $this->emergencyId = (int)($row['id'] ?? 0);
        $this->tripId      = (int)($row['trip_id'] ?? 0);
        $this->triggeredBy = (int)($row['triggered_by'] ?? 0);
        $this->message     = $row['message']    ?? '';
        $this->timestamp   = $row['timestamp']  ?? date('Y-m-d H:i:s');
    }

    public static function broadcastEmergency(int $tripId, int $triggeredBy, string $message): int
    {
        $stmt = Database::getInstance('trips')->prepare(
            "SELECT user_id FROM trip_members WHERE trip_id = ? AND status = 'accepted'"
        );
        $stmt->execute([$tripId]);
        $count = 0;
        foreach ($stmt->fetchAll() as $row) {
            $uid = (int)$row['user_id'];
            if ($uid === $triggeredBy) continue;
            Notification::send($uid, 'emergency', $message, '🚨 EMERGENCY');
            $count++;
        }
        return $count;
    }
}
