<?php

class Itinerary
{
    private int $tripId;

    public function __construct(int $tripId)
    {
        $this->tripId = $tripId;
    }

    
    
    
    
    public function detectConflicts(): array
    {
        $db   = Database::getInstance('trips');
        $stmt = $db->prepare(
            'SELECT * FROM activities
             WHERE trip_id = ? AND status != "cancelled"
             ORDER BY datetime ASC'
        );
        $stmt->execute([$this->tripId]);
        $rows = $stmt->fetchAll();

        $activities = array_map(fn($r) => new Activity($r), $rows);
        $conflicts  = [];

        for ($i = 0; $i < count($activities); $i++) {
            for ($j = $i + 1; $j < count($activities); $j++) {
                if ($activities[$i]->conflictsWith($activities[$j])) {
                    $conflicts[] = [
                        'a' => $activities[$i],
                        'b' => $activities[$j],
                    ];
                }
            }
        }

        return $conflicts;
    }

    
    
    
    
    public function optimizeRoute(): array
    {
        $db   = Database::getInstance('trips');
        $stmt = $db->prepare(
            'SELECT * FROM activities
             WHERE trip_id = ? AND status = "confirmed" AND lat IS NOT NULL AND lng IS NOT NULL
             ORDER BY datetime ASC'
        );
        $stmt->execute([$this->tripId]);
        $activities = $stmt->fetchAll();

        if (count($activities) <= 1) return $activities;

        $ordered   = [array_shift($activities)];
        $remaining = $activities;

        while (!empty($remaining)) {
            $last    = end($ordered);
            $nearest = null;
            $minDist = PHP_FLOAT_MAX;

            foreach ($remaining as $key => $act) {
                $dist = $this->haversine(
                    (float)$last['lat'], (float)$last['lng'],
                    (float)$act['lat'],  (float)$act['lng']
                );
                if ($dist < $minDist) {
                    $minDist = $dist;
                    $nearest = $key;
                }
            }

            $ordered[] = $remaining[$nearest];
            unset($remaining[$nearest]);
        }

        return $ordered;
    }

    
    
    
    public function saveVersion(int $editorId, ?string $note = null): ItineraryVersion
    {
        return ItineraryVersion::snapshot($this->tripId, $editorId, $note);
    }

    
    
    
    
    
    public function applyTransportBuffers(): int
    {
        $db   = Database::getInstance('trips');
        $stmt = $db->prepare(
            'SELECT * FROM activities
             WHERE trip_id = ? AND status != "cancelled"
             ORDER BY datetime ASC'
        );
        $stmt->execute([$this->tripId]);
        $rows = $stmt->fetchAll();

        if (count($rows) < 2) return 0;

        $adjusted = 0;
        $update   = $db->prepare('UPDATE activities SET datetime = ? WHERE id = ?');

        for ($i = 1; $i < count($rows); $i++) {
            $prev      = $rows[$i - 1];
            $curr      = $rows[$i];
            $prevEnd   = strtotime($prev['datetime']) + ((int)$prev['duration_min'] * 60);
            $buffer    = Activity::bufferMinutes($curr['transport_mode']) * 60;
            $earliest  = $prevEnd + $buffer;
            $currStart = strtotime($curr['datetime']);

            if ($currStart < $earliest) {
                $newDatetime = date('Y-m-d H:i:s', $earliest);
                $update->execute([$newDatetime, $curr['id']]);
                $rows[$i]['datetime'] = $newDatetime;
                $adjusted++;
            }
        }

        return $adjusted;
    }

    
    
    
    
    
    public function getCurrentItinerary(): array
    {
        $tripsDb  = Database::getInstance('trips');
        $stmt     = $tripsDb->prepare(
            'SELECT * FROM activities
             WHERE trip_id = ? AND status != "cancelled"
             ORDER BY datetime ASC'
        );
        $stmt->execute([$this->tripId]);
        $activities = $stmt->fetchAll();

        if (empty($activities)) return [];

        
        $creatorIds   = array_unique(array_column($activities, 'created_by'));
        $accountsDb   = Database::getInstance('accounts');
        $placeholders = implode(',', array_fill(0, count($creatorIds), '?'));
        $userStmt     = $accountsDb->prepare(
            "SELECT id, email FROM users WHERE id IN ({$placeholders})"
        );
        $userStmt->execute($creatorIds);
        $emails = [];
        foreach ($userStmt->fetchAll() as $u) {
            $emails[$u['id']] = $u['email'];
        }

        foreach ($activities as &$a) {
            $a['creator_email'] = $emails[$a['created_by']] ?? '';
        }

        return $activities;
    }

    
    
    
    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    
    
    
    
    public static function calculateSettlement(int $tripId): array
    {
        $db   = Database::getInstance('financial');

        
        $stmt = $db->prepare(
            'SELECT paid_by, SUM(converted_amount) AS paid FROM expenses WHERE trip_id = ? GROUP BY paid_by'
        );
        $stmt->execute([$tripId]);
        $paid = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $stmt = $db->prepare(
            'SELECT es.user_id, SUM(es.amount) AS share
             FROM expense_splits es
             JOIN expenses e ON e.id = es.expense_id
             WHERE e.trip_id = ?
             GROUP BY es.user_id'
        );
        $stmt->execute([$tripId]);
        $owed = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $balance = [];
        foreach (array_unique(array_merge(array_keys($paid), array_keys($owed))) as $uid) {
            $balance[$uid] = ((float)($paid[$uid] ?? 0)) - ((float)($owed[$uid] ?? 0));
        }

        
        $creditors = array_filter($balance, fn($b) => $b > 0.01);
        $debtors   = array_filter($balance, fn($b) => $b < -0.01);
        arsort($creditors);
        asort($debtors);

        $transactions = [];

        while (!empty($creditors) && !empty($debtors)) {
            $creditorId = array_key_first($creditors);
            $debtorId   = array_key_first($debtors);
            $amount     = min($creditors[$creditorId], -$debtors[$debtorId]);

            $transactions[] = [
                'from'   => $debtorId,
                'to'     => $creditorId,
                'amount' => round($amount, 2),
            ];

            $creditors[$creditorId] -= $amount;
            $debtors[$debtorId]     += $amount;

            if (abs($creditors[$creditorId]) < 0.01) unset($creditors[$creditorId]);
            if (abs($debtors[$debtorId])     < 0.01) unset($debtors[$debtorId]);
        }

        return $transactions;
    }
}
