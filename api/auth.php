<?php

require_once __DIR__ . '/../config/bootstrap.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        
        case 'register':
            $required = ['email','password','name','phone','nationality'];
            foreach ($required as $f) {
                if (empty($_POST[$f])) ApiResponse::error("Missing field: {$f}");
            }

            $pwd = $_POST['password'];
            if (strlen($pwd) < 8) ApiResponse::error('Password must be at least 8 characters.');
            if (!preg_match('/[A-Z]/', $pwd)) ApiResponse::error('Password must contain at least 1 uppercase letter.');
            if (!preg_match('/[0-9]/', $pwd)) ApiResponse::error('Password must contain at least 1 number.');
            if (!preg_match('/[^A-Za-z0-9]/', $pwd)) ApiResponse::error('Password must contain at least 1 special character.');

            $userId = Auth::register(
                trim($_POST['email']),
                $_POST['password'],
                trim($_POST['name']),
                trim($_POST['phone']),
                trim($_POST['nationality']),
                'member'
            );

            ApiResponse::success(['user_id' => $userId], 'Registered successfully.');

        
        case 'login':
            if (empty($_POST['email']) || empty($_POST['password'])) {
                ApiResponse::error('Email and password required.');
            }

            $user = Auth::login(trim($_POST['email']), $_POST['password']);

            ApiResponse::success([
                'id'    => $user->getId(),
                'email' => $user->getEmail(),
                'name'  => $user->getName(),
                'role'  => $user->getRole(),
            ], 'Logged in.');

        
        case 'logout':
            Auth::logout();
            ApiResponse::success(null, 'Logged out.');

        
        case 'me':
            $user = Auth::require();
            ApiResponse::success([
                'id'          => $user->getId(),
                'email'       => $user->getEmail(),
                'name'        => $user->getName(),
                'role'        => $user->getRole(),
                'nationality' => $user->getNationality(),
                'points'      => $user->getPoints(),
            ]);

        default:
            ApiResponse::error('Unknown action.', 400);
    }
} catch (\Throwable $e) {
    ApiResponse::error($e->getMessage());
}

