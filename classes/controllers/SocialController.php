<?php

class SocialController
{
    public function handle(User $user, string $action): void
    {
        try {
            switch ($action) {

                case 'polls':
                    $tripId = (int)($_GET['trip_id'] ?? 0);
                    if (!$tripId) ApiResponse::error('trip_id required.');
                    if (!$user->viewTrip($tripId)) ApiResponse::error('Access denied.', 403);

                    $db   = Database::getInstance('social');
                    $stmt = $db->prepare(
                        'SELECT p.*, COUNT(po.id) AS option_count
                         FROM polls p
                         LEFT JOIN poll_options po ON po.poll_id = p.id
                         WHERE p.trip_id = ?
                         GROUP BY p.id
                         ORDER BY p.created_at DESC'
                    );
                    $stmt->execute([$tripId]);
                    $polls = $stmt->fetchAll();

                    foreach ($polls as $row) {
                        $poll = Poll::findById($row['id']);
                        $poll->checkDeadline();
                    }

                    ApiResponse::success($polls);

                case 'create_poll':
                    $tripId = (int)($_POST['trip_id'] ?? 0);
                    if (!$tripId) ApiResponse::error('trip_id required.');
                    if (!$user->viewTrip($tripId)) ApiResponse::error('Access denied.', 403);
                    if (empty($_POST['question'])) ApiResponse::error('question required.');

                    $options = array_filter(array_map('trim', $_POST['options'] ?? []));
                    if (count($options) < 2) ApiResponse::error('At least 2 options required.');

                    $db   = Database::getInstance('social');
                    $stmt = $db->prepare(
                        'INSERT INTO polls (trip_id, question, type, is_anonymous, deadline, created_by)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $tripId,
                        trim($_POST['question']),
                        $_POST['type']        ?? 'general',
                        !empty($_POST['is_anonymous']) ? 1 : 0,
                        !empty($_POST['deadline']) ? $_POST['deadline'] : null,
                        $user->getId(),
                    ]);
                    $pollId = (int)$db->lastInsertId();

                    $poll = Poll::findById($pollId);
                    foreach ($options as $opt) {
                        $poll->addOption($opt);
                    }

                    ApiResponse::success(['poll_id' => $pollId], 'Poll created.');

                case 'vote':
                    $pollId   = (int)($_POST['poll_id']   ?? 0);
                    $optionId = (int)($_POST['option_id'] ?? 0);
                    if (!$pollId || !$optionId) ApiResponse::error('poll_id and option_id required.');

                    $socialDb = Database::getInstance('social');
                    $stmt     = $socialDb->prepare('SELECT trip_id FROM polls WHERE id = ?');
                    $stmt->execute([$pollId]);
                    $tripId = (int)$stmt->fetchColumn();
                    if (!$user->viewTrip($tripId)) ApiResponse::error('Access denied.', 403);

                    $poll = Poll::findById($pollId);
                    $poll->checkDeadline();
                    if ($poll->getStatus() !== 'open') ApiResponse::error('Poll is closed.');

                    $vote = Vote::cast($pollId, $optionId, $user->getId());
                    if (!$vote) ApiResponse::error('Already voted or poll is closed.');

                    ApiResponse::success(null, 'Vote cast.');

                case 'results':
                    $pollId = (int)($_GET['poll_id'] ?? 0);
                    if (!$pollId) ApiResponse::error('poll_id required.');

                    $db   = Database::getInstance('social');
                    $stmt = $db->prepare('SELECT trip_id, created_by FROM polls WHERE id = ?');
                    $stmt->execute([$pollId]);
                    $row  = $stmt->fetch();
                    if (!$row) ApiResponse::error('Poll not found.', 404);
                    if (!$user->viewTrip((int)$row['trip_id'])) ApiResponse::error('Access denied.', 403);

                    $poll      = Poll::findById($pollId);
                    $isLeader  = ($user instanceof TripLeader) || ($user->getId() === (int)$row['created_by']);
                    $results   = $poll->getResults($isLeader);
                    $winnerId  = $poll->getWinner();

                    ApiResponse::success([
                        'poll_id'          => $pollId,
                        'status'           => $poll->getStatus(),
                        'results'          => $results,
                        'winner_option_id' => $winnerId,
                    ]);

                case 'close_poll':
                    $pollId = (int)($_POST['poll_id'] ?? 0);
                    if (!$pollId) ApiResponse::error('poll_id required.');

                    $db   = Database::getInstance('social');
                    $stmt = $db->prepare('SELECT trip_id, created_by FROM polls WHERE id = ?');
                    $stmt->execute([$pollId]);
                    $row  = $stmt->fetch();

                    if (!$row) ApiResponse::error('Poll not found.', 404);
                    if (!($user instanceof TripLeader) && $user->getId() !== (int)$row['created_by']) {
                        ApiResponse::error('Only the poll creator or a trip leader can close.', 403);
                    }

                    $poll = Poll::findById($pollId);
                    $winnerId = $poll->closeVoting();

                    Notification::pollClosed($pollId, (int)$row['trip_id'], $winnerId);

                    ApiResponse::success(['winner_option_id' => $winnerId], 'Poll closed.');

                default:
                    ApiResponse::error('Unknown action.', 400);
            }
        } catch (\Throwable $e) {
            ApiResponse::error($e->getMessage());
        }
    }
}
