<?php

session_start();
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/classes/boundary/Auth.php';

$isAuthed = (bool)Auth::current();
$page = $_GET['page'] ?? ($isAuthed ? 'dashboard' : 'landing');
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
    require __DIR__ . "/views/{$page}.php";
}
