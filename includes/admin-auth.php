<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function get_admin_user()
{
    return $_SESSION['admin_user'] ?? null;
}

function is_admin_logged_in()
{
    $user = get_admin_user();
    return is_array($user) && !empty($user['id']);
}

function require_admin($redirect = '/login.php')
{
    if (!is_admin_logged_in()) {
        header("Location: {$redirect}");
        exit;
    }
}

function require_admin_json()
{
    if (!is_admin_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required.']);
        exit;
    }
}

function admin_logout()
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}
