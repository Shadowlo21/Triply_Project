<?php

require_once __DIR__ . '/../config/bootstrap.php';

$user   = Auth::require();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

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
            if (empty($_FILES['file'])) ApiResponse::error('No file uploaded.');
            if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) ApiResponse::error('Upload error.');


            if ($_FILES['file']['size'] > 10 * 1024 * 1024) {
                ApiResponse::error('File too large. Max 10 MB.');
            }

            $allowed = ['application/pdf', 'image/jpeg', 'image/png'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime    = $finfo->file($_FILES['file']['tmp_name']);
            if (!in_array($mime, $allowed)) {
                ApiResponse::error('Only PDF and images allowed.');
            }

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

            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
            header('Content-Length: ' . strlen($bytes));
            echo $bytes;
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
            if (empty($_FILES['file'])) ApiResponse::error('No file uploaded.');
            if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) ApiResponse::error('Upload error.');
            if ($_FILES['file']['size'] > 10 * 1024 * 1024) ApiResponse::error('File too large. Max 10 MB.');

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($_FILES['file']['tmp_name']);
            if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'])) {
                ApiResponse::error('Only PDF and images allowed.');
            }

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
            $docId      = (int)($_POST['doc_id'] ?? 0);
            $doVerify   = ($_POST['verified'] ?? '1') === '1';
            if (!$docId) ApiResponse::error('doc_id required.');
            $doVerify ? Document::verifyDoc($docId, $user->getId()) : Document::unverifyDoc($docId);
            ApiResponse::success(null, $doVerify ? 'Document verified.' : 'Verification removed.');


        case 'list_member_docs':
            // Leader or admin views a specific user's profile docs (for verification)
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
                'nationality' => $nationality,
                'destination' => $destination,
                'visa_required' => $needsVisa,
                'note' => $needsVisa
                    ? 'Visa required. Please check the official embassy website.'
                    : 'No visa required (verify before travel).',
            ]);

        default:
            ApiResponse::error('Unknown action.', 400);
    }
} catch (\Throwable $e) {
    ApiResponse::error($e->getMessage());
}
