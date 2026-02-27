<?php
session_start();
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/includes/admin_auth.php";
require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit("Missing id."); }

$stmt = $conn->prepare("SELECT file_path, original_name, mime_type FROM request_attachments WHERE id=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$f = $res->fetch_assoc();
$stmt->close();

if (!$f) { http_response_code(404); exit("File not found."); }

$full = realpath(__DIR__ . "/../" . $f['file_path']);
$uploadsRoot = realpath(__DIR__ . "/../uploads");

if (!$full || !$uploadsRoot || strpos($full, $uploadsRoot) !== 0 || !is_file($full)) {
  http_response_code(403);
  exit("Access denied.");
}

header("Content-Type: ".$f['mime_type']);
header('Content-Disposition: attachment; filename="'.basename($f['original_name']).'"');
header("Content-Length: ".filesize($full));
readfile($full);
exit();