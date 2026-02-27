<?php
session_start();
require_once __DIR__ . "/includes/csrf.php";
require_once __DIR__ . "/includes/helpers.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/lib/phpqrcode/qrlib.php";

Csrf::verify($_POST['_csrf'] ?? null);

$client  = $_SESSION['client_info'] ?? null;
$sel     = $_SESSION['selected'] ?? null;
$uploads = $_SESSION['uploads'] ?? [];

if (!$client || !$sel || empty($sel['offering_id'])) {
  header("Location: index.php");
  exit();
}

/* SECURITY: re-fetch offering (price/requirements) from DB */
$offId = (int)$sel['offering_id'];
$stmt = $conn->prepare("SELECT id,type,item_key,label,price,requirements_json,is_active FROM offerings WHERE id=? AND is_active=1 LIMIT 1");
$stmt->bind_param("i", $offId);
$stmt->execute();
$res = $stmt->get_result();
$off = $res->fetch_assoc();
$stmt->close();

if (!$off) {
  http_response_code(400);
  exit("Invalid offering.");
}

$reqArr = [];
if (!empty($off['requirements_json'])) {
  $decoded = json_decode($off['requirements_json'], true);
  if (is_array($decoded)) $reqArr = $decoded;
}

if ($off['type'] === 'certificate' && count($reqArr) > 0) {

  // Build a set of requirement labels that have at least one uploaded file
  $haveLabels = [];
  foreach ($uploads as $u) {
    $lbl = (string)($u['requirement_label'] ?? '');
    if ($lbl !== '') $haveLabels[$lbl] = true;
  }

  // Ensure every requirement label has at least one uploaded file
  foreach ($reqArr as $lbl) {
    if (empty($haveLabels[$lbl])) {
      http_response_code(400);
      exit("Missing attachment for requirement: " . $lbl);
    }
  }
}

$conn->begin_transaction();

try {
  // Insert client
  $stmt = $conn->prepare("INSERT INTO clients (first_name, middle_name, last_name, address, cp_no) VALUES (?,?,?,?,?)");
  $stmt->bind_param("sssss", $client['first_name'], $client['middle_name'], $client['last_name'], $client['address'], $client['cp_no']);
  $stmt->execute();
  $client_id = $stmt->insert_id;
  $stmt->close();

  // Insert request (store price)
  $price = (float)$off['price'];
  $stmt = $conn->prepare("INSERT INTO requests (client_id, request_type, item_key, item_label, price, status) VALUES (?,?,?,?,?, 'SUBMITTED')");
  $stmt->bind_param("isssd", $client_id, $off['type'], $off['item_key'], $off['label'], $price);
  $stmt->execute();
  $request_id = $stmt->insert_id;
  $stmt->close();

  // Finalize uploads
  if (!empty($uploads)) {
    $finalDir = __DIR__ . "/uploads/request_" . $request_id;
    ensure_uploads_dir($finalDir);

    foreach ($uploads as $u) {
      $src = __DIR__ . "/" . $u['file_path'];
      if (!is_file($src)) throw new Exception("Staged file missing for: ".$u['requirement_label']);

      $base = basename($src);
      $dest = $finalDir . "/" . $base;

      if (!rename($src, $dest)) throw new Exception("Failed to finalize upload for: ".$u['requirement_label']);

      $dbPath = "uploads/request_" . $request_id . "/" . $base;

      $stmt = $conn->prepare("
        INSERT INTO request_attachments
          (request_id, requirement_label, file_path, original_name, mime_type, file_size)
        VALUES (?,?,?,?,?,?)
      ");
      $stmt->bind_param(
        "issssi",
        $request_id,
        $u['requirement_label'],
        $dbPath,
        $u['original_name'],
        $u['mime_type'],
        $u['file_size']
      );
      $stmt->execute();
      $stmt->close();
    }

    $stageDir = __DIR__ . "/uploads/stage_" . session_id();
    @rmdir($stageDir);
  }

  $conn->commit();

  unset($_SESSION['client_info'], $_SESSION['selected'], $_SESSION['uploads']);

} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  exit("Submit failed: " . $e->getMessage());
}

// Build QR payload (JSON)
$qrPayload = [
  'v' => 1,
  'client' => [
    'first_name'  => $client['first_name'],
    'middle_name' => $client['middle_name'],
    'last_name'   => $client['last_name'],
    'address'     => $client['address'],
    'cp_no'       => $client['cp_no'],
  ],
  'items' => [
    [
      'type' => $off['type'],
      'key'  => $off['item_key'],
      'label'=> $off['label'],
      'price'=> (float)$price
    ]
  ],
  'request_id' => (int)$request_id
];

$qrText = json_encode($qrPayload, JSON_UNESCAPED_UNICODE);

// Ensure qrcodes folder exists
$qrDir = __DIR__ . "/qrcodes";
if (!is_dir($qrDir)) { @mkdir($qrDir, 0755, true); }

// File name
$qrFileName = "REQ_" . (int)$request_id . ".png";
$qrAbsPath  = $qrDir . "/" . $qrFileName;

