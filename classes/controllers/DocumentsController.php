<?php

class DocumentsController
{
    public function handle(User $user, string $action): void
    {
        try {
            switch ($action) {

                case 'list':
                    $tripId = (int)($_GET['trip_id'] ?? 0);
                    if (!$tripId) ApiResponse::error('trip_id required.');
                    if (!$user->viewTrip($tripId)) ApiResponse::error('Access denied.', 403);

                    if ($user instanceof TripLeader) {
                        $docs = Document::findByTrip($tripId);
                    } else {
                        $docs = Document::findByTrip($tripId, $user->getId());
                    }

                    $result = array_map(fn($d) => [
                        'id'          => $d->getId(),
                        'user_id'     => $d->getUserId(),
                        'type'        => $d->getType(),
                        'visibility'  => $d->getVisibility(),
                        'metadata'    => $d->getMetadata(),
                        'uploaded_at' => $d->getUploadedAt(),
                    ], $docs);

                    ApiResponse::success($result);

                case 'upload':
                    $tripId     = (int)($_POST['trip_id'] ?? 0);
                    $type       = $_POST['type']       ?? 'other';
                    $visibility = $_POST['visibility'] ?? 'private';

                    if (!$tripId) ApiResponse::error('trip_id required.');
                    if (!$user->viewTrip($tripId)) ApiResponse::error('Access denied.', 403);
                    self::checkFileUpload('file');

                    if ($_FILES['file']['size'] > 10 * 1024 * 1024) {
                        ApiResponse::error('File too large. Max 10 MB.');
                    }

                    self::validateDocFile('file');

                    $doc = Document::upload(
                        $user->getId(),
                        $tripId,
                        $type,
                        $_FILES['file']['name'],
                        $_FILES['file']['tmp_name'],
                        $visibility
                    );

                    ApiResponse::success(['doc_id' => $doc->getId()], 'Document uploaded.');

                case 'download':
                    $docId = (int)($_GET['doc_id'] ?? 0);
                    if (!$docId) ApiResponse::error('doc_id required.');

                    $docsDb = Database::getInstance('documents');
                    $row    = $docsDb->prepare('SELECT * FROM documents WHERE id = ?');
                    $row->execute([$docId]);
                    $docRow = $row->fetch();
                    if (!$docRow) ApiResponse::error('Document not found.', 404);

                    $doc = new Document($docRow);

                    $tripsDb   = Database::getInstance('trips');
                    $memberRow = $tripsDb->prepare(
                        'SELECT role FROM trip_members WHERE trip_id = ? AND user_id = ?'
                    );
                    $memberRow->execute([$docRow['trip_id'], $user->getId()]);
                    $tripRole = $memberRow->fetchColumn() ?: '';

                    if (!$doc->checkAccess($user->getId(), $tripRole)) {
                        ApiResponse::error('Access denied.', 403);
                    }

                    $bytes    = $doc->getDecryptedBytes();
                    $meta     = $doc->getMetadata();
                    $filename = $meta['original_name'] ?? ('document_' . $docId);
                    self::serveFile($bytes, $filename);
                    exit;

                case 'download_profile':
                    $docId = (int)($_GET['doc_id'] ?? 0);
                    if (!$docId) ApiResponse::error('doc_id required.');

                    $docsDb = Database::getInstance('documents');
                    $row    = $docsDb->prepare('SELECT * FROM profile_documents WHERE id = ?');
                    $row->execute([$docId]);
                    $docRow = $row->fetch();
                    if (!$docRow) ApiResponse::error('Document not found.', 404);

                    $isOwner  = (int)$docRow['user_id'] === $user->getId();
                    $isAdmin  = $user->getRole() === 'admin';
                    $isLeader = $user->getRole() === 'leader';
                    if (!$isOwner && !$isAdmin && !$isLeader) {
                        ApiResponse::error('Access denied.', 403);
                    }

                    $ownerId  = (int)$docRow['user_id'];
                    $filePath = Document::resolvePath($ownerId, 'profile', $docRow['type'], $docRow['stored_name']);
                    $raw      = @file_get_contents($filePath);
                    if ($raw === false) ApiResponse::error('File not found on disk.', 404);

                    $bytes = Encryption::decryptFile($raw, $ownerId);
                    $meta  = [];
                    if ($docRow['metadata']) {
                        try { $meta = Encryption::decryptJson($docRow['metadata'], $ownerId); } catch (\Throwable $ignored) {}
                    }
                    $filename = $meta['original_name'] ?? ('profile_doc_' . $docId);
                    self::serveFile($bytes, $filename);
                    exit;

                case 'delete':
                    $docId = (int)($_POST['doc_id'] ?? 0);
                    if (!$docId) ApiResponse::error('doc_id required.');

                    $docsDb = Database::getInstance('documents');
                    $row    = $docsDb->prepare('SELECT * FROM documents WHERE id = ?');
                    $row->execute([$docId]);
                    $docRow = $row->fetch();
                    if (!$docRow) ApiResponse::error('Document not found.', 404);

                    if ((int)$docRow['user_id'] !== $user->getId() && !($user instanceof TripLeader)) {
                        ApiResponse::error('Access denied.', 403);
                    }

                    $doc = new Document($docRow);
                    $doc->delete();
                    ApiResponse::success(null, 'Document deleted.');

                case 'list_profile':
                    ApiResponse::success(Document::listProfile($user->getId()));

                case 'upload_profile':
                    $type = $_POST['type'] ?? 'other';
                    if (!in_array($type, ['passport', 'national_id', 'license', 'other'])) {
                        ApiResponse::error('Invalid document type.');
                    }
                    self::checkFileUpload('file');
                    if ($_FILES['file']['size'] > 10 * 1024 * 1024) ApiResponse::error('File too large. Max 10 MB.');

                    self::validateDocFile('file');

                    $doc = Document::uploadProfile(
                        $user->getId(), $type,
                        $_FILES['file']['name'], $_FILES['file']['tmp_name']
                    );
                    ApiResponse::success($doc, 'Document saved to profile.');

                case 'delete_profile':
                    $docId = (int)($_POST['doc_id'] ?? 0);
                    if (!$docId) ApiResponse::error('doc_id required.');
                    $ok = Document::deleteProfileDoc($docId, $user->getId());
                    if (!$ok) ApiResponse::error('Not found or access denied.', 404);
                    ApiResponse::success(null, 'Document deleted.');

                case 'verify_profile':
                    if (!in_array($user->getRole(), ['admin', 'leader'])) {
                        ApiResponse::error('Only admins and leaders can verify documents.', 403);
                    }
                    $docId  = (int)($_POST['doc_id'] ?? 0);
                    $status = $_POST['status'] ?? 'verified';
                    $note   = trim($_POST['note'] ?? '');
                    if (!$docId) ApiResponse::error('doc_id required.');
                    if (!in_array($status, ['verified', 'rejected', 'pending'])) ApiResponse::error('Invalid status.');
                    Document::reviewDoc($docId, $user->getId(), $status, $note);
                    ApiResponse::success(null, 'Document status updated to ' . $status . '.');

                case 'pending_docs':
                    if (!in_array($user->getRole(), ['admin', 'leader'])) {
                        ApiResponse::error('Access denied.', 403);
                    }
                    ApiResponse::success(Document::listPendingDocs());

                case 'list_member_docs':
                    if (!in_array($user->getRole(), ['admin', 'leader'])) {
                        ApiResponse::error('Access denied.', 403);
                    }
                    $targetId = (int)($_GET['user_id'] ?? 0);
                    if (!$targetId) ApiResponse::error('user_id required.');
                    ApiResponse::success(Document::listProfileForUser($targetId));

                case 'visa_check':
                    $nationality = strtoupper(trim($_GET['nationality'] ?? ''));
                    $destination = strtoupper(trim($_GET['destination'] ?? ''));
                    if (!$nationality || !$destination) {
                        ApiResponse::error('nationality and destination required.');
                    }

                    $rules = [
                        'EG' => ['US', 'GB', 'DE', 'FR', 'IT', 'CA', 'AU', 'JP', 'CN', 'KR'],
                        'US' => [],
                        'GB' => ['CN'],
                    ];

                    $needsVisa = in_array($destination, $rules[$nationality] ?? []);

                    ApiResponse::success([
                        'nationality'   => $nationality,
                        'destination'   => $destination,
                        'visa_required' => $needsVisa,
                        'note'          => $needsVisa
                            ? 'Visa required. Please check the official embassy website.'
                            : 'No visa required (verify before travel).',
                    ]);

                default:
                    ApiResponse::error('Unknown action.', 400);
            }
        } catch (\Throwable $e) {
            ApiResponse::error($e->getMessage());
        }
    }

    private static function checkFileUpload(string $field): void
    {
        $phpErrors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Server failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension.',
        ];
        if (empty($_FILES[$field])) ApiResponse::error('No file received.');
        $code = $_FILES[$field]['error'];
        if ($code !== UPLOAD_ERR_OK) {
            ApiResponse::error($phpErrors[$code] ?? "Upload failed (error code {$code}).");
        }
    }

    private static function validateDocFile(string $field): void
    {
        $allowedMap = [
            'pdf'  => ['application/pdf'],
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'docx' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
            ],
        ];

        $originalName = $_FILES[$field]['name'] ?? '';
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!isset($allowedMap[$ext])) {
            ApiResponse::error('Only PDF, JPG, PNG, and DOCX files are allowed.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($_FILES[$field]['tmp_name']);

        if (!in_array($mime, $allowedMap[$ext])) {
            ApiResponse::error('File content does not match its extension. Upload rejected.');
        }
    }

    private static function serveFile(string $bytes, string $filename): void
    {
        $ext     = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimeMap = [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        $ct     = $mimeMap[$ext] ?? 'application/octet-stream';
        $inline = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png']);

        header('Content-Type: ' . $ct);
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . addslashes($filename) . '"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
    }
}
