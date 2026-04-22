<?php

class Vote
{
    private int    $id;
    private int    $pollId;
    private int    $optionId;
    private int    $userId;
    private string $castAt;

    public function __construct(array $row)
    {
        $this->id       = (int)$row['id'];
        $this->pollId   = (int)$row['poll_id'];
        $this->optionId = (int)$row['option_id'];
        $this->userId   = (int)$row['user_id'];
        $this->castAt   = $row['cast_at'];
    }

    
    
    
    public static function cast(int $pollId, int $optionId, int $userId): ?self
    {
        $db = Database::getInstance('social');

        $stmt = $db->prepare('SELECT status FROM polls WHERE id = ?');
        $stmt->execute([$pollId]);
        $poll = $stmt->fetch();

        if (!$poll || $poll['status'] !== 'open') return null;

        try {
            $stmt = $db->prepare(
                'INSERT INTO votes (poll_id, option_id, user_id) VALUES (?, ?, ?)'
            );
            $stmt->execute([$pollId, $optionId, $userId]);
            $id = (int)$db->lastInsertId();
        } catch (\PDOException $e) {
            
            return null;
        }

        return new self([
            'id'        => $id,
            'poll_id'   => $pollId,
            'option_id' => $optionId,
            'user_id'   => $userId,
            'cast_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    
    
    
    public static function getByPoll(int $pollId): array
    {
        $db   = Database::getInstance('social');
        $stmt = $db->prepare('SELECT * FROM votes WHERE poll_id = ?');
        $stmt->execute([$pollId]);
        return array_map(fn($r) => new self($r), $stmt->fetchAll());
    }

    
    public function getId(): int       { return $this->id; }
    public function getPollId(): int   { return $this->pollId; }
    public function getOptionId(): int { return $this->optionId; }
    public function getUserId(): int   { return $this->userId; }
}
