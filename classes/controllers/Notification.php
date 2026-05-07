<?php

/**
 * Design Pattern: Observer
 * Observes state changes in trips and sends notifications to interested parties.
 * Triggers on events: trip invite, poll closed, budget threshold exceeded, daily briefing.
 */
class Notification
{




    public static function checkBudgetThreshold(int $tripId): void
    {
        $trip = Trip::findById($tripId);
        if (!$trip || !$trip->getBudgetLimit()) return;

        $used    = $trip->getBudgetUsed();
        $limit   = $trip->getBudgetLimit();
        $percent = ($used / $limit) * 100;

        if ($percent < 80) return;

        $label = $percent >= 100 ? 'EXCEEDED' : 'WARNING (80%+)';
        $msg   = "Budget {$label}: {$used} / {$limit} {$trip->getBaseCurrency()} used on trip \"{$trip->getTitle()}\"";


        $members = $trip->getMembers();
        foreach ($members as $m) {
            self::send($m['id'], 'budget_alert', $msg, 'Budget Alert');
        }
    }




    public static function pollClosed(int $pollId, int $tripId, ?int $winnerOptionId): void
    {
        $socialDb = Database::getInstance('social');
        $msg      = "Poll #{$pollId} has closed.";

        if ($winnerOptionId) {
            $stmt = $socialDb->prepare('SELECT option_text FROM poll_options WHERE id = ?');
            $stmt->execute([$winnerOptionId]);
            $opt  = $stmt->fetchColumn();
            $msg .= " Winner: \"{$opt}\"";
        }

        $tripsDb = Database::getInstance('trips');
        $stmt    = $tripsDb->prepare('SELECT user_id FROM trip_members WHERE trip_id = ?');
        $stmt->execute([$tripId]);
        foreach ($stmt->fetchAll() as $row) {
            self::send($row['user_id'], 'poll_closed', $msg, 'Poll Closed');
        }
    }






    public static function sendDailyBriefings(): void
    {
        $tripsDb  = Database::getInstance('trips');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        $stmt = $tripsDb->prepare(
            'SELECT a.*, t.title AS trip_title, tm.user_id
             FROM activities a
             JOIN trips t ON t.id = a.trip_id
             JOIN trip_members tm ON tm.trip_id = t.id
             WHERE DATE(a.datetime) = ? AND a.status = "confirmed"'
        );
        $stmt->execute([$tomorrow]);
        $rows = $stmt->fetchAll();


        $byUser = [];
        foreach ($rows as $row) {
            $byUser[$row['user_id']][$row['trip_id']][] = $row;
        }

        foreach ($byUser as $userId => $trips) {
            foreach ($trips as $tripId => $activities) {
                $lines = array_map(
                    fn($a) => "• {$a['datetime']} — {$a['title']} @ {$a['location']}",
                    $activities
                );
                $msg = "Tomorrow's schedule for \"{$activities[0]['trip_title']}\":\n" . implode("\n", $lines);
                self::send($userId, 'daily_briefing', $msg, 'Daily Briefing');
            }
        }
    }




    public static function tripInvite(int $userId, string $tripTitle, string $inviterName): void
    {
        self::send($userId, 'invite', "{$inviterName} invited you to join trip \"{$tripTitle}\"", 'Trip Invitation');
    }




    public static function send(int $userId, string $type, string $message, string $title = ''): void
    {
        $db   = Database::getInstance('trips');
        $stmt = $db->prepare(
            'INSERT INTO notifications (user_id, type, title, message) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $type, $title, $message]);
    }




    public static function getAll(int $userId): array
    {
        $db   = Database::getInstance('trips');
        $db->prepare(
            "DELETE FROM notifications WHERE user_id = ? AND created_at < datetime('now', '-7 days')"
        )->execute([$userId]);

        $stmt = $db->prepare(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function getUnread(int $userId): array
    {
        $db   = Database::getInstance('trips');
        $stmt = $db->prepare(
            'SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function markRead(int $notificationId): void
    {
        $db   = Database::getInstance('trips');
        $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ?')->execute([$notificationId]);
    }
}
