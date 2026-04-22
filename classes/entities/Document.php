<?php

class Document
{
    private int    $id;
    private int    $userId;
    private int    $tripId;
    private string $type;
    private string $storedName;
    private string $visibility;
    private ?string $metadata;

    private const UPLOAD_DIR = __DIR__ . '/../../public/uploads/';

    public function __construct(array $row)
    {
        $this->id         = (int)$row['id'];
        $this->userId     = (int)$row['user_id'];
        $this->tripId     = (int)$row['trip_id'];
        $this->type       = $row['type'];
        $this->storedName = $row['stored_name'];
        $this->visibility = $row['visibility'];
        $this->metadata   = $row['metadata'] ?? null;
    }

    
    
    
    public static function upload(
        int $userId,
        int $tripId,
        string $type,
        string $originalName,
        string $tmpPath,
        string $visibility = 'private'
    ): self {
        $bytes      = file_get_contents($tmpPath);
        $encrypted  = Encryption::encryptFile($bytes, $userId); 
        $storedName = bin2hex(random_bytes(16)) . '.enc';

        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0750, true);
        }

        
        file_put_contents(self::UPLOAD_DIR . $storedName, $encrypted, LOCK_EX);

        $meta = Encryption::encryptJson([
            'original_name' => $originalName,
            'size'          => strlen($bytes),
        ], $userId);

        $db   = Database::getInstance('documents');
        $stmt = $db->prepare(
            'INSERT INTO documents (user_id, trip_id, type, stored_name, visibility, metadata)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $tripId, $type, $storedName, $visibility, $meta]);

        return new self([
            'id'          => (int)$db->lastInsertId(),
            'user_id'     => $userId,
            'trip_id'     => $tripId,
            'type'        => $type,
            'stored_name' => $storedName,
            'visibility'  => $visibility,
            'metadata'    => $meta,
        ]);
    }

    
    
    
    public function checkAccess(int $requesterId, string $requesterTripRole): bool
    {
        if ($requesterId === $this->userId) return true;
        if ($this->visibility === 'leader' && $requesterTripRole === 'leader') return true;
        return false;
    }

    
    
    
    public function getDecryptedBytes(): string
    {
        $raw = file_get_contents(self::UPLOAD_DIR . $this->storedName); 
        return Encryption::decryptFile($raw, $this->userId);
    }

    
    
    
    public function getMetadata(): array
    {
        if (!$this->metadata) return [];
        return Encryption::decryptJson($this->metadata, $this->userId);
    }

    
    
    
    public static function findByTrip(int $tripId, ?int $userId = null): array
    {
        $db   = Database::getInstance('documents');
        if ($userId) {
            $stmt = $db->prepare('SELECT * FROM documents WHERE trip_id = ? AND user_id = ?');
            $stmt->execute([$tripId, $userId]);
        } else {
            $stmt = $db->prepare('SELECT * FROM documents WHERE trip_id = ?');
            $stmt->execute([$tripId]);
        }
        return array_map(fn($r) => new self($r), $stmt->fetchAll());
    }

    public function delete(): bool
    {
        @unlink(self::UPLOAD_DIR . $this->storedName);
        $db   = Database::getInstance('documents');
        $stmt = $db->prepare('DELETE FROM documents WHERE id = ?');
        return $stmt->execute([$this->id]);
    }

    
    public function getId(): int           { return $this->id; }
    public function getUserId(): int       { return $this->userId; }
    public function getType(): string      { return $this->type; }
    public function getVisibility(): string { return $this->visibility; }
    public function getStoredName(): string { return $this->storedName; }
}
