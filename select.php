<?php
session_start();
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/csrf.php";
require_once __DIR__ . "/includes/helpers.php";

/* ===============================
   1) If coming from index.php form submit
================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['first_name'])) {
    Csrf::verify($_POST['_csrf'] ?? null);

    $first  = clean_name($_POST['first_name'] ?? '');
    $middle = clean_name($_POST['middle_name'] ?? '');
    $last   = clean_name($_POST['last_name'] ?? '');
    $addr   = trim((string)($_POST['address'] ?? ''));
    $cp     = clean_phone($_POST['cp_no'] ?? '');

    $errors = [];
    if ($first === '') $errors[] = "First name is required.";
    if ($last === '')  $errors[] = "Last name is required.";
    if ($addr === '')  $errors[] = "Address is required.";
    if ($cp === '')    $errors[] = "CP Number is required.";

    if ($errors) {
        $_SESSION['flash_errors'] = $errors;
        $_SESSION['client_info'] = [
            'first_name'=>$first,'middle_name'=>$middle,'last_name'=>$last,'address'=>$addr,'cp_no'=>$cp
        ];
        header("Location: index.php");
        exit();
    }

    $_SESSION['client_info'] = [
        'first_name'=>$first,'middle_name'=>$middle,'last_name'=>$last,'address'=>$addr,'cp_no'=>$cp
    ];

    // clear previous selection
    unset($_SESSION['selected'], $_SESSION['uploads']);
}

if (empty($_SESSION['client_info'])) {
    header("Location: index.php");
    exit();
}

/* ===============================
   2) Handle selection from modal (secure: by offering_id)
================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pick_id'])) {
    Csrf::verify($_POST['_csrf'] ?? null);

    $pickId = (int)($_POST['pick_id'] ?? 0);
    if ($pickId <= 0) {
        http_response_code(400);
        exit("Invalid selection.");
    }

    $stmt = $conn->prepare("SELECT id,type,item_key,label,price,requirements_json FROM offerings WHERE id=? AND is_active=1 LIMIT 1");
    $stmt->bind_param("i", $pickId);
    $stmt->execute();
    $res = $stmt->get_result();
    $off = $res->fetch_assoc();
    $stmt->close();

    if (!$off) {
        http_response_code(400);
        exit("Invalid selection.");
    }

    $reqArr = [];
    if (!empty($off['requirements_json'])) {
        $decoded = json_decode($off['requirements_json'], true);
        if (is_array($decoded)) $reqArr = $decoded;
    }

    $_SESSION['selected'] = [
        'offering_id' => (int)$off['id'],
        'type'        => $off['type'],
        'key'         => $off['item_key'],
        'label'       => $off['label'],
        'price'       => (float)$off['price'],
        'requirements'=> $reqArr
    ];

    unset($_SESSION['uploads']);

    if (empty($reqArr)) {
        header("Location: summary.php");
    } else {
        header("Location: upload.php");
    }
    exit();
}

/* ===============================
   3) Load offerings for display
================================== */
$certs = [];
$services = [];

