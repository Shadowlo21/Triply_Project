<?php

require_once __DIR__ . '/../config/bootstrap.php';

$user   = Auth::require();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {



        case 'list':
            $db   = Database::getInstance('trips');
            $stmt = $db->prepare(
                'SELECT t.*, tm.role AS my_role, tm.can_edit
                 FROM trips t
                 JOIN trip_members tm ON tm.trip_id = t.id
                 WHERE tm.user_id = ?
                 ORDER BY t.start_date DESC'
            );
            $stmt->execute([$user->getId()]);
            ApiResponse::success($stmt->fetchAll());


        case 'get':
            $tripId = (int)($_GET['trip_id'] ?? 0);
            if (!$tripId) ApiResponse::error('trip_id required.');

            $trip = $user->viewTrip($tripId);
            if (!$trip) ApiResponse::error('Trip not found or access denied.', 404);

            ApiResponse::success($trip);


        case 'create':
            if (!($user instanceof TripLeader)) {

                $leader = new TripLeader($user->getId(), $user->getEmail(), 'leader');
                $leader->decryptData(
                    Database::getInstance('accounts')
                        ->query("SELECT data FROM users WHERE id = {$user->getId()}")
                        ->fetchColumn()
                );
                $user = $leader;


                Database::getInstance('accounts')
                    ->prepare('UPDATE users SET role = "leader" WHERE id = ?')
                    ->execute([$user->getId()]);
            }

            $required = ['title', 'destination', 'start_date', 'end_date'];
            foreach ($required as $f) {
                if (empty($_POST[$f])) ApiResponse::error("Missing field: {$f}");
            }

            $tripId = $user->createTrip([
                'title'         => trim($_POST['title']),
                'destination'   => trim($_POST['destination']),
                'start_date'    => $_POST['start_date'],
                'end_date'      => $_POST['end_date'],
                'base_currency' => $_POST['base_currency'] ?? 'EGP',
                'budget_limit'  => !empty($_POST['budget_limit']) ? (float)$_POST['budget_limit'] : null,
            ]);

            ApiResponse::success(['trip_id' => $tripId], 'Trip created.');


        case 'members':
            $tripId = (int)($_GET['trip_id'] ?? 0);
            if (!$tripId) ApiResponse::error('trip_id required.');
            if (!$user->viewTrip($tripId)) ApiResponse::error('Access denied.', 403);

            $trip = Trip::findById($tripId);
            ApiResponse::success($trip->getMembers());


        case 'invite':
            $tripId = (int)($_POST['trip_id'] ?? 0);
            $email  = trim($_POST['email'] ?? '');
            if (!$tripId || !$email) ApiResponse::error('trip_id and email required.');
            if (!$user->viewTrip($tripId)) ApiResponse::error('Access denied.', 403);

            if (!($user instanceof Member)) ApiResponse::error('Not allowed.');
            // $ok = $user->inviteUser($email, $tripId);
            $ok = true;

            if ($ok) {
                $trip    = Trip::findById($tripId);
                $invitee = Database::getInstance('accounts')
                    ->prepare('SELECT id FROM users WHERE email = ?');
                $invitee->execute([$email]);
                $inviteeId = (int)$invitee->fetchColumn();
                if ($inviteeId) {
                    Notification::tripInvite($inviteeId, $trip->getTitle(), $user->getName());
                }
            }

            ApiResponse::success(null, $ok ? 'Invited.' : 'User not found or already a member.');


        case 'set_permission':
            if (!($user instanceof TripLeader)) ApiResponse::error('Only trip leaders.', 403);

            $tripId  = (int)($_POST['trip_id'] ?? 0);
            $userId  = (int)($_POST['user_id'] ?? 0);
            $canEdit = (int)($_POST['can_edit'] ?? 0);
            if (!$tripId || !$userId) ApiResponse::error('trip_id and user_id required.');

            $ok = $user->editPermission($tripId, $userId, (bool)$canEdit);
            ApiResponse::success(null, $ok ? 'Permission updated.' : 'Failed.');


        case 'set_budget':
            if (!($user instanceof TripLeader)) ApiResponse::error('Only trip leaders.', 403);

            $tripId = (int)($_POST['trip_id'] ?? 0);
            $limit  = (float)($_POST['budget_limit'] ?? 0);
            if (!$tripId || $limit <= 0) ApiResponse::error('trip_id and budget_limit required.');

            $ok = $user->setBudgetLimit($tripId, $limit);
            ApiResponse::success(null, $ok ? 'Budget set.' : 'Failed.');


        case 'close':
            if (!($user instanceof TripLeader)) ApiResponse::error('Only trip leaders.', 403);

            $tripId = (int)($_POST['trip_id'] ?? 0);
            if (!$tripId) ApiResponse::error('trip_id required.');

            $ok = $user->closeTrip($tripId);
            ApiResponse::success(null, $ok ? 'Trip settled.' : 'Failed.');

        case 'delete':
            if (!($user instanceof TripLeader)) ApiResponse::error('Only trip leaders can delete trips.', 403);

            $tripId = (int)($_POST['trip_id'] ?? 0);
            if (!$tripId) ApiResponse::error('trip_id required.');
            if (!$user->viewTrip($tripId)) ApiResponse::error('Access denied.', 403);

            $trip = Trip::findById($tripId);
            if ($trip->getCreatedBy() !== $user->getId() && $user->getRole() !== 'admin') {
                ApiResponse::error('Only the trip creator or admin can delete.', 403);
            }

            Database::getInstance('trips')
                ->prepare('DELETE FROM trips WHERE id = ?')
                ->execute([$tripId]);

            ApiResponse::success(null, 'Trip deleted.');

        case 'update_status':
            if (!($user instanceof TripLeader)) ApiResponse::error('Only trip leaders.', 403);

            $tripId = (int)($_POST['trip_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $validStatuses = ['planning', 'active', 'completed', 'settled'];

            if (!$tripId) ApiResponse::error('trip_id required.');
            if (!in_array($status, $validStatuses)) ApiResponse::error('Invalid status.');
            if (!$user->viewTrip($tripId)) ApiResponse::error('Access denied.', 403);

            Database::getInstance('trips')
                ->prepare('UPDATE trips SET status = ? WHERE id = ?')
                ->execute([$status, $tripId]);

            ApiResponse::success(null, 'Status updated to ' . $status . '.');

        default:
            ApiResponse::error('Unknown action.', 400);
    }
} catch (\Throwable $e) {
    ApiResponse::error($e->getMessage());
}
