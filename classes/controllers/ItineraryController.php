<?php

class ItineraryController
{
    public function handle(User $user, string $action): void
    {
        try {
            switch ($action) {

                case 'list':
                    $tripId = (int)($_GET['trip_id'] ?? 0);
                    if (!$tripId) ApiResponse::error('trip_id required.');
                    if (!$user->viewTrip($tripId)) ApiResponse::error('Access denied.', 403);

                    $itinerary = new Itinerary($tripId);
                    ApiResponse::success($itinerary->getCurrentItinerary());

                case 'add':
                    $tripId = (int)($_POST['trip_id'] ?? 0);
                    if (!$tripId) ApiResponse::error('trip_id required.');
                    if (!$user->viewTrip($tripId)) ApiResponse::error('Access denied.', 403);
                    if (empty($_POST['title']) || empty($_POST['datetime'])) {
                        ApiResponse::error('title and datetime required.');
                    }

                    $itinerary  = new Itinerary($tripId);
                    $itinerary->saveVersion($user->getId(), 'before add: ' . $_POST['title']);

                    $activityId = $user->proposeActivity($tripId, [
                        'title'          => trim($_POST['title']),
                        'location'       => trim($_POST['location']      ?? ''),
                        'lat'            => !empty($_POST['lat'])  ? (float)$_POST['lat']  : null,
                        'lng'            => !empty($_POST['lng'])  ? (float)$_POST['lng']  : null,
                        'datetime'       => $_POST['datetime'],
                        'duration_min'   => (int)($_POST['duration_min']   ?? 60),
                        'transport_mode' => $_POST['transport_mode'] ?? 'car',
                    ]);

                    $conflicts = $itinerary->detectConflicts();

                    ApiResponse::success([
                        'activity_id' => $activityId,
                        'conflicts'   => count($conflicts),
                    ], count($conflicts) > 0 ? 'Activity added with scheduling conflicts.' : 'Activity proposed.');

                case 'update':
                    $activityId = (int)($_POST['activity_id'] ?? 0);
                    if (!$activityId) ApiResponse::error('activity_id required.');

                    $activity = Activity::findById($activityId);
                    if (!$activity) ApiResponse::error('Activity not found.', 404);
                    if (!$user->viewTrip($activity->getTripId())) ApiResponse::error('Access denied.', 403);

                    $itinerary = new Itinerary($activity->getTripId());
                    $itinerary->saveVersion($user->getId(), 'before update activity #' . $activityId);

                    if (!empty($_POST['status'])) {
                        if (!($user instanceof TripLeader)) ApiResponse::error('Only leaders can change status.', 403);
                        $activity->setStatus($_POST['status']);
                    }

                    if (!empty($_POST['title']) || !empty($_POST['datetime'])) {
                        $db      = Database::getInstance('trips');
                        $canEdit = $db->prepare(
                            'SELECT can_edit FROM trip_members WHERE trip_id = ? AND user_id = ?'
                        );
                        $canEdit->execute([$activity->getTripId(), $user->getId()]);
                        $perm = $canEdit->fetchColumn();

                        if (!($user instanceof TripLeader) && !$perm) {
                            ApiResponse::error('No edit permission.', 403);
                        }

                        $fields = [];
                        $params = [];
                        foreach (['title','location','datetime','duration_min','transport_mode'] as $f) {
                            if (isset($_POST[$f])) {
                                $fields[] = "{$f} = ?";
                                $params[] = $_POST[$f];
                            }
                        }
                        if ($fields) {
                            $params[] = $activityId;
                            $db->prepare('UPDATE activities SET ' . implode(', ', $fields) . ' WHERE id = ?')
                               ->execute($params);
                        }
                    }

                    ApiResponse::success(null, 'Activity updated.');

                case 'delete':
                    $activityId = (int)($_POST['activity_id'] ?? 0);
                    if (!$activityId) ApiResponse::error('activity_id required.');

                    $activity = Activity::findById($activityId);
                    if (!$activity) ApiResponse::error('Activity not found.', 404);
                    if (!($user instanceof TripLeader)) ApiResponse::error('Only leaders can delete.', 403);
                    if (!$user->viewTrip($activity->getTripId())) ApiResponse::error('Access denied.', 403);

                    $itinerary = new Itinerary($activity->getTripId());
                    $itinerary->saveVersion($user->getId(), 'before delete activity #' . $activityId);

                    Database::getInstance('trips')
                        ->prepare('DELETE FROM activities WHERE id = ?')
                        ->execute([$activityId]);

                    ApiResponse::success(null, 'Activity deleted.');

                case 'conflicts':
                    $tripId = (int)($_GET['trip_id'] ?? 0);
                    if (!$tripId) ApiResponse::error('trip_id required.');
                    if (!$user->viewTrip($tripId)) ApiResponse::error('Access denied.', 403);

                    $itinerary = new Itinerary($tripId);
                    $conflicts = $itinerary->detectConflicts();

                    $result = array_map(fn($c) => [
                        'a' => ['id' => $c['a']->getId(), 'title' => $c['a']->getTitle(), 'datetime' => $c['a']->getDatetime()],
                        'b' => ['id' => $c['b']->getId(), 'title' => $c['b']->getTitle(), 'datetime' => $c['b']->getDatetime()],
                    ], $conflicts);

                    ApiResponse::success($result);

                case 'rsvp':
                    $activityId = (int)($_POST['activity_id'] ?? 0);
                    $status     = $_POST['status'] ?? '';
                    if (!$activityId || !in_array($status, ['in','out'])) {
                        ApiResponse::error('activity_id and status (in|out) required.');
                    }

                    $activity = Activity::findById($activityId);
                    if (!$activity || !$user->viewTrip($activity->getTripId())) {
                        ApiResponse::error('Access denied.', 403);
                    }

                    $user->setAttendance($activityId, $status);
                    ApiResponse::success(null, 'RSVP saved.');

                case 'comment':
                    $activityId = (int)($_POST['activity_id'] ?? 0);
                    $content    = trim($_POST['content'] ?? '');
                    if (!$activityId || !$content) ApiResponse::error('activity_id and content required.');

                    $activity = Activity::findById($activityId);
                    if (!$activity || !$user->viewTrip($activity->getTripId())) {
                        ApiResponse::error('Access denied.', 403);
                    }

                    $user->addComment($activityId, $content);
                    ApiResponse::success(null, 'Comment added.');

                case 'comments':
                    $activityId = (int)($_GET['activity_id'] ?? 0);
                    if (!$activityId) ApiResponse::error('activity_id required.');

                    $activity = Activity::findById($activityId);
                    if (!$activity || !$user->viewTrip($activity->getTripId())) {
                        ApiResponse::error('Access denied.', 403);
                    }

                    $tripsDb  = Database::getInstance('trips');
                    $stmt     = $tripsDb->prepare(
                        'SELECT id, user_id, content, created_at FROM comments WHERE activity_id = ? ORDER BY created_at ASC'
                    );
                    $stmt->execute([$activityId]);
                    $comments = $stmt->fetchAll();

                    if (!empty($comments)) {
                        $userIds      = array_values(array_unique(array_column($comments, 'user_id')));
                        $accountsDb   = Database::getInstance('accounts');
                        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                        $userStmt     = $accountsDb->prepare(
                            "SELECT id, email FROM users WHERE id IN ({$placeholders})"
                        );
                        $userStmt->execute($userIds);
                        $emails = [];
                        foreach ($userStmt->fetchAll() as $u) {
                            $emails[$u['id']] = $u['email'];
                        }
                        foreach ($comments as &$c) {
                            $c['email'] = $emails[$c['user_id']] ?? '';
                        }
                    }

                    ApiResponse::success($comments);

                case 'versions':
                    $tripId = (int)($_GET['trip_id'] ?? 0);
                    if (!$tripId) ApiResponse::error('trip_id required.');
                    if (!$user->viewTrip($tripId)) ApiResponse::error('Access denied.', 403);

                    ApiResponse::success(ItineraryVersion::listByTrip($tripId));

                case 'rollback':
                    if (!($user instanceof TripLeader)) ApiResponse::error('Only leaders can rollback.', 403);

                    $versionId = (int)($_POST['version_id'] ?? 0);
                    if (!$versionId) ApiResponse::error('version_id required.');

                    $version = ItineraryVersion::findById($versionId);
                    if (!$version) ApiResponse::error('Version not found.', 404);
                    if (!$user->viewTrip($version->getTripId())) ApiResponse::error('Access denied.', 403);

                    $itinerary = new Itinerary($version->getTripId());
                    $itinerary->saveVersion($user->getId(), 'before rollback to version #' . $versionId);

                    $ok = $version->rollback();
                    ApiResponse::success(null, $ok ? 'Rolled back.' : 'Rollback failed.');

                default:
                    ApiResponse::error('Unknown action.', 400);
            }
        } catch (\Throwable $e) {
            ApiResponse::error($e->getMessage());
        }
    }
}
