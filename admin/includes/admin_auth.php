<?php
// admin/includes/admin_auth.php
if (session_status() === PHP_SESSION_NONE) session_start();

function admin_session_harden(): void {
    // Call once after login
    session_regenerate_id(true);
}

function require_admin(): void {
    if (empty($_SESSION['admin_id'])) {
        header("Location: login.php");
        exit();
    }
}

function admin_logout(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $p["path"], $p["domain"], (bool)$p["secure"], (bool)$p["httponly"]);
    }
    session_destroy();
}