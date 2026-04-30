<?php
/**
 * Logout Page
 * Calls Auth::logout() and redirects to login
 */

require_once __DIR__ . '/../config/bootstrap.php';

// Perform logout
Auth::logout();

// Redirect to login page
header('Location: /?page=login');
exit;
