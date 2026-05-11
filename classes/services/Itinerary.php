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

    
    
    
    
    public function saveVersion(int $editorId, ?string $note = null): ItineraryVersion
    {
        return ItineraryVersion::snapshot($this->tripId, $editorId, $note);
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
