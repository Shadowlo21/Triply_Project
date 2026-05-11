<?php

session_start();
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/classes/services/Auth.php';

$isAuthed = (bool)Auth::current();
$defaultPage = $isAuthed
    ? (Auth::current()->getRole() === 'admin' ? 'admin' : 'dashboard')
    : 'landing';
$page = $_GET['page'] ?? $defaultPage;
$allowed = ['landing', 'dashboard', 'login', 'register', 'trips', 'itinerary', 'financial', 'documents', 'social', 'admin', 'profile', 'notifications', 'emergency', 'logout'];

if (!in_array($page, $allowed)) {
    http_response_code(404);
    exit('Page not found');
}

if ($page === 'landing') {
    if ($isAuthed) {
        header('Location: /?page=dashboard');
        exit;
    }
    require __DIR__ . "/views/landing.php";
} elseif ($page === 'login' || $page === 'register') {

    if ($isAuthed) {
        header('Location: /?page=dashboard');
        exit;
    }
    require __DIR__ . "/views/{$page}.php";
} else {

    if (!$isAuthed) {
        header('Location: /?page=login');
        exit;
    }

    $user = Auth::current();
    $adminBlocked = ['dashboard', 'social', 'financial', 'documents', 'itinerary'];
    if ($user->getRole() === 'admin' && in_array($page, $adminBlocked, true)) {
        header('Location: /?page=admin');
        exit;
    }

    require __DIR__ . "/views/{$page}.php";
}
