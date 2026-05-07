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
    private string $uploadedAt;

    private const UPLOAD_DIR = __DIR__ . '/../../storage/uploads/';

    public function __construct(array $row)
    {
        $this->id         = (int)$row['id'];
        $this->userId     = (int)$row['user_id'];
        $this->tripId     = (int)$row['trip_id'];
        $this->type       = $row['type'];
        $this->storedName = $row['stored_name'];
        $this->visibility = $row['visibility'];
        $this->metadata   = $row['metadata'] ?? null;
        $this->uploadedAt = $row['uploaded_at'] ?? '';
    }

    private static function dirFor(int $userId, string $kind, string $type): string
    {
        $safeKind = preg_replace('/[^a-z_]/', '', strtolower($kind)) ?: 'misc';
        $safeType = preg_replace('/[^a-z0-9_]/', '', strtolower($type)) ?: 'other';
        $dir = self::UPLOAD_DIR . $userId . '/' . $safeKind . '/' . $safeType . '/';
        if (!is_dir($dir)) mkdir($dir, 0750, true);
        return $dir;
    }

    public static function resolvePath(int $userId, string $kind, string $type, string $storedName): string
    {
        $newPath = self::dirFor($userId, $kind, $type) . $storedName;
        if (is_file($newPath)) return $newPath;
        $legacy = self::UPLOAD_DIR . $storedName;
        return is_file($legacy) ? $legacy : $newPath;
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

        file_put_contents(self::dirFor($userId, 'trip', $type) . $storedName, $encrypted, LOCK_EX);

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
            'uploaded_at' => date('Y-m-d H:i:s'),
        ]);
    }

    
    
    
    public function checkAccess(int $requesterId, string $requesterTripRole): bool
    {
        if ($requesterId === $this->userId) return true;
        if ($this->visibility === 'group' && $requesterTripRole !== '') return true;
        if ($this->visibility === 'private' && $requesterTripRole === 'leader') return true;
        return false;
    }

    
    
    
    public function getDecryptedBytes(): string
    {
        $path = self::resolvePath($this->userId, 'trip', $this->type, $this->storedName);
        $raw  = file_get_contents($path);
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
        @unlink(self::resolvePath($this->userId, 'trip', $this->type, $this->storedName));
        @unlink(self::UPLOAD_DIR . $this->storedName);
        $db   = Database::getInstance('documents');
        $stmt = $db->prepare('DELETE FROM documents WHERE id = ?');
        return $stmt->execute([$this->id]);
    }

    
    public function getId(): int            { return $this->id; }
    public function getUserId(): int        { return $this->userId; }
    public function getType(): string       { return $this->type; }
    public function getVisibility(): string { return $this->visibility; }
    public function getStoredName(): string { return $this->storedName; }
    public function getUploadedAt(): string { return $this->uploadedAt; }

    // ── Profile Documents ────────────────────────────────────────────────────

    public static function uploadProfile(
        int $userId, string $type, string $originalName, string $tmpPath
    ): array {
        $bytes      = file_get_contents($tmpPath);
        $encrypted  = Encryption::encryptFile($bytes, $userId);
        $storedName = bin2hex(random_bytes(16)) . '.enc';

        file_put_contents(self::dirFor($userId, 'profile', $type) . $storedName, $encrypted, LOCK_EX);

        $meta = Encryption::encryptJson(['original_name' => $originalName, 'size' => strlen($bytes)], $userId);

        $db   = Database::getInstance('documents');
        $db->prepare('INSERT INTO profile_documents (user_id, type, stored_name, metadata) VALUES (?,?,?,?)')
           ->execute([$userId, $type, $storedName, $meta]);

        return [
            'id'            => (int)$db->lastInsertId(),
            'type'          => $type,
            'is_verified'   => 0,
            'uploaded_at'   => date('Y-m-d H:i:s'),
            'original_name' => $originalName,
        ];
    }

    public static function listProfile(int $userId): array
    {
        $db   = Database::getInstance('documents');
        $stmt = $db->prepare('SELECT * FROM profile_documents WHERE user_id = ? ORDER BY uploaded_at DESC');
        $stmt->execute([$userId]);
        return array_map([self::class, 'formatProfileRow'], $stmt->fetchAll());
    }

    private static function formatProfileRow(array $r): array
    {
        $meta = [];
        if ($r['metadata']) {
            try { $meta = Encryption::decryptJson($r['metadata'], (int)$r['user_id']); } catch (\Throwable $ignored) {}
        }
        return [
            'id'            => (int)$r['id'],
            'user_id'       => (int)$r['user_id'],
            'type'          => $r['type'],
            'status'        => $r['status'] ?? 'pending',
            'review_note'   => $r['review_note'] ?? '',
            'reviewed_by'   => $r['reviewed_by'] ?? null,
            'uploaded_at'   => $r['uploaded_at'],
            'original_name' => $meta['original_name'] ?? '',
        ];
    }

    public static function listProfileForUser(int $targetUserId): array
    {
        return self::listProfile($targetUserId);
    }

    public static function listPendingDocs(): array
    {
        $db   = Database::getInstance('documents');
        $stmt = $db->query("SELECT * FROM profile_documents WHERE status = 'pending' ORDER BY uploaded_at ASC");
        return array_map([self::class, 'formatProfileRow'], $stmt->fetchAll());
    }

    public static function deleteProfileDoc(int $docId, int $userId): bool
    {
        $db   = Database::getInstance('documents');
        $stmt = $db->prepare('SELECT stored_name, type FROM profile_documents WHERE id = ? AND user_id = ?');
        $stmt->execute([$docId, $userId]);
        $row  = $stmt->fetch();
        if (!$row) return false;
        @unlink(self::resolvePath($userId, 'profile', $row['type'], $row['stored_name']));
        @unlink(self::UPLOAD_DIR . $row['stored_name']);
        $db->prepare('DELETE FROM profile_documents WHERE id = ?')->execute([$docId]);
        return true;
    }

    public static function hasVerifiedDoc(int $userId, string $type): bool
    {
        $stmt = Database::getInstance('documents')
            ->prepare("SELECT 1 FROM profile_documents WHERE user_id = ? AND type = ? AND status = 'verified'");
        $stmt->execute([$userId, $type]);
        return (bool)$stmt->fetchColumn();
    }

    public static function reviewDoc(int $docId, int $reviewedBy, string $status, string $note = ''): void
    {
        Database::getInstance('documents')
            ->prepare('UPDATE profile_documents SET status = ?, reviewed_by = ?, review_note = ? WHERE id = ?')
            ->execute([$status, $reviewedBy, $note, $docId]);
    }
}
