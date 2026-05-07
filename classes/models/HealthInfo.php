<?php

class HealthInfo
{
    public int    $userId;
    public array  $healthData    = [];
    public string $lastUpdated   = '';
    public bool   $isSensitive   = true;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    public static function forUser(int $userId): self
    {
        $info = new self($userId);
        $stmt = Database::getInstance('accounts')->prepare('SELECT data, last_updated, is_sensitive FROM health_info WHERE user_id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if ($row) {
            $info->lastUpdated = $row['last_updated'] ?? '';
            $info->isSensitive = (bool)($row['is_sensitive'] ?? 1);
            if (!empty($row['data'])) {
                try {
                    $info->healthData = Encryption::decryptJson($row['data'], $userId);
                } catch (\Throwable $ignored) {
                    $info->healthData = [];
                }
            }
        }
        return $info;
    }

    public function createOrUpdate(array $records): void
    {
        $this->healthData  = $records;
        $this->lastUpdated = date('Y-m-d H:i:s');
        $encrypted = Encryption::encryptJson($records, $this->userId);

        $db   = Database::getInstance('accounts');
        $stmt = $db->prepare('SELECT id FROM health_info WHERE user_id = ?');
        $stmt->execute([$this->userId]);
        if ($stmt->fetchColumn()) {
            $db->prepare("UPDATE health_info SET data = ?, last_updated = datetime('now'), is_sensitive = ? WHERE user_id = ?")
               ->execute([$encrypted, $this->isSensitive ? 1 : 0, $this->userId]);
        } else {
            $db->prepare('INSERT INTO health_info (user_id, data, is_sensitive) VALUES (?, ?, ?)')
               ->execute([$this->userId, $encrypted, $this->isSensitive ? 1 : 0]);
        }
    }

    public function markAsSensitive(): void
    {
        $this->isSensitive = true;
        Database::getInstance('accounts')
            ->prepare('UPDATE health_info SET is_sensitive = 1 WHERE user_id = ?')
            ->execute([$this->userId]);
    }
}
