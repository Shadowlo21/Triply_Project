<?php

class TripsController
{
    public function handle(User $user, string $action): void
    {
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

                    if ($trips) {
                        $creatorIds  = array_values(array_unique(array_column($trips, 'created_by')));
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

                case 'join':
                    $tripId = (int)($_POST['trip_id'] ?? 0);
                    if (!$tripId) ApiResponse::error('trip_id required.');

                    $trip = Trip::findById($tripId);
                    if (!$trip) ApiResponse::error('Trip not found.', 404);

                    $db = Database::getInstance('trips');
                    $existing = $db->prepare('SELECT status FROM trip_members WHERE trip_id = ? AND user_id = ?');
                    $existing->execute([$tripId, $user->getId()]);
                    $existingStatus = $existing->fetchColumn();
                    if ($existingStatus === 'accepted') ApiResponse::error('You are already a member of this trip.');

                    if ($trip->getMaxSlots() !== null && $trip->getAcceptedMemberCount() >= $trip->getMaxSlots()) {
                        ApiResponse::error('This trip is full (' . $trip->getMaxSlots() . ' slots).');
                    }

                    if (!in_array($user->getRole(), ['admin', 'leader'])) {
                        foreach ($trip->getRequiredDocs() as $docType) {
                            if (!Document::hasVerifiedDoc($user->getId(), $docType)) {
                                $label = ['passport' => 'Passport', 'national_id' => 'National ID', 'license' => 'Driver\'s License'][$docType] ?? ucfirst($docType);
                                ApiResponse::error("This trip requires a verified {$label}. Please upload it in your Profile first.", 403);
                            }
                        }
                    }

                    if ($existingStatus === 'pending') {
                        $db->prepare("UPDATE trip_members SET status = 'accepted' WHERE trip_id = ? AND user_id = ?")
                           ->execute([$tripId, $user->getId()]);
                    } else {
                        $db->prepare("INSERT INTO trip_members (trip_id, user_id, role, status, can_edit) VALUES (?, ?, 'member', 'accepted', 0)")
                           ->execute([$tripId, $user->getId()]);
                    }

                    $leaderId = (int)$trip->getCreatedBy();
                    if ($leaderId && $leaderId !== $user->getId()) {
                        Notification::send(
                            $leaderId,
                            'announcement',
                            ($user->getName() ?: $user->getEmail()) . ' joined trip "' . $trip->getTitle() . '".',
                            'New Member Joined'
                        );
                    }

                    ApiResponse::success(null, 'You have joined the trip!');

                case 'accept_invite':
                    $tripId = (int)($_POST['trip_id'] ?? 0);
                    if (!$tripId) ApiResponse::error('trip_id required.');

                    $trip = Trip::findById($tripId);

                    if ($trip && $trip->getMaxSlots() !== null) {
                        if ($trip->getAcceptedMemberCount() >= $trip->getMaxSlots()) {
                            ApiResponse::error('This trip is full (' . $trip->getMaxSlots() . ' slots).');
                        }
                    }

                    if ($trip && !in_array($user->getRole(), ['admin', 'leader'])) {
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
                    $tripId = (int)($_POST['trip_id'] ?? 0);
                    if (!$tripId) ApiResponse::error('trip_id required.');

                    $isAdmin      = $user->getRole() === 'admin';
                    $isLeader     = $user->getRole() === 'leader';
                    $isTripLeader = $user instanceof Member && $user->isTripLeader($tripId);
                    if (!$isAdmin && !$isLeader && !$isTripLeader) {
                        ApiResponse::error('Only leaders or an admin can cancel a trip.', 403);
                    }

                    $tripsDb   = Database::getInstance('trips');
                    $titleStmt = $tripsDb->prepare('SELECT title FROM trips WHERE id = ?');
                    $titleStmt->execute([$tripId]);
                    $tripTitle = $titleStmt->fetchColumn();
                    if (!$tripTitle) ApiResponse::error('Trip not found.', 404);

                    $memStmt = $tripsDb->prepare(
                        "SELECT user_id FROM trip_members WHERE trip_id = ? AND status = 'accepted'"
                    );
                    $memStmt->execute([$tripId]);
                    $memberIds = array_column($memStmt->fetchAll(), 'user_id');

                    $name    = $user->getName() ?: $user->getEmail();
                    $roleTag = $isAdmin ? 'Admin' : ($isTripLeader ? 'Trip Leader' : 'Leader');
                    $byLine  = "{$name}({$roleTag})";

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

                    $finDb = Database::getInstance('financial');
                    $finDb->prepare('DELETE FROM expenses WHERE trip_id = ?')->execute([$tripId]);
                    $finDb->prepare('DELETE FROM settlements WHERE trip_id = ?')->execute([$tripId]);

                    Database::getInstance('social')
                        ->prepare('DELETE FROM polls WHERE trip_id = ?')
                        ->execute([$tripId]);

                    $docsDb   = Database::getInstance('documents');
                    $docsStmt = $docsDb->prepare('SELECT user_id, type, stored_name FROM documents WHERE trip_id = ?');
                    $docsStmt->execute([$tripId]);
                    foreach ($docsStmt->fetchAll() as $doc) {
                        @unlink(Document::resolvePath((int)$doc['user_id'], 'trip', $doc['type'], $doc['stored_name']));
                    }
                    $docsDb->prepare('DELETE FROM documents WHERE trip_id = ?')->execute([$tripId]);

                    $tripsDb->prepare('DELETE FROM trips WHERE id = ?')->execute([$tripId]);

                    ApiResponse::success(null, "Trip \"{$tripTitle}\" has been cancelled.");

                case 'get':
                    $tripId = (int)($_GET['trip_id'] ?? 0);
                    if (!$tripId) ApiResponse::error('trip_id required.');
                    $trip = $user->viewTrip($tripId);
                    if (!$trip) ApiResponse::error('Trip not found or access denied.', 404);
                    ApiResponse::success($trip);

                case 'create':
                    if ($user->getRole() !== 'leader') {
                        ApiResponse::error('Only leaders can create trips.', 403);
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

                    $title       = substr(trim(strip_tags($_POST['title'])), 0, 120);
                    $destination = substr(trim(strip_tags($_POST['destination'])), 0, 120);
                    if ($title === '' || $destination === '') {
                        ApiResponse::error('Title and destination must contain plain text.');
                    }

                    $tripId = $user->createTrip([
                        'title'           => $title,
                        'destination'     => $destination,
                        'start_date'      => $_POST['start_date'],
                        'end_date'        => $_POST['end_date'],
                        'base_currency'   => $_POST['base_currency']   ?? 'EGP',
                        'budget_limit'    => !empty($_POST['budget_limit'])    ? (float)$_POST['budget_limit']    : null,
                        'max_slots'       => !empty($_POST['max_slots'])       ? (int)$_POST['max_slots']         : 20,
                        'departure_point' => !empty($_POST['departure_point']) ? substr(trim(strip_tags($_POST['departure_point'])), 0, 200) : null,
                        'departure_time'  => !empty($_POST['departure_time'])  ? trim($_POST['departure_time'])   : null,
                    ]);

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

                case 'invite_all':
                    $tripId = (int)($_POST['trip_id'] ?? 0);
                    if (!$tripId) ApiResponse::error('trip_id required.');

                    $canManage = in_array($user->getRole(), ['admin', 'leader'])
                        || ($user instanceof Member && $user->isTripLeader($tripId));
                    if (!$canManage) ApiResponse::error('Only leaders or an admin can invite all members.', 403);

                    $trip = Trip::findById($tripId);
                    if (!$trip) ApiResponse::error('Trip not found.', 404);

                    $tripsDb = Database::getInstance('trips');
                    $coolStmt = $tripsDb->prepare('SELECT last_invite_all_at FROM trips WHERE id = ?');
                    $coolStmt->execute([$tripId]);
                    $lastAt = $coolStmt->fetchColumn();
                    if ($lastAt) {
                        $elapsed = time() - strtotime($lastAt);
                        if ($elapsed < 3600) {
                            $waitMin = (int)ceil((3600 - $elapsed) / 60);
                            ApiResponse::error("Invite-all was used recently on this trip. Try again in {$waitMin} minute(s).", 429);
                        }
                    }

                    $accountsDb = Database::getInstance('accounts');
                    $accountsDb->exec("DELETE FROM rate_events WHERE datetime(event_at) < datetime('now', '-1 day')");
                    $userScope = 'invite_all:user:' . $user->getId();
                    $cnt = $accountsDb->prepare(
                        "SELECT COUNT(*) FROM rate_events WHERE scope = ? AND datetime(event_at) >= datetime('now', '-1 day')"
                    );
                    $cnt->execute([$userScope]);
                    if ((int)$cnt->fetchColumn() >= 10) {
                        ApiResponse::error('Daily invite-all limit reached (10 / 24h). Try again tomorrow.', 429);
                    }
                    $existing = $tripsDb->prepare('SELECT user_id FROM trip_members WHERE trip_id = ?');
                    $existing->execute([$tripId]);
                    $existingIds = array_map('intval', array_column($existing->fetchAll(), 'user_id'));

                    $allUsers = Database::getInstance('accounts')->query('SELECT id FROM users')->fetchAll();
                    $invited  = 0;
                    $insert   = $tripsDb->prepare(
                        "INSERT INTO trip_members (trip_id, user_id, role, status, can_edit) VALUES (?, ?, 'member', 'pending', 0)"
                    );
                    $tripTitle   = $trip->getTitle();
                    $inviterName = $user->getName() ?: $user->getEmail();

                    foreach ($allUsers as $u) {
                        $uid = (int)$u['id'];
                        if (in_array($uid, $existingIds, true) || $uid === $user->getId()) continue;
                        try {
                            $insert->execute([$tripId, $uid]);
                            Notification::tripInvite($uid, $tripTitle, $inviterName);
                            $invited++;
                        } catch (\Throwable $ignored) {}
                    }

                    $tripsDb->prepare("UPDATE trips SET last_invite_all_at = datetime('now') WHERE id = ?")
                        ->execute([$tripId]);
                    $accountsDb->prepare('INSERT INTO rate_events (scope) VALUES (?)')->execute([$userScope]);

                    ApiResponse::success(['invited' => $invited], "Invited {$invited} user(s).");

                case 'set_permission':
                    $tripId  = (int)($_POST['trip_id'] ?? 0);
                    $userId  = (int)($_POST['user_id'] ?? 0);
                    $canEdit = (int)($_POST['can_edit'] ?? 0);
                    if (!$tripId || !$userId) ApiResponse::error('trip_id and user_id required.');
                    $canManage = $user->getRole() === 'admin'
                        || ($user instanceof Member && $user->isTripLeader($tripId));
                    if (!$canManage) {
                        ApiResponse::error('Only the trip leader or an admin can change permissions.', 403);
                    }
                    ApiResponse::success(null,
                        $user->editPermission($tripId, $userId, (bool)$canEdit) ? 'Permission updated.' : 'Failed.'
                    );

                case 'update':
                    $tripId = (int)($_POST['trip_id'] ?? 0);
                    if (!$tripId) ApiResponse::error('trip_id required.');

                    $canManage = in_array($user->getRole(), ['admin', 'leader'])
                        || ($user instanceof Member && $user->isTripLeader($tripId));
                    if (!$canManage) ApiResponse::error('Only leaders or an admin can edit trips.', 403);

                    $existing = Trip::findById($tripId);
                    if (!$existing) ApiResponse::error('Trip not found.', 404);

                    $fields = [];
                    $params = [];

                    if (isset($_POST['title'])) {
                        $title = substr(trim(strip_tags($_POST['title'])), 0, 120);
                        if ($title === '') ApiResponse::error('Title cannot be empty.');
                        $fields[] = 'title = ?'; $params[] = $title;
                    }
                    if (isset($_POST['destination'])) {
                        $dest = substr(trim(strip_tags($_POST['destination'])), 0, 120);
                        if ($dest === '') ApiResponse::error('Destination cannot be empty.');
                        $fields[] = 'destination = ?'; $params[] = $dest;
                    }
                    if (!empty($_POST['start_date'])) { $fields[] = 'start_date = ?'; $params[] = $_POST['start_date']; }
                    if (!empty($_POST['end_date']))   { $fields[] = 'end_date = ?';   $params[] = $_POST['end_date']; }
                    if (isset($_POST['base_currency']) && $_POST['base_currency'] !== '') { $fields[] = 'base_currency = ?'; $params[] = $_POST['base_currency']; }
                    if (isset($_POST['budget_limit']))    { $fields[] = 'budget_limit = ?';    $params[] = $_POST['budget_limit'] === '' ? null : (float)$_POST['budget_limit']; }
                    if (isset($_POST['max_slots']))       { $fields[] = 'max_slots = ?';       $params[] = $_POST['max_slots'] === '' ? null : (int)$_POST['max_slots']; }
                    if (isset($_POST['departure_point'])) { $fields[] = 'departure_point = ?'; $params[] = $_POST['departure_point'] === '' ? null : substr(trim(strip_tags($_POST['departure_point'])), 0, 200); }
                    if (isset($_POST['departure_time']))  { $fields[] = 'departure_time = ?';  $params[] = $_POST['departure_time'] === '' ? null : trim($_POST['departure_time']); }

                    if (isset($_POST['required_docs'])) {
                        $reqDocs = array_values(array_filter(array_intersect(
                            is_array($_POST['required_docs']) ? $_POST['required_docs'] : [],
                            ['passport', 'national_id', 'license']
                        )));
                        $fields[] = 'required_docs = ?';
                        $params[] = empty($reqDocs) ? null : json_encode($reqDocs);
                    }

                    if (empty($fields)) ApiResponse::error('No fields to update.');

                    $params[] = $tripId;
                    Database::getInstance('trips')
                        ->prepare('UPDATE trips SET ' . implode(', ', $fields) . ' WHERE id = ?')
                        ->execute($params);

                    ApiResponse::success(null, 'Trip updated.');

                case 'set_budget':
                    $tripId = (int)($_POST['trip_id'] ?? 0);
                    $limit  = (float)($_POST['budget_limit'] ?? 0);
                    if (!$tripId || $limit <= 0) ApiResponse::error('trip_id and budget_limit required.');
                    $canManage = in_array($user->getRole(), ['admin', 'leader'])
                        || ($user instanceof Member && $user->isTripLeader($tripId));
                    if (!$canManage) {
                        ApiResponse::error('Only leaders or an admin can set budget.', 403);
                    }
                    Database::getInstance('trips')
                        ->prepare('UPDATE trips SET budget_limit = ? WHERE id = ?')
                        ->execute([$limit, $tripId]);
                    ApiResponse::success(null, 'Budget set.');

                case 'close':
                    $tripId = (int)($_POST['trip_id'] ?? 0);
                    if (!$tripId) ApiResponse::error('trip_id required.');
                    $canManage = in_array($user->getRole(), ['admin', 'leader'])
                        || ($user instanceof Member && $user->isTripLeader($tripId));
                    if (!$canManage) {
                        ApiResponse::error('Only leaders or an admin can close a trip.', 403);
                    }
                    Database::getInstance('trips')
                        ->prepare("UPDATE trips SET status = 'settled' WHERE id = ?")
                        ->execute([$tripId]);
                    ApiResponse::success(null, 'Trip settled.');

                case 'update_status':
                    $tripId = (int)($_POST['trip_id'] ?? 0);
                    if (!$tripId) ApiResponse::error('trip_id required.');

                    $isAdmin      = $user->getRole() === 'admin';
                    $isLeader     = $user->getRole() === 'leader';
                    $isTripLeader = $user instanceof Member && $user->isTripLeader($tripId);
                    if (!$isAdmin && !$isLeader && !$isTripLeader) {
                        ApiResponse::error('Only the trip leader or an admin can update status.', 403);
                    }

                    $status        = $_POST['status'] ?? '';
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
                    $isLeader     = $user->getRole() === 'leader';
                    $isTripLeader = $user instanceof Member && $user->isTripLeader($tripId);
                    if (!$isAdmin && !$isLeader && !$isTripLeader) {
                        ApiResponse::error('Only leaders or an admin can set requirements.', 403);
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
    }
}
