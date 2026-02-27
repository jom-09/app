<?php
session_start();
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/csrf.php";
require_once __DIR__ . "/includes/admin_auth.php";
require_admin();

$title = "Certificates & Services";
$active = "offerings";

$errors = [];
$success = '';

/* ===============================
   HELPERS
================================== */
function normalize_key(string $k): string {
  $k = strtolower(trim($k));
  $k = preg_replace("/[^a-z0-9_]/", "_", $k);
  $k = preg_replace("/_+/", "_", $k);
  return trim($k, "_");
}

function clean_price($v): float {
  $v = trim((string)$v);
  $v = preg_replace("/[^0-9.]/", "", $v);
  if ($v === '' || !is_numeric($v)) return 0.0;
  $n = (float)$v;
  if ($n < 0) $n = 0;
  if ($n > 99999999) $n = 99999999;
  return $n;
}

/* ===============================
   FORM HANDLER
================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  Csrf::verify($_POST['_csrf'] ?? null);
  $mode = $_POST['mode'] ?? '';

  /* ========= ADD / EDIT ========= */
  if ($mode === 'add' || $mode === 'edit') {

    $id         = (int)($_POST['id'] ?? 0);
    $type       = $_POST['type'] ?? '';
    $label      = trim((string)($_POST['label'] ?? ''));
    $item_key   = normalize_key($_POST['item_key'] ?? '');
    $price      = clean_price($_POST['price'] ?? '0');
    $reqText    = trim((string)($_POST['requirements'] ?? ''));
    $is_active  = isset($_POST['is_active']) ? 1 : 0;

    if (!in_array($type, ['certificate','service'], true))
        $errors[] = "Invalid type.";

    if ($label === '')
        $errors[] = "Label is required.";

    if ($item_key === '')
        $errors[] = "Item key is required.";

    /* requirements -> json */
    $reqArr = [];
    if ($reqText !== '') {
      foreach (preg_split("/\R+/", $reqText) as $line) {
        $line = trim($line);
        if ($line !== '') $reqArr[] = $line;
      }
    }

    $reqJson = json_encode($reqArr, JSON_UNESCAPED_UNICODE);

    if (!$errors) {

      /* ===== ADD ===== */
      if ($mode === 'add') {

        $stmt = $conn->prepare("
          INSERT INTO offerings
          (type,item_key,label,price,requirements_json,is_active)
          VALUES (?,?,?,?,?,?)
        ");

        $stmt->bind_param(
          "sssdsi",
          $type,
          $item_key,
          $label,
          $price,
          $reqJson,
          $is_active
        );

        $stmt->execute();
        $stmt->close();

        $success = "Added successfully.";
      }

      /* ===== EDIT ===== */
      else {

        $stmt = $conn->prepare("
          UPDATE offerings
          SET type=?, item_key=?, label=?, price=?, requirements_json=?, is_active=?
          WHERE id=?
        ");

        // ✅ CORRECT TYPES (7 VARIABLES)
        $stmt->bind_param(
          "sssdsii",
          $type,
          $item_key,
          $label,
          $price,
          $reqJson,
          $is_active,
          $id
        );

        $stmt->execute();
        $stmt->close();

        $success = "Updated successfully.";
      }
    }
  }

  /* ========= DELETE ========= */
  if ($mode === 'delete') {

    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
      $stmt = $conn->prepare("DELETE FROM offerings WHERE id=?");
      $stmt->bind_param("i", $id);
      $stmt->execute();
      $stmt->close();

      $success = "Deleted.";
    }
  }
}

/* ===============================
   LOAD DATA
================================== */
$items = [];
$res = $conn->query("SELECT * FROM offerings ORDER BY type ASC, label ASC");
while ($r = $res->fetch_assoc()) {
  $items[] = $r;
}

require __DIR__ . "/partials/header.php";
require __DIR__ . "/partials/footer.php";
?>

<div class="topbar mb-3">
  <div class="fw-bold">Certificates & Services</div>
  <div class="text-muted small">Manage client selectable items (with prices)</div>
</div>

