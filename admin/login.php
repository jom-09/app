<?php
session_start();

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/csrf.php";
require_once __DIR__ . "/../includes/helpers.php";
require_once __DIR__ . "/includes/admin_auth.php";

if (!empty($_SESSION['admin_id'])) {
  header("Location: index.php");
  exit();
}

/* ===============================
   Security helpers (no inline JS)
================================= */

function client_ip(): string {
  // If behind reverse proxy, you should trust headers only if you control the proxy.
  // Sa XAMPP/local, REMOTE_ADDR is enough.
  return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function normalize_username(string $u): string {
  $u = trim($u);
  // keep it predictable: avoid weird unicode or spaces
  $u = preg_replace('/\s+/', '', $u);
  return mb_substr($u, 0, 190);
}

/**
 * Get attempt row (create if none).
 */
function get_attempt_row(mysqli $conn, string $username, string $ip): array {
  $stmt = $conn->prepare("SELECT id, attempts, last_attempt_at, locked_until FROM login_attempts WHERE username=? AND ip=? LIMIT 1");
  $stmt->bind_param("ss", $username, $ip);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();

  if ($row) return $row;

  $now = (new DateTime('now'))->format('Y-m-d H:i:s');
  $stmt = $conn->prepare("INSERT INTO login_attempts (username, ip, attempts, last_attempt_at, locked_until) VALUES (?, ?, 0, ?, NULL)");
  $stmt->bind_param("sss", $username, $ip, $now);
  $stmt->execute();
  $stmt->close();

  return [
    'id' => $conn->insert_id,
    'attempts' => 0,
    'last_attempt_at' => $now,
    'locked_until' => null
  ];
}

/**
 * Update attempts on failure, with progressive lockout.
 * - After 5 fails: short lock
 * - After 8 fails: longer lock
 * - After 12 fails: even longer lock
 */
function register_failed_attempt(mysqli $conn, int $rowId, int $attemptsNow): void {
  $attemptsNow++;

  $now = new DateTime('now');
  $lockedUntil = null;

  if ($attemptsNow >= 12) {
    $lockedUntil = (clone $now)->modify('+30 minutes');
  } elseif ($attemptsNow >= 8) {
    $lockedUntil = (clone $now)->modify('+10 minutes');
  } elseif ($attemptsNow >= 5) {
    $lockedUntil = (clone $now)->modify('+2 minutes');
  }

  $nowStr = $now->format('Y-m-d H:i:s');
  $lockStr = $lockedUntil ? $lockedUntil->format('Y-m-d H:i:s') : null;

  $stmt = $conn->prepare("UPDATE login_attempts SET attempts=?, last_attempt_at=?, locked_until=? WHERE id=?");
  $stmt->bind_param("issi", $attemptsNow, $nowStr, $lockStr, $rowId);
  $stmt->execute();
  $stmt->close();

  // Small server-side delay to slow bruteforce (caps at ~2s)
  $delayMs = min(2000, 200 * $attemptsNow);
  usleep($delayMs * 1000);
}

/**
 * Clear attempts on success.
 */
function clear_attempts(mysqli $conn, int $rowId): void {
  $now = (new DateTime('now'))->format('Y-m-d H:i:s');
  $stmt = $conn->prepare("UPDATE login_attempts SET attempts=0, last_attempt_at=?, locked_until=NULL WHERE id=?");
  $stmt->bind_param("si", $now, $rowId);
  $stmt->execute();
  $stmt->close();
}

/**
 * Make session cookies stricter + rotate session id on login.
 * (Call AFTER successful login)
 */
function secure_session_on_login(): void {
  // You can also set these in php.ini, but doing here is fine.
  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

  // Ensure cookie params are strict
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,     // true only if HTTPS
    'httponly' => true,
    'samesite' => 'Strict',
  ]);

  // Rotate session ID
  if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
  }
}

$error = '';
$ip = client_ip();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  Csrf::verify($_POST['_csrf'] ?? null);

  $username = normalize_username((string)($_POST['username'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  // Always use same generic error (avoid enumeration)
  $genericError = "Invalid credentials.";

  // Basic validation
  if ($username === '' || $password === '') {
    $error = $genericError;
  } else {
    // Rate limit by username+ip
    $attemptRow = get_attempt_row($conn, $username, $ip);

    // If locked, block immediately
    if (!empty($attemptRow['locked_until'])) {
      $lockedUntil = new DateTime($attemptRow['locked_until']);
      $now = new DateTime('now');
      if ($lockedUntil > $now) {
        $error = "Too many attempts. Try again later.";
      }
    }

    if ($error === '') {
      // Lookup admin
      $stmt = $conn->prepare("SELECT id, username, password_hash, is_active FROM admin_users WHERE username=? LIMIT 1");
      $stmt->bind_param("s", $username);
      $stmt->execute();
      $res = $stmt->get_result();
      $admin = $res->fetch_assoc();
      $stmt->close();

      $ok = false;
      if ($admin && (int)$admin['is_active'] === 1) {
        // Verify password
        if (!empty($admin['password_hash']) && password_verify($password, (string)$admin['password_hash'])) {
          $ok = true;

          // If hash algo updated, rehash transparently
          if (password_needs_rehash((string)$admin['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $up = $conn->prepare("UPDATE admin_users SET password_hash=? WHERE id=? LIMIT 1");
            $aid = (int)$admin['id'];
            $up->bind_param("si", $newHash, $aid);
            $up->execute();
            $up->close();
          }
        }
      }

      if (!$ok) {
        register_failed_attempt($conn, (int)$attemptRow['id'], (int)$attemptRow['attempts']);
        $error = $genericError;
      } else {
        // Success: clear attempts
        clear_attempts($conn, (int)$attemptRow['id']);

        // Session harden + your existing hardening
        secure_session_on_login();

        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['admin_user'] = (string)$admin['username'];

        // keep your function
        admin_session_harden();

        header("Location: index.php");
        exit();
      }
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-5">
      <div class="card shadow-sm">
        <div class="card-header bg-white"><strong>Admin Login</strong></div>
        <div class="card-body">
          <?php if ($error): ?>
            <div class="alert alert-danger mb-3"><?= h($error) ?></div>
          <?php endif; ?>

          <form method="post" novalidate>
            <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">

            <div class="mb-3">
              <label class="form-label">Username</label>
              <input
                class="form-control"
                name="username"
                autocomplete="username"
                required
                value="<?= h($_POST['username'] ?? '') ?>"
              >
            </div>

            <div class="mb-3">
              <label class="form-label">Password</label>
              <input class="form-control" type="password" name="password" autocomplete="current-password" required>
            </div>

            <button class="btn btn-primary w-100" type="submit">Login</button>
          </form>

        </div>
      </div>
    </div>
  </div>
</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>