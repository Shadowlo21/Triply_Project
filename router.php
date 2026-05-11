<?php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

if (preg_match('#^/(public/|favicon\.ico|storage/)#', $uri)) {
    return false;
}

if (preg_match('#^/api/([a-z]+)(?:\.php)?/?$#', $uri, $m)) {
    require_once __DIR__ . '/config/bootstrap.php';

    $endpoint = $m[1];
    $class    = ucfirst($endpoint) . 'Controller';

    if (!class_exists($class)) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unknown endpoint.']);
        exit;
    }

    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($endpoint === 'auth') {
        (new $class())->handle($action);
    } else {
        $user = Auth::require();
        (new $class())->handle($user, $action);
    }
    exit;
}

require __DIR__ . '/index.php';
