<?php
session_start();
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/csrf.php";
require_once __DIR__ . "/includes/admin_auth.php";
require_admin();

$title = "Requests";
$active = "requests";

$status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$where = "WHERE 1=1";
$types = "";
$params = [];

if ($status !== '' && in_array($status, ['PENDING','SUBMITTED','ACCEPTED','DECLINED'], true)) {
  $where .= " AND r.status=?";
  $types .= "s";
  $params[] = $status;
}

if ($search !== '') {
  $where .= " AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.cp_no LIKE ? OR c.address LIKE ? OR r.item_label LIKE ?)";
  $types .= "sssss";
  $like = "%{$search}%";
  array_push($params, $like, $like, $like, $like, $like);
}

// actions (accept/decline) via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['request_id'])) {
  Csrf::verify($_POST['_csrf'] ?? null);
  $rid = (int)$_POST['request_id'];
  $action = $_POST['action'];

  if (in_array($action, ['accept','decline'], true)) {
    $newStatus = $action === 'accept' ? 'ACCEPTED' : 'DECLINED';
    $stmt = $conn->prepare("UPDATE requests SET status=? WHERE id=?");
    $stmt->bind_param("si", $newStatus, $rid);
    $stmt->execute();
    $stmt->close();
  }
  header("Location: requests.php");
  exit();
}

// fetch rows
$sql = "
SELECT r.id, r.request_type, r.item_label, r.status, r.created_at,
       c.first_name, c.middle_name, c.last_name, c.address, c.cp_no
FROM requests r
JOIN clients c ON c.id = r.client_id
{$where}
ORDER BY r.created_at DESC
LIMIT 200
";

$stmt = $conn->prepare($sql);
if ($types !== "") $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

require __DIR__ . "/partials/header.php";
require __DIR__ . "/partials/footer.php";

function badge($s){
  return match($s){
    'ACCEPTED' => 'bg-success',
    'DECLINED' => 'bg-danger',
    default => 'bg-warning text-dark',
  };
}
?>
<div class="topbar mb-3">
  <div class="fw-bold">Requests</div>
  <div class="text-muted small">Approve/Decline requests (SMS soon)</div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2" method="get">
      <div class="col-md-3">
        <select class="form-select" name="status">
          <option value="">All Status</option>
          <?php foreach(['PENDING','SUBMITTED','ACCEPTED','DECLINED'] as $s): ?>
            <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <input class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, phone, address, item...">
      </div>
      <div class="col-md-3 d-grid">
        <button class="btn btn-primary" type="submit">Filter</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>Client</th>
          <th>Type</th>
          <th>Item</th>
          <th>Status</th>
          <th>Date</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if(!$rows): ?>
        <tr><td colspan="7" class="text-muted">No requests found.</td></tr>
      <?php else: foreach($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td>
            <div class="fw-semibold">
              <?= htmlspecialchars($r['first_name']." ".$r['middle_name']." ".$r['last_name']) ?>
            </div>
            <div class="text-muted small"><?= htmlspecialchars($r['cp_no']) ?> • <?= htmlspecialchars($r['address']) ?></div>
          </td>
          <td><?= htmlspecialchars($r['request_type']) ?></td>
          <td><?= htmlspecialchars($r['item_label']) ?></td>
          <td><span class="badge badge-status <?= badge($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
          <td class="text-muted small"><?= htmlspecialchars($r['created_at']) ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-secondary" href="request_view.php?id=<?= (int)$r['id'] ?>">View</a>

            <?php if (in_array($r['status'], ['PENDING','SUBMITTED'], true)): ?>
              <form method="post" class="d-inline">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="action" value="accept">
                <button class="btn btn-sm btn-accent" type="submit">Accept</button>
              </form>

              <form method="post" class="d-inline">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="action" value="decline">
                <button class="btn btn-sm btn-outline-danger" type="submit">Decline</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . "/partials/footer.php"; ?>