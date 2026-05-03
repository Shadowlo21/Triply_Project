<?php

/**
 * Emergency API
 * Actions: get_contact, update_contact, broadcast
 */

require_once __DIR__ . '/../config/bootstrap.php';

$user   = Auth::require();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        /**
         * Get current user's emergency contact info
         */
        case 'get_contact':
            $raw   = $user->getEmergencyContact();
            $parts = array_map('trim', explode('|', $raw . '||'));
            ApiResponse::success([
                'emergency_name'     => $parts[0] ?? '',
                'emergency_phone'    => $parts[1] ?? '',
                'emergency_relation' => $parts[2] ?? '',
            ]);

        /**
         * Update emergency contact information
         */
        case 'update_contact':
            $emergencyInfo = [];

            if (isset($_POST['emergency_name'])) {
                $emergencyInfo['name'] = trim($_POST['emergency_name']);
            }
            if (isset($_POST['emergency_phone'])) {
                $emergencyInfo['phone'] = trim($_POST['emergency_phone']);
            }
            if (isset($_POST['emergency_relation'])) {
                $emergencyInfo['relation'] = trim($_POST['emergency_relation']);
            }

            // Store combined emergency contact info
            $contactString = implode(' | ', array_filter([
                $emergencyInfo['name'] ?? '',
                $emergencyInfo['phone'] ?? '',
                $emergencyInfo['relation'] ?? ''
            ]));

            $user->updateProfile(['emergencyContact' => $contactString]);
            ApiResponse::success(null, 'Emergency contact updated successfully.');

        /**
         * Broadcast emergency alert to all trip members
         */
        case 'get_trip_contacts':
            $tripId = (int)($_GET['trip_id'] ?? 0);
            if (!$tripId) ApiResponse::error('trip_id required.');
            if (!$user->viewTrip($tripId)) ApiResponse::error('Access denied.', 403);

            $trip       = Trip::findById($tripId);
            $members    = $trip->getMembers();
            $accountsDb = Database::getInstance('accounts');
            $result     = [];

            foreach ($members as $m) {
                $blobStmt = $accountsDb->prepare('SELECT data FROM users WHERE id = ?');
                $blobStmt->execute([$m['id']]);
                $blob = $blobStmt->fetchColumn();

                $eName = $ePhone = $eRel = '';
                if ($blob) {
                    try {
                        $data  = Encryption::decryptJson($blob, $m['id']);
                        $raw   = $data['emergency_contact'] ?? '';
                        $parts = array_map('trim', explode('|', $raw . '||'));
                        $eName  = $parts[0];
                        $ePhone = $parts[1];
                        $eRel   = $parts[2];
                    } catch (\Throwable $ignored) {}
                }

                $result[] = [
                    'id'                 => $m['id'],
                    'email'              => $m['email'],
                    'trip_role'          => $m['trip_role'],
                    'emergency_name'     => $eName,
                    'emergency_phone'    => $ePhone,
                    'emergency_relation' => $eRel,
                ];
            }
            ApiResponse::success($result);

        case 'broadcast':
            $tripId  = (int)($_POST['trip_id'] ?? 0);
            $message = trim($_POST['message'] ?? '');

            if (!$tripId) {
                ApiResponse::error('trip_id is required.');
            }
            if (empty($message)) {
                ApiResponse::error('Message is required.');
            }

            // Verify user is a trip leader
            if (!($user instanceof TripLeader)) {
                ApiResponse::error('Only trip leaders can send emergency broadcasts.', 403);
            }

            // Verify user has access to this trip
            if (!$user->viewTrip($tripId)) {
                ApiResponse::error('Access denied to this trip.', 403);
            }

            // Get all trip members
            $trip = Trip::findById($tripId);
            $members = $trip->getMembers();

            // Send emergency notification to all members
            $notificationCount = 0;
            foreach ($members as $member) {
                $memberId = (int)$member['id'];
                if ($memberId !== $user->getId()) {
                    Notification::send(
                        $memberId,
                        'budget_alert',
                        '🚨 EMERGENCY ALERT from ' . $user->getName() . ' (' . $trip->getTitle() . '): ' . $message,
                        '🚨 Emergency Alert'
                    );
                    $notificationCount++;
                }
            }

            ApiResponse::success(['notified_count' => $notificationCount], 'Emergency alert sent to ' . $notificationCount . ' member(s).');

        default:
            ApiResponse::error('Unknown action.', 400);
    }
} catch (\Throwable $e) {
    ApiResponse::error($e->getMessage());
}