$res = $conn->query("SELECT id,type,label,price,requirements_json FROM offerings WHERE is_active=1 ORDER BY type ASC, label ASC");
while ($r = $res->fetch_assoc()) {
    $reqArr = [];
    if (!empty($r['requirements_json'])) {
        $decoded = json_decode($r['requirements_json'], true);
        if (is_array($decoded)) $reqArr = $decoded;
    }

    $item = [
        'id' => (int)$r['id'],
        'label' => $r['label'],
        'price' => (float)$r['price'],
        'requirements' => $reqArr
    ];

    if ($r['type'] === 'certificate') $certs[] = $item;
    else $services[] = $item;
}
?>
<<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Select Certificate or Service</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="app-body">
<main class="app-shell">
  <div class="container py-4 py-lg-5">
    <div class="row justify-content-center">
      <div class="col-xl-10">

        <!-- Top Bar -->
        <div class="app-topbar app-reveal">
          <div>
            <div class="app-badge">
              <span class="app-badge__dot"></span>
              Step 2 of 4
            </div>
            <h1 class="app-title app-title--sm mb-1">Select a Certificate or Service</h1>
            <div class="app-subtitle">
              Choose what you need. Requirements (if any) will be shown before proceeding.
            </div>
          </div>

          <div class="app-topbar__actions">
            <a class="btn btn-outline-secondary app-btn-outline" href="index.php">Edit Info</a>
          </div>
        </div>

        <!-- Stepper -->
        <div class="app-stepper app-reveal">
          <div class="app-step is-done">
            <div class="app-step__dot">1</div>
            <div class="app-step__label">Client Info</div>
          </div>
          <div class="app-step is-active">
            <div class="app-step__dot">2</div>
            <div class="app-step__label">Select</div>
          </div>
          <div class="app-step">
            <div class="app-step__dot">3</div>
            <div class="app-step__label">Upload</div>
          </div>
          <div class="app-step">
            <div class="app-step__dot">4</div>
            <div class="app-step__label">Summary</div>
          </div>
        </div>

        <!-- Content Card -->
        <div class="app-card app-reveal">
          <div class="app-card__header app-card__header--split">
            <div>
              <h5 class="mb-1">Available Offerings</h5>
              <div class="app-card__hint">Select an item to view requirements and proceed.</div>
            </div>
            <div class="app-filter">
              <span class="app-filter__pill">Certificates: <?= (int)count($certs) ?></span>
              <span class="app-filter__pill">Services: <?= (int)count($services) ?></span>
            </div>
          </div>

          <div class="app-card__body">
            <!-- Certificates -->
            <div class="app-section-head">
              <div class="app-section-title">Certificates</div>
              <div class="app-section-sub">Official documents that may require attachments.</div>
            </div>

            <?php if(!$certs): ?>
              <div class="alert alert-warning app-alert-soft">No active certificates yet.</div>
            <?php else: ?>
              <div class="row g-3">
                <?php foreach($certs as $c): ?>
                  <div class="col-sm-6 col-lg-4">
                    <div class="app-itemcard">
                      <div class="app-itemcard__body">
                        <div class="app-itemcard__top">
                          <div class="app-chip app-chip--cert">Certificate</div>
                          <div class="app-price">₱<?= number_format($c['price'], 2) ?></div>
                        </div>

                        <h5 class="app-itemcard__title"><?= h($c['label']) ?></h5>

                        <div class="app-itemcard__meta">
                          <?php if(empty($c['requirements'])): ?>
                            <span class="app-meta ok">No requirements</span>
                          <?php else: ?>
                            <span class="app-meta warn"><?= (int)count($c['requirements']) ?> requirement(s)</span>
                          <?php endif; ?>
                        </div>
                      </div>

                      <div class="app-itemcard__footer">
                        <button
                          class="btn btn-primary w-100 app-btn"
                          data-bs-toggle="modal"
                          data-bs-target="#reqModal"
                          data-id="<?= (int)$c['id'] ?>"
                          data-label="<?= h($c['label']) ?>"
                          data-price="<?= h(number_format($c['price'],2)) ?>"
                          data-req='<?= h(json_encode($c['requirements'])) ?>'
                        >
                          Select
                          <span class="app-btn__arrow" aria-hidden="true">→</span>
                        </button>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <!-- Divider -->
            <div class="app-divider"></div>

            <!-- Services -->
            <div class="app-section-head">
              <div class="app-section-title">Services</div>
              <div class="app-section-sub">Transactions or requests processed by the office.</div>
            </div>

            <?php if(!$services): ?>
              <div class="alert alert-warning app-alert-soft">No active services yet.</div>
            <?php else: ?>
              <div class="row g-3">
                <?php foreach($services as $s): ?>
                  <div class="col-sm-6 col-lg-4">
                    <div class="app-itemcard">
                      <div class="app-itemcard__body">
                        <div class="app-itemcard__top">
                          <div class="app-chip app-chip--svc">Service</div>
                          <div class="app-price">₱<?= number_format($s['price'], 2) ?></div>
                        </div>

                        <h5 class="app-itemcard__title"><?= h($s['label']) ?></h5>

                        <div class="app-itemcard__meta">
                          <span class="app-meta ok">No requirements</span>
                        </div>
                      </div>

                      <div class="app-itemcard__footer">
                        <button
                          class="btn btn-primary w-100 app-btn app-btn--alt"
                          data-bs-toggle="modal"
                          data-bs-target="#reqModal"
                          data-id="<?= (int)$s['id'] ?>"
                          data-label="<?= h($s['label']) ?>"
                          data-price="<?= h(number_format($s['price'],2)) ?>"
                          data-req='<?= h(json_encode([])) ?>'
                        >
                          Select
                          <span class="app-btn__arrow" aria-hidden="true">→</span>
                        </button>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

          </div>
        </div>

        <footer class="app-footer">
          <div class="small">Tip: If your selected item has requirements, you’ll upload them on the next page.</div>
        </footer>

      </div>
    </div>
  </div>
</main>

<!-- Requirements Modal -->
<div class="modal fade" id="reqModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content app-modal">
      <div class="modal-header app-modal__header">
        <div>
          <h5 class="modal-title" id="modalTitle">Requirements</h5>
          <div class="app-modal__sub">Review before proceeding.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body app-modal__body">
        <div class="app-modal__price">
          <span class="label">Price</span>
          <span class="value" id="modalPrice"></span>
        </div>
        <div id="reqList"></div>
      </div>

      <div class="modal-footer app-modal__footer">
        <form method="post" class="ms-auto d-flex gap-2">
          <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
          <input type="hidden" name="pick_id" id="pickId">
          <button type="button" class="btn btn-outline-secondary app-btn-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary app-btn">Proceed <span class="app-btn__arrow" aria-hidden="true">→</span></button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script>
const modal = document.getElementById('reqModal');
modal.addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  const id = btn.getAttribute('data-id');
  const label = btn.getAttribute('data-label');
  const price = btn.getAttribute('data-price');
  const req = JSON.parse(btn.getAttribute('data-req') || "[]");

  document.getElementById('modalTitle').textContent = label + " - Requirements";
  document.getElementById('modalPrice').textContent = "₱" + price;
  document.getElementById('pickId').value = id;

  const box = document.getElementById('reqList');
  if (!req.length) {
    box.innerHTML = '<div class="app-empty ok">No requirements to be attached.</div>';
    return;
  }

  let html = '<div class="app-reqbox"><ol class="mb-2">';
  req.forEach(r => html += '<li>' + r.replaceAll("<","&lt;").replaceAll(">","&gt;") + '</li>');
  html += '</ol><div class="app-help">You will upload one screenshot per requirement on the next page.</div></div>';
  box.innerHTML = html;
});
</script>
</body>
</html>