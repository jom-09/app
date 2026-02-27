<?php
// api/psgc.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$level = $_GET['level'] ?? '';
$level = is_string($level) ? $level : '';

$code = $_GET['code'] ?? '';
$code = is_string($code) ? trim($code) : '';

// Use PSGC Cloud v2 endpoints (direct + nested)
// Docs: https://psgc.cloud/api-docs/v2  :contentReference[oaicite:1]{index=1}
$base = 'https://psgc.cloud/api/v2';

$map = [
  'regions' => $base . '/regions',
  'provinces_by_region' => $base . '/regions/%s/provinces',
  'citiesmun_by_province' => $base . '/provinces/%s/cities-municipalities',
  'barangays_by_citymun' => $base . '/cities-municipalities/%s/barangays',
];

function bad(string $msg, int $code = 400): void {
  http_response_code($code);
  echo json_encode(['ok'=>false,'message'=>$msg], JSON_UNESCAPED_UNICODE);
  exit();
}

function fetch_json(string $url): array {
  $ctx = stream_context_create([
    'http' => [
      'method' => 'GET',
      'timeout' => 8,
      'header' => "Accept: application/json\r\nUser-Agent: AppointmentSystem/1.0\r\n"
    ]
  ]);
  $raw = @file_get_contents($url, false, $ctx);
  if ($raw === false) return ['ok'=>false,'data'=>[], 'error'=>'Fetch failed'];

  $data = json_decode($raw, true);
  if (!is_array($data)) return ['ok'=>false,'data'=>[], 'error'=>'Invalid JSON'];

  return ['ok'=>true,'data'=>$data];
}

switch ($level) {
  case 'regions':
    $r = fetch_json($map['regions']);
    if (!$r['ok']) bad('Failed to load regions. Try again.', 502);
    echo json_encode(['ok'=>true,'items'=>$r['data']], JSON_UNESCAPED_UNICODE);
    exit();

  case 'provinces':
    if ($code === '') bad('Missing region code.');
    $url = sprintf($map['provinces_by_region'], rawurlencode($code));
    $r = fetch_json($url);
    if (!$r['ok']) bad('Failed to load provinces. Try again.', 502);
    echo json_encode(['ok'=>true,'items'=>$r['data']], JSON_UNESCAPED_UNICODE);
    exit();

  case 'citiesmun':
    if ($code === '') bad('Missing province code.');
    $url = sprintf($map['citiesmun_by_province'], rawurlencode($code));
    $r = fetch_json($url);
    if (!$r['ok']) bad('Failed to load cities/municipalities. Try again.', 502);
    echo json_encode(['ok'=>true,'items'=>$r['data']], JSON_UNESCAPED_UNICODE);
    exit();

  case 'barangays':
    if ($code === '') bad('Missing city/municipality code.');
    $url = sprintf($map['barangays_by_citymun'], rawurlencode($code));
    $r = fetch_json($url);
    if (!$r['ok']) bad('Failed to load barangays. Try again.', 502);
    echo json_encode(['ok'=>true,'items'=>$r['data']], JSON_UNESCAPED_UNICODE);
    exit();

  default:
    bad('Invalid level.');
}