<?php

/**
 * Profile API
 * Actions: get, update, change_password
 */

require_once __DIR__ . '/../config/bootstrap.php';

$user   = Auth::require();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        /**
         * Get current user's profile information
         */
        case 'get':
            ApiResponse::success([
                'id'                => $user->getId(),
                'email'             => $user->getEmail(),
                'name'              => $user->getName(),
                'phone'             => $user->getPhone(),
                'nationality'       => $user->getNationality(),
                'emergency_contact' => $user->getEmergencyContact(),
                'role'              => $user->getRole(),
                'points'            => $user->getPoints(),
            ]);

        /**
         * Update profile information
         */
        case 'update':
            $fields = [];
            if (isset($_POST['name'])) {
                $fields['name'] = trim($_POST['name']);
            }
            if (isset($_POST['phone'])) {
                $fields['phone'] = trim($_POST['phone']);
            }
            if (isset($_POST['nationality'])) {
                $fields['nationality'] = strtoupper(trim($_POST['nationality']));
            }
            if (isset($_POST['emergency_contact'])) {
                $fields['emergencyContact'] = trim($_POST['emergency_contact']);
            }

            if (empty($fields)) {
                ApiResponse::error('No fields to update.');
            }

            $user->updateProfile($fields);
            ApiResponse::success(null, 'Profile updated successfully.');

        /**
         * Change password
         */
        case 'change_password':
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword     = $_POST['new_password']     ?? '';

            if (empty($currentPassword) || empty($newPassword)) {
                ApiResponse::error('Current password and new password are required.');
            }

            if (strlen($newPassword) < 8) {
                ApiResponse::error('New password must be at least 8 characters.');
            }

            // Verify current password
            $row = User::findByEmail($user->getEmail());
            if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
                ApiResponse::error('Current password is incorrect.');
            }

            // Update password
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            Database::getInstance('accounts')
                ->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([$newHash, $user->getId()]);

            // Revoke all sessions except current
            Auth::revokeAll($user->getId());

            ApiResponse::success(null, 'Password changed successfully. Please log in again.');

        default:
            ApiResponse::error('Unknown action.', 400);
    }
} catch (\Throwable $e) {
    ApiResponse::error($e->getMessage());
}
