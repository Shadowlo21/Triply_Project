<?php

class DocumentController implements IDocumentService
{
    public string $uploadStatus  = '';
    public array  $allowedTypes  = ['pdf', 'jpg', 'jpeg', 'png', 'docx'];

    public function uploadDocument(int $userId, int $tripId, array $file, string $visibility): bool
    {
        try {
            $type = $file['type'] ?? 'other';
            Document::upload(
                $userId,
                $tripId,
                $type,
                $file['name'],
                $file['tmp_name'],
                $visibility
            );
            $this->uploadStatus = 'ok';
            return true;
        } catch (\Throwable $e) {
            $this->uploadStatus = $e->getMessage();
            return false;
        }
    }

    public function deleteDocument(int $docId, int $requesterId): bool
    {
        $stmt = Database::getInstance('documents')->prepare('SELECT * FROM documents WHERE id = ?');
        $stmt->execute([$docId]);
        $row = $stmt->fetch();
        if (!$row) return false;
        if ((int)$row['user_id'] !== $requesterId) {
            $requester = User::findById($requesterId);
            if (!$requester || !($requester instanceof TripLeader)) return false;
        }
        return (new Document($row))->delete();
    }

    public function checkVisaRequirements(string $nationality, string $destination): bool
    {
        $rules = [
            'EG' => ['US', 'GB', 'DE', 'FR', 'IT', 'CA', 'AU', 'JP', 'CN', 'KR'],
            'US' => [],
            'GB' => ['CN'],
        ];
        $nat  = strtoupper(trim($nationality));
        $dest = strtoupper(trim($destination));
        return in_array($dest, $rules[$nat] ?? [], true);
    }

    public function verifyAccessRights(int $userId, int $docId): bool
    {
        $stmt = Database::getInstance('documents')->prepare('SELECT * FROM documents WHERE id = ?');
        $stmt->execute([$docId]);
        $row = $stmt->fetch();
        if (!$row) return false;

        $tripsDb   = Database::getInstance('trips');
        $roleStmt  = $tripsDb->prepare('SELECT role FROM trip_members WHERE trip_id = ? AND user_id = ?');
        $roleStmt->execute([$row['trip_id'], $userId]);
        $tripRole = $roleStmt->fetchColumn() ?: '';

        return (new Document($row))->checkAccess($userId, $tripRole);
    }

    public function downloadDocument(int $docId): void
    {
        $stmt = Database::getInstance('documents')->prepare('SELECT * FROM documents WHERE id = ?');
        $stmt->execute([$docId]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $doc      = new Document($row);
        $bytes    = $doc->getDecryptedBytes();
        $meta     = $doc->getMetadata();
        $filename = $meta['original_name'] ?? ('document_' . $docId);

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
    }
}
