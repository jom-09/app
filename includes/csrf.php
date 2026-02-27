<?php
// includes/csrf.php
if (session_status() === PHP_SESSION_NONE) session_start();

class Csrf {
    public static function token(): string {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verify(?string $token): void {
        if (!$token || empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $token)) {
            http_response_code(400);
            exit("Invalid CSRF token.");
        }
    }
}