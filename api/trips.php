<?php

require_once __DIR__ . '/../config/bootstrap.php';

$user   = Auth::require();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {


        case 'list':
            $db   = Database::getInstance('trips');
            $stmt = $db->prepare(
                "SELECT t.*, tm.role AS my_role, tm.can_edit, tm.status AS my_status,
                        (SELECT COUNT(*) FROM trip_members tm2
                         WHERE tm2.trip_id = t.id AND tm2.status = 'accepted') AS member_count
                 FROM trips t
                 LEFT JOIN trip_members tm ON tm.trip_id = t.id AND tm.user_id = ?
                 ORDER BY t.start_date DESC"
            );
            $stmt->execute([$user->getId()]);
            $trips = $stmt->fetchAll();

            // Attach creator names (decrypt from accounts DB)
            if ($trips) {
                $creatorIds  = array_unique(array_column($trips, 'created_by'));
                $accountsDb  = Database::getInstance('accounts');
                $ph          = implode(',', array_fill(0, count($creatorIds), '?'));
                $usersStmt   = $accountsDb->prepare("SELECT id, data FROM users WHERE id IN ({$ph})");
                $usersStmt->execute($creatorIds);
                $creatorNames = [];
                foreach ($usersStmt->fetchAll() as $u) {
                    try {
                        $d = Encryption::decryptJson($u['data'], (int)$u['id']);
                        $creatorNames[$u['id']] = $d['name'] ?? '';
                    } catch (\Throwable $ignored) {}
                }
                foreach ($trips as &$t) {
                    $t['creator_name'] = $creatorNames[$t['created_by']] ?? '';
                }
                unset($t);
            }
            ApiResponse::success($trips);


        case 'pending_invites':
            $db   = Database::getInstance('trips');
            $stmt = $db->prepare(
                "SELECT t.id, t.title, t.destination, t.start_date, t.end_date
                 FROM trips t
                 JOIN trip_members tm ON tm.trip_id = t.id
                 WHERE tm.user_id = ? AND tm.status = 'pending'
                 ORDER BY tm.joined_at DESC"
            );
            $stmt->execute([$user->getId()]);
            ApiResponse::success($stmt->fetchAll());


        case 'accept_invite':
            $tripId = (int)($_POST['trip_id'] ?? 0);
            if (!$tripId) ApiResponse::error('trip_id required.');

            $trip = Trip::findById($tripId);

            // Check max slots
            if ($trip && $trip->getMaxSlots() !== null) {
                if ($trip->getAcceptedMemberCount() >= $trip->getMaxSlots()) {
                    ApiResponse::error('This trip is full (' . $trip->getMaxSlots() . ' slots).');
                }
            }

            // Check required documents
            if ($trip) {
                foreach ($trip->getRequiredDocs() as $docType) {
                    if (!Document::hasVerifiedDoc($user->getId(), $docType)) {
                        $label = ['passport' => 'Passport', 'national_id' => 'National ID', 'license' => 'Driver\'s License'][$docType] ?? ucfirst($docType);
                        ApiResponse::error("This trip requires a verified {$label}. Please upload it in your Profile first.", 403);
                    }
                }
            }

            $db   = Database::getInstance('trips');
            $stmt = $db->prepare(
                "UPDATE trip_members SET status = 'accepted' WHERE trip_id = ? AND user_id = ? AND status = 'pending'"
            );
            $stmt->execute([$tripId, $user->getId()]);
            if ($stmt->rowCount() === 0) ApiResponse::error('No pending invite found.');
            ApiResponse::success(null, 'You have joined the trip!');


        case 'decline_invite':
            $tripId = (int)($_POST['trip_id'] ?? 0);
            if (!$tripId) ApiResponse::error('trip_id required.');
            Database::getInstance('trips')
                ->prepare("DELETE FROM trip_members WHERE trip_id = ? AND user_id = ? AND status = 'pending'")
                ->execute([$tripId, $user->getId()]);
            ApiResponse::success(null, 'Invite declined.');


        case 'leave':
            $tripId = (int)($_POST['trip_id'] ?? 0);
            if (!$tripId) ApiResponse::error('trip_id required.');
            if ($user instanceof Member && $user->isTripLeader($tripId)) {
                ApiResponse::error('Trip leaders cannot leave. Transfer leadership or cancel the trip.');
            }
            $db   = Database::getInstance('trips');
            $stmt = $db->prepare(
                "DELETE FROM trip_members WHERE trip_id = ? AND user_id = ? AND status = 'accepted'"
            );
            $stmt->execute([$tripId, $user->getId()]);
            if ($stmt->rowCount() === 0) ApiResponse::error('You are not a member of this trip.');
            ApiResponse::success(null, 'You have left the trip.');


        case 'cancel':
            $tripId      = (int)($_POST['trip_id'] ?? 0);
            if (!$tripId) ApiResponse::error('trip_id required.');

            $isAdmin      = $user->getRole() === 'admin';
            $isTripLeader = $user instanceof Member && $user->isTripLeader($tripId);
            if (!$isAdmin && !$isTripLeader) {
                ApiResponse::error('Only the trip leader or an admin can cancel a trip.', 403);
            }

            $tripsDb   = Database::getInstance('trips');
            $titleStmt = $tripsDb->prepare('SELECT title FROM trips WHERE id = ?');
            $titleStmt->execute([$tripId]);
            $tripTitle = $titleStmt->fetchColumn();
            if (!$tripTitle) ApiResponse::error('Trip not found.', 404);

            // Collect all accepted members before deletion
            $memStmt = $tripsDb->prepare(
                "SELECT user_id FROM trip_members WHERE trip_id = ? AND status = 'accepted'"
            );
            $memStmt->execute([$tripId]);
            $memberIds = array_column($memStmt->fetchAll(), 'user_id');

            // Build display name e.g. "Shadow(Admin)"
            $name    = $user->getName() ?: $user->getEmail();
            $roleTag = $isAdmin ? 'Admin' : 'Leader';
            $byLine  = "{$name}({$roleTag})";

            // Notify all members except the canceller
            foreach ($memberIds as $mid) {
                if ((int)$mid !== $user->getId()) {
                    Notification::send(
                        (int)$mid,
                        'announcement',
                        "Trip \"{$tripTitle}\" has been cancelled by {$byLine}.",
                        'Trip Cancelled'
                    );
                }
            }

            // Clean up financial DB
            $finDb = Database::getInstance('financial');
            $finDb->prepare('DELETE FROM expenses WHERE trip_id = ?')->execute([$tripId]);
            $finDb->prepare('DELETE FROM settlements WHERE trip_id = ?')->execute([$tripId]);

            // Clean up social DB
            Database::getInstance('social')
                ->prepare('DELETE FROM polls WHERE trip_id = ?')
                ->execute([$tripId]);

            // Clean up documents (files + DB rows)
            $docsDb   = Database::getInstance('documents');
            $docsStmt = $docsDb->prepare('SELECT stored_name FROM documents WHERE trip_id = ?');
            $docsStmt->execute([$tripId]);
            $uploadDir = __DIR__ . '/../public/uploads/';
            foreach ($docsStmt->fetchAll() as $doc) {
                @unlink($uploadDir . $doc['stored_name']);
            }
            $docsDb->prepare('DELETE FROM documents WHERE trip_id = ?')->execute([$tripId]);

            // Delete the trip — CASCADE handles trip_members, activities, attendance,
            // itinerary_versions, comments, shared_items within trips.db
            $tripsDb->prepare('DELETE FROM trips WHERE id = ?')->execute([$tripId]);

            ApiResponse::success(null, "Trip \"{$tripTitle}\" has been cancelled.");


        case 'get':
            $tripId = (int)($_GET['trip_id'] ?? 0);
            if (!$tripId) ApiResponse::error('trip_id required.');
            $trip = $user->viewTrip($tripId);
            if (!$trip) ApiResponse::error('Trip not found or access denied.', 404);
            ApiResponse::success($trip);


        case 'create':
            if ($user->getRole() === 'member') {
                ApiResponse::error('Only leaders and admins can create trips.', 403);
            }
            if (!($user instanceof TripLeader)) {
                $leader = new TripLeader($user->getId(), $user->getEmail(), $user->getRole());
                $leader->decryptData(
                    Database::getInstance('accounts')
                        ->query("SELECT data FROM users WHERE id = {$user->getId()}")
                        ->fetchColumn()
                );
                $user = $leader;
            }

            $required = ['title', 'destination', 'start_date', 'end_date'];
            foreach ($required as $f) {
                if (empty($_POST[$f])) ApiResponse::error("Missing field: {$f}");
            }

            $tripId = $user->createTrip([
                'title'           => trim($_POST['title']),
                'destination'     => trim($_POST['destination']),
                'start_date'      => $_POST['start_date'],
                'end_date'        => $_POST['end_date'],
                'base_currency'   => $_POST['base_currency']   ?? 'EGP',
                'budget_limit'    => !empty($_POST['budget_limit'])    ? (float)$_POST['budget_limit']    : null,
                'max_slots'       => !empty($_POST['max_slots'])       ? (int)$_POST['max_slots']         : 20,
                'departure_point' => !empty($_POST['departure_point']) ? trim($_POST['departure_point'])  : null,
                'departure_time'  => !empty($_POST['departure_time'])  ? trim($_POST['departure_time'])   : null,
            ]);

            // Optional required docs
            $reqDocs = array_filter(array_intersect(
                $_POST['required_docs'] ?? [],
                ['passport', 'national_id', 'license']
            ));
            if (!empty($reqDocs)) {
                Database::getInstance('trips')
                    ->prepare('UPDATE trips SET required_docs = ? WHERE id = ?')
                    ->execute([json_encode(array_values($reqDocs)), $tripId]);
            }

            ApiResponse::success(['trip_id' => $tripId], 'Trip created.');


        case 'members':
            $tripId = (int)($_GET['trip_id'] ?? 0);
            if (!$tripId) ApiResponse::error('trip_id required.');
            // Admins can always see members; others must be in the trip (any status)
            if ($user->getRole() !== 'admin') {
                $chk = Database::getInstance('trips')
                    ->prepare('SELECT 1 FROM trip_members WHERE trip_id = ? AND user_id = ?');
                $chk->execute([$tripId, $user->getId()]);
                if (!$chk->fetchColumn()) ApiResponse::error('Access denied.', 403);
            }
            ApiResponse::success(Trip::findById($tripId)->getMembers());


        case 'invite':
            $tripId = (int)($_POST['trip_id'] ?? 0);
            $email  = trim($_POST['email'] ?? '');
            if (!$tripId || !$email) ApiResponse::error('trip_id and email required.');
            $isAdmin = $user->getRole() === 'admin';
            if (!$isAdmin && !$user->viewTrip($tripId)) ApiResponse::error('Access denied.', 403);
            if (!$isAdmin && !($user instanceof Member)) ApiResponse::error('Not allowed.');

            $trip = Trip::findById($tripId);
            if ($trip->getMaxSlots() !== null &&
                $trip->getAcceptedMemberCount() >= $trip->getMaxSlots()) {
                ApiResponse::error('Trip is full (' . $trip->getMaxSlots() . ' max slots).');
            }

            $ok = $user->inviteUser($email, $tripId);
            if ($ok) {
                $invitee = Database::getInstance('accounts')->prepare('SELECT id FROM users WHERE email = ?');
                $invitee->execute([$email]);
                $inviteeId = (int)$invitee->fetchColumn();
                if ($inviteeId) Notification::tripInvite($inviteeId, $trip->getTitle(), $user->getName());
            }
            ApiResponse::success(null, $ok ? 'Invited.' : 'User not found or already a member.');


        case 'set_permission':
            $tripId  = (int)($_POST['trip_id'] ?? 0);
            $userId  = (int)($_POST['user_id'] ?? 0);
            $canEdit = (int)($_POST['can_edit'] ?? 0);
            if (!$tripId || !$userId) ApiResponse::error('trip_id and user_id required.');
            if (!($user instanceof Member) || !$user->isTripLeader($tripId)) {
                ApiResponse::error('Only the leader of this trip can change permissions.', 403);
            }
            ApiResponse::success(null,
                $user->editPermission($tripId, $userId, (bool)$canEdit) ? 'Permission updated.' : 'Failed.'
            );


        case 'set_budget':
            $tripId = (int)($_POST['trip_id'] ?? 0);
            $limit  = (float)($_POST['budget_limit'] ?? 0);
            if (!$tripId || $limit <= 0) ApiResponse::error('trip_id and budget_limit required.');
            if (!($user instanceof Member) || !$user->isTripLeader($tripId)) {
                ApiResponse::error('Only the leader of this trip can set budget.', 403);
            }
            ApiResponse::success(null,
                $user->setBudgetLimit($tripId, $limit) ? 'Budget set.' : 'Failed.'
            );


        case 'close':
            $tripId = (int)($_POST['trip_id'] ?? 0);
            if (!$tripId) ApiResponse::error('trip_id required.');
            if (!($user instanceof Member) || !$user->isTripLeader($tripId)) {
                ApiResponse::error('Only the leader of this trip can close it.', 403);
            }
            ApiResponse::success(null,
                $user->closeTrip($tripId) ? 'Trip settled.' : 'Failed.'
            );


        case 'update_status':
            $tripId = (int)($_POST['trip_id'] ?? 0);
            if (!$tripId) ApiResponse::error('trip_id required.');

            $isAdmin      = $user->getRole() === 'admin';
            $isTripLeader = $user instanceof Member && $user->isTripLeader($tripId);
            if (!$isAdmin && !$isTripLeader) {
                ApiResponse::error('Only the trip leader or an admin can update status.', 403);
            }

            $status       = $_POST['status'] ?? '';
            $validStatuses = ['planning', 'active', 'completed', 'settled'];
            if (!in_array($status, $validStatuses)) ApiResponse::error('Invalid status.');

            Database::getInstance('trips')
                ->prepare('UPDATE trips SET status = ? WHERE id = ?')
                ->execute([$status, $tripId]);
            ApiResponse::success(null, 'Status updated to ' . $status . '.');


        case 'set_required_docs':
            $tripId = (int)($_POST['trip_id'] ?? 0);
            if (!$tripId) ApiResponse::error('trip_id required.');

            $isAdmin      = $user->getRole() === 'admin';
            $isTripLeader = $user instanceof Member && $user->isTripLeader($tripId);
            if (!$isAdmin && !$isTripLeader) {
                ApiResponse::error('Only the trip leader or an admin can set requirements.', 403);
            }

            $reqDocs = array_values(array_filter(array_intersect(
                $_POST['required_docs'] ?? [],
                ['passport', 'national_id', 'license']
            )));

            Database::getInstance('trips')
                ->prepare('UPDATE trips SET required_docs = ? WHERE id = ?')
                ->execute([empty($reqDocs) ? null : json_encode($reqDocs), $tripId]);

            ApiResponse::success(null, 'Requirements updated.');


        default:
            ApiResponse::error('Unknown action.', 400);
    }
} catch (\Throwable $e) {
    ApiResponse::error($e->getMessage());
}
