<?php
session_start();
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/includes/admin_auth.php";
require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit("Missing id."); }

$stmt = $conn->prepare("
  SELECT r.*, c.first_name,c.middle_name,c.last_name,c.address,c.cp_no
  FROM requests r
  JOIN clients c ON c.id=r.client_id
  WHERE r.id=?
  LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$req = $res->fetch_assoc();
$stmt->close();

if (!$req) { http_response_code(404); exit("Request not found."); }

$att = [];
$stmt = $conn->prepare("SELECT id, requirement_label, original_name, file_path, mime_type, file_size FROM request_attachments WHERE request_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
while($r=$res->fetch_assoc()) $att[] = $r;
$stmt->close();

$title = "Request #".$id;
$active = "requests";
require __DIR__ . "/partials/header.php";
require __DIR__ . "/partials/sidebar.php";
?>
<div class="topbar mb-3 d-flex justify-content-between align-items-center">
  <div>
    <div class="fw-bold">Request #<?= (int)$req['id'] ?></div>
    <div class="text-muted small"><?= htmlspecialchars($req['created_at']) ?> • <?= htmlspecialchars($req['status']) ?></div>
  </div>
  <a class="btn btn-outline-secondary btn-sm" href="requests.php">Back</a>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header bg-white"><strong>Client</strong></div>
      <div class="card-body">
        <div><strong>Name:</strong> <?= htmlspecialchars($req['first_name']." ".$req['middle_name']." ".$req['last_name']) ?></div>
        <div><strong>CP No:</strong> <?= htmlspecialchars($req['cp_no']) ?></div>
        <div><strong>Address:</strong> <?= htmlspecialchars($req['address']) ?></div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card">
      <div class="card-header bg-white"><strong>Request</strong></div>
      <div class="card-body">
        <div><strong>Type:</strong> <?= htmlspecialchars($req['request_type']) ?></div>
        <div><strong>Item:</strong> <?= htmlspecialchars($req['item_label']) ?></div>
        <div><strong>Status:</strong> <?= htmlspecialchars($req['status']) ?></div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-header bg-white"><strong>Attachments</strong></div>
      <div class="card-body table-responsive">
        <table class="table table-sm align-middle">
          <thead><tr><th>Requirement</th><th>File</th><th class="text-end">Action</th></tr></thead>
          <tbody>
            <?php if(!$att): ?>
              <tr><td colspan="3" class="text-muted">No attachments.</td></tr>
            <?php else: foreach($att as $a): ?>
              <tr>
                <td><?= htmlspecialchars($a['requirement_label']) ?></td>
                <td class="text-muted small"><?= htmlspecialchars($a['original_name']) ?></td>
                <td class="text-end">

  <!-- VIEW BUTTON (only if image) -->
  <?php if (str_starts_with($a['mime_type'], 'image/')): ?>
    <button
      class="btn btn-sm btn-outline-success"
      data-bs-toggle="modal"
      data-bs-target="#viewImageModal"
      data-file="download.php?id=<?= (int)$a['id'] ?>"
      data-label="<?= htmlspecialchars($a['requirement_label']) ?>"
    >
      View
    </button>
  <?php endif; ?>

  <a class="btn btn-sm btn-outline-primary"
     href="download.php?id=<?= (int)$a['id'] ?>">
     Download
  </a>

</td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- IMAGE VIEW MODAL -->
<div class="modal fade" id="viewImageModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="imageModalTitle">Attachment Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body text-center">
        <img id="previewImage"
             src=""
             class="img-fluid rounded"
             alt="Preview">
      </div>

    </div>
  </div>
</div>

<script>
const viewModal = document.getElementById('viewImageModal');

viewModal.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;

    const file = button.getAttribute('data-file');
    const label = button.getAttribute('data-label');

    document.getElementById('previewImage').src = file;
    document.getElementById('imageModalTitle').textContent =
        "Preview - " + label;
});

// clear image when modal closes (memory cleanup)
viewModal.addEventListener('hidden.bs.modal', function () {
    document.getElementById('previewImage').src = '';
});
</script>

<?php require __DIR__ . "/partials/footer.php"; ?>