<?php if($success): ?>
<div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if($errors): ?>
<div class="alert alert-danger">
  <ul class="mb-0">
    <?php foreach($errors as $e): ?>
      <li><?= htmlspecialchars($e) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="card mb-3">
  <div class="card-header bg-white"><strong>Add New</strong></div>
  <div class="card-body">
    <form method="post" class="row g-2">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
      <input type="hidden" name="mode" value="add">

      <div class="col-md-3">
        <label class="form-label">Type</label>
        <select class="form-select" name="type">
          <option value="certificate">certificate</option>
          <option value="service">service</option>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Item Key</label>
        <input class="form-control" name="item_key" placeholder="e.g. tax_decl_own" required>
      </div>

      <div class="col-md-4">
        <label class="form-label">Label</label>
        <input class="form-control" name="label" placeholder="Display name" required>
      </div>

      <div class="col-md-2">
        <label class="form-label">Price</label>
        <input class="form-control" name="price" placeholder="0.00" required>
      </div>

      <div class="col-12">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="is_active" id="activeAdd" checked>
          <label class="form-check-label" for="activeAdd">Active</label>
        </div>
      </div>

      <div class="col-12">
        <label class="form-label">Requirements (one per line)</label>
        <textarea class="form-control" name="requirements" rows="3" placeholder="Leave blank for services / no requirements"></textarea>
      </div>

      <div class="col-12 d-flex justify-content-end">
        <button class="btn btn-primary" type="submit">Add</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header bg-white"><strong>List</strong></div>
  <div class="card-body table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <th>Type</th><th>Key</th><th>Label</th><th class="text-end">Price</th><th>Active</th><th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if(!$items): ?>
        <tr><td colspan="6" class="text-muted">No offerings yet.</td></tr>
      <?php else: foreach($items as $it): ?>
        <tr>
          <td><?= htmlspecialchars($it['type']) ?></td>
          <td class="text-muted small"><?= htmlspecialchars($it['item_key']) ?></td>
          <td><?= htmlspecialchars($it['label']) ?></td>
          <td class="text-end">₱<?= number_format((float)$it['price'],2) ?></td>
          <td><?= (int)$it['is_active']===1 ? 'Yes' : 'No' ?></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#edit<?= (int)$it['id'] ?>">Edit</button>
            <form method="post" class="d-inline" onsubmit="return confirm('Delete this item?');">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
              <input type="hidden" name="mode" value="delete">
              <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
              <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>

        <tr class="collapse" id="edit<?= (int)$it['id'] ?>">
          <td colspan="6">
            <div class="p-3 bg-light rounded">
              <?php
                $reqArr = [];
                if (!empty($it['requirements_json'])) {
                  $decoded = json_decode($it['requirements_json'], true);
                  if (is_array($decoded)) $reqArr = $decoded;
                }
              ?>
              <form method="post" class="row g-2">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <input type="hidden" name="mode" value="edit">
                <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">

                <div class="col-md-3">
                  <label class="form-label">Type</label>
                  <select class="form-select" name="type">
                    <option value="certificate" <?= $it['type']==='certificate'?'selected':'' ?>>certificate</option>
                    <option value="service" <?= $it['type']==='service'?'selected':'' ?>>service</option>
                  </select>
                </div>

                <div class="col-md-3">
                  <label class="form-label">Item Key</label>
                  <input class="form-control" name="item_key" value="<?= htmlspecialchars($it['item_key']) ?>" required>
                </div>

                <div class="col-md-4">
                  <label class="form-label">Label</label>
                  <input class="form-control" name="label" value="<?= htmlspecialchars($it['label']) ?>" required>
                </div>

                <div class="col-md-2">
                  <label class="form-label">Price</label>
                  <input class="form-control" name="price" value="<?= htmlspecialchars(number_format((float)$it['price'],2,'.','')) ?>" required>
                </div>

                <div class="col-12">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" id="active<?= (int)$it['id'] ?>" <?= (int)$it['is_active']===1?'checked':'' ?>>
                    <label class="form-check-label" for="active<?= (int)$it['id'] ?>">Active</label>
                  </div>
                </div>

                <div class="col-12">
                  <label class="form-label">Requirements (one per line)</label>
                  <textarea class="form-control" name="requirements" rows="3"><?= htmlspecialchars(implode("\n",$reqArr)) ?></textarea>
                </div>

                <div class="col-12 d-flex justify-content-end">
                  <button class="btn btn-primary" type="submit">Save</button>
                </div>
              </form>
            </div>
          </td>
        </tr>

      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . "/partials/footer.php"; ?>