// Generate QR PNG
QRcode::png($qrText, $qrAbsPath, QR_ECLEVEL_L, 6);
$qrWebPath = "qrcodes/" . $qrFileName;
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Submitted</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="app-body">
<main class="app-shell">
  <div class="container py-4 py-lg-5">
    <div class="row justify-content-center">
      <div class="col-xl-8">

        <!-- Top Bar -->
        <div class="app-topbar app-reveal">
          <div>
            <div class="app-badge">
              <span class="app-badge__dot"></span>
              Completed
            </div>
            <h1 class="app-title app-title--sm mb-1">Request Submitted</h1>
            <div class="app-subtitle">
              Your request was submitted successfully. Please keep your reference number.
            </div>
          </div>

          <div class="app-topbar__actions">
            <a class="btn btn-outline-secondary app-btn-outline" href="index.php">New Request</a>
          </div>
        </div>

        <!-- Stepper (all done) -->
        <div class="app-stepper app-reveal">
          <div class="app-step is-done">
            <div class="app-step__dot">1</div>
            <div class="app-step__label">Client Info</div>
          </div>
          <div class="app-step is-done">
            <div class="app-step__dot">2</div>
            <div class="app-step__label">Select</div>
          </div>
          <div class="app-step is-done">
            <div class="app-step__dot">3</div>
            <div class="app-step__label">Upload</div>
          </div>
          <div class="app-step is-done">
            <div class="app-step__dot">4</div>
            <div class="app-step__label">Summary</div>
          </div>
        </div>

        <!-- Success Card -->
        <div class="app-card app-reveal">
          <div class="app-card__header">
            <div class="app-successhead">
              <div class="app-successicon" aria-hidden="true">✓</div>
              <div>
                <h5 class="mb-1">Submission Complete</h5>
                <div class="app-card__hint">
                  Use the Reference ID for follow-ups and status checking.
                </div>
              </div>
            </div>
          </div>

          <div class="app-card__body">
            <div class="row g-3">
              <div class="col-lg-7">
                <div class="app-refcard" id="slip">
                  <div class="app-refcard__row">
                    <div class="k">Reference ID</div>
                    <div class="v">
                      <span class="app-refid" id="refText"><?= (int)$request_id ?></span>
                      <button class="btn btn-outline-secondary app-btn-outline app-btn-outline--sm" type="button" id="copyBtn">
                        Copy
                      </button>
                    </div>
                  </div>

                  <div class="app-refcard__row">
                    <div class="k">Amount</div>
                    <div class="v">₱<?= number_format((float)$price, 2) ?></div>
                  </div>

                  <div class="app-refcard__row">
                    <div class="k">Type</div>
                    <div class="v"><?= h(ucfirst((string)$off['type'])) ?></div>
                  </div>

                  <div class="app-refcard__row">
                    <div class="k">Item</div>
                    <div class="v"><?= h((string)$off['label']) ?></div>
                  </div>

                  <div class="app-divider"></div>

                  <div class="text-center mt-3">
                    <div class="small text-muted mb-2">Scan this QR at the office to auto-fill your details.</div>
                      <img src="<?= h($qrWebPath) ?>" alt="QR Code" style="width:220px; max-width:100%;">
                        <div class="mt-2">
                          <a class="btn btn-outline-secondary app-btn-outline" href="<?= h($qrWebPath) ?>" download="QR_<?= (int)$request_id ?>.png">
                            Download QR
                          </a>
                        </div>
                      </div>

                  <div class="app-refcard__note">
                    <strong>Next step:</strong> Wait for verification and updates from the office.
                    Keep your phone reachable for notifications.
                  </div>
                </div>

                <div class="app-help mt-3">
                  If you made a mistake, submit a new request and inform the office to disregard the previous one.
                </div>
              </div>

              <div class="col-lg-5">
                <div class="app-infobox">
                  <div class="app-infobox__title">What happens next?</div>
                  <ol class="app-infobox__list">
                    <li>Office reviews your submission and attachments.</li>
                    <li>Status will be updated once processed.</li>
                    <li>You may be contacted for clarification if needed.</li>
                  </ol>

                  <div class="app-divider"></div>

                  <div class="app-infobox__title">Quick actions</div>
                  <div class="d-grid gap-2">
                    <button class="btn btn-primary app-btn" type="button" id="printBtn">
                      Print Slip
                      <span class="app-btn__arrow" aria-hidden="true">→</span>
                    </button>
                    <a class="btn btn-outline-secondary app-btn-outline" href="index.php">
                      Create Another Request
                    </a>
                  </div>
                </div>
              </div>
            </div>

            <div class="app-footer mt-3">
              <div class="small">
                Thank you. This system is designed for faster and more organized LGU appointment processing.
              </div>
            </div>

          </div>
        </div>

      </div>
    </div>
  </div>
</main>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  const ref = document.getElementById('refText');
  const copyBtn = document.getElementById('copyBtn');
  const printBtn = document.getElementById('printBtn');

  if (copyBtn && ref) {
    copyBtn.addEventListener('click', async () => {
      const text = (ref.textContent || '').trim();
      try {
        await navigator.clipboard.writeText(text);
        copyBtn.textContent = 'Copied';
        setTimeout(() => copyBtn.textContent = 'Copy', 1200);
      } catch (e) {
        // fallback: select text
        const r = document.createRange();
        r.selectNodeContents(ref);
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(r);
        copyBtn.textContent = 'Select';
        setTimeout(() => copyBtn.textContent = 'Copy', 1200);
      }
    });
  }

  if (printBtn) {
    printBtn.addEventListener('click', () => {
      window.print();
    });
  }
})();
</script>
</body>
</html>