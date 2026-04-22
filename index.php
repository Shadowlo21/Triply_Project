<?php

session_start();
require_once __DIR__ . '/config/bootstrap.php';

$page = $_GET['page'] ?? 'dashboard';
$allowed = ['dashboard', 'login', 'register', 'trips', 'itinerary', 'financial', 'documents', 'social', 'admin'];

if (!in_array($page, $allowed)) {
    http_response_code(404);
    exit('Page not found');
}

if ($page === 'login' || $page === 'register') {
    
    if (Auth::current()) {
        header('Location: /?page=dashboard');
        exit;
    }
    require __DIR__ . "/views/{$page}.php";
} else {
    
    if (!Auth::current()) {
        header('Location: /?page=login');
        exit;
    }
    require __DIR__ . "/views/{$page}.php";
}
