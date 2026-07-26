<?php
/**
 * FinancePro - Logout Script
 * Location: /FinancePro/logout.php
 * Fully destroys the session (not just unsets user_id) for a clean, secure logout.
 */
require_once 'config.php';

// Clear all session variables
$_SESSION = [];

// Destroy the session cookie itself
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

// Destroy the session on the server
session_destroy();

// Start a fresh session only to carry the goodbye flash message
session_start();
$_SESSION['flash'] = ['type' => 'success', 'message' => 'You have been logged out successfully.'];

header('Location: ' . BASE_URL . 'login.php');
exit;
