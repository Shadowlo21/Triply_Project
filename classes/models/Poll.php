<?php

class Poll
{
    private int     $id;
    private int     $tripId;
    private string  $question;
    private string  $type;
    private bool    $isAnonymous;
    private ?string $deadline;
    private string  $status;
    private int     $createdBy;

    public function __construct(array $row)
    {
        $this->id          = (int)$row['id'];
        $this->tripId      = (int)$row['trip_id'];
        $this->question    = $row['question'];
        $this->type        = $row['type'];
        $this->isAnonymous = (bool)$row['is_anonymous'];
        $this->deadline    = $row['deadline'] ?? null;
        $this->status      = $row['status'];
        $this->createdBy   = (int)$row['created_by'];
    }

    public static function findById(int $id): ?self
    {
        $db   = Database::getInstance('social');
        $stmt = $db->prepare('SELECT * FROM polls WHERE id = ?');
        $stmt->execute([$id]);
        $row  = $stmt->fetch();
        return $row ? new self($row) : null;
    }

    public function addOption(string $text): int
    {
        $db   = Database::getInstance('social');
        $stmt = $db->prepare('INSERT INTO poll_options (poll_id, option_text) VALUES (?, ?)');
        $stmt->execute([$this->id, $text]);
        return (int)$db->lastInsertId();
    }

    public function getOptions(): array
    {
        $db   = Database::getInstance('social');
        $stmt = $db->prepare('SELECT * FROM poll_options WHERE poll_id = ?');
        $stmt->execute([$this->id]);
        return $stmt->fetchAll();
    }

    
    
    
    public function closeVoting(): ?int
    {
        $db   = Database::getInstance('social');
        $stmt = $db->prepare('UPDATE polls SET status = "closed" WHERE id = ?');
        $stmt->execute([$this->id]);
        $this->status = 'closed';

        return $this->getWinner();
    }

    
    
    
    public function getWinner(): ?int
    {
        $db   = Database::getInstance('social');
        $stmt = $db->prepare(
            'SELECT option_id, COUNT(*) AS cnt
             FROM votes WHERE poll_id = ?
             GROUP BY option_id ORDER BY cnt DESC LIMIT 1'
        );
        $stmt->execute([$this->id]);
        $row = $stmt->fetch();
        return $row ? (int)$row['option_id'] : null;
    }

    
    
    
    public function getResults(bool $isLeader = false): array
    {
        $db = Database::getInstance('social');

        if ($this->isAnonymous && !$isLeader) {
            $stmt = $db->prepare(
                'SELECT po.id AS option_id, po.option_text, COUNT(v.id) AS vote_count
                 FROM poll_options po
                 LEFT JOIN votes v ON v.option_id = po.id
                 WHERE po.poll_id = ?
                 GROUP BY po.id ORDER BY po.id'
            );
            $stmt->execute([$this->id]);
            return $stmt->fetchAll();
        }

        $stmt = $db->prepare(
            'SELECT po.id AS option_id, po.option_text, COUNT(v.id) AS vote_count,
                    GROUP_CONCAT(v.user_id) AS voter_ids
             FROM poll_options po
             LEFT JOIN votes v ON v.option_id = po.id
             WHERE po.poll_id = ?
             GROUP BY po.id ORDER BY po.id'
        );
        $stmt->execute([$this->id]);
        $rows = $stmt->fetchAll();

        if (empty($rows)) return [];

        $allVoterIds = [];
        foreach ($rows as $row) {
            if ($row['voter_ids']) {
                foreach (explode(',', $row['voter_ids']) as $uid) {
                    $allVoterIds[] = (int)$uid;
                }
            }
        }
        $allVoterIds = array_values(array_unique($allVoterIds));

        $names = [];
        if (!empty($allVoterIds)) {
            $ph      = implode(',', array_fill(0, count($allVoterIds), '?'));
            $uStmt   = Database::getInstance('accounts')->prepare(
                "SELECT id, email, data FROM users WHERE id IN ({$ph})"
            );
            $uStmt->execute($allVoterIds);
            foreach ($uStmt->fetchAll() as $u) {
                $name = '';
                if (!empty($u['data'])) {
                    try {
                        $d = Encryption::decryptJson($u['data'], (int)$u['id']);
                        $name = $d['name'] ?? '';
                    } catch (\Throwable $ignored) {}
                }
                $names[$u['id']] = $name !== '' ? $name : $u['email'];
            }
        }

        foreach ($rows as &$row) {
            $voterIds = $row['voter_ids'] ? array_map('intval', explode(',', $row['voter_ids'])) : [];
            $row['voters'] = array_values(array_filter(array_map(fn($id) => $names[$id] ?? null, $voterIds)));
            unset($row['voter_ids']);
        }

        return $rows;
    }

    
    
    
    public function checkDeadline(): void
    {
        if ($this->status === 'closed') return;
        if ($this->deadline && strtotime($this->deadline) < time()) {
            $this->closeVoting();
        }
    }

    
    public function getId(): int          { return $this->id; }
    public function getTripId(): int      { return $this->tripId; }
    public function getStatus(): string   { return $this->status; }
    public function isAnonymous(): bool   { return $this->isAnonymous; }
    public function getCreatedBy(): int   { return $this->createdBy; }
}
