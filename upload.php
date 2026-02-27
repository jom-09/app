<?php
session_start();
require_once __DIR__ . "/includes/csrf.php";
require_once __DIR__ . "/includes/helpers.php";

$sel = $_SESSION['selected'] ?? null;
if (!$sel) { header("Location: select.php"); exit(); }
if (empty($sel['requirements'])) { header("Location: summary.php"); exit(); }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify($_POST['_csrf'] ?? null);

    $reqs = $sel['requirements'];
    $uploads = [];

    $maxBytes = 5 * 1024 * 1024; // 5MB each

    foreach ($reqs as $i => $label) {
        $field = "req_" . $i;

        if (empty($_FILES[$field])) {
            $errors[] = "Attachment required for: {$label}";
            continue;
        }

        $names    = $_FILES[$field]['name'] ?? null;
        $tmpNames = $_FILES[$field]['tmp_name'] ?? null;
        $sizes    = $_FILES[$field]['size'] ?? null;
        $errs     = $_FILES[$field]['error'] ?? null;

        // normalize to arrays (works for single or multiple)
        if (!is_array($names)) {
            $names = [$names];
            $tmpNames = [$tmpNames];
            $sizes = [$sizes];
            $errs  = [$errs];
        }

        // require at least 1 file for this requirement
        $hasAtLeastOne = false;

        for ($k = 0; $k < count($names); $k++) {
            $orig = (string)$names[$k];
            $tmp  = (string)$tmpNames[$k];
            $size = (int)$sizes[$k];
            $err  = (int)$errs[$k];

            // skip empty slot
            if ($orig === '' && $err === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($err !== UPLOAD_ERR_OK) {
                $errors[] = "Upload error for {$label} (file: {$orig}).";
                continue;
            }

            $hasAtLeastOne = true;

            if ($size <= 0 || $size > $maxBytes) {
                $errors[] = "File size must be 1 byte to 5MB for: {$label} (file: {$orig})";
                continue;
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmp);

            if (!is_allowed_image_mime($mime)) {
                $errors[] = "Only JPG/PNG/WebP images are allowed for: {$label} (file: {$orig})";
                continue;
            }

            $ext = match($mime) {
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
                default      => 'bin'
            };

            $uploads[] = [
                'requirement_label' => $label,
                'tmp_path' => $tmp,
                'mime' => $mime,
                'size' => $size,
                'original_name' => $orig,
                'ext' => $ext,
            ];
        }

        if (!$hasAtLeastOne) {
            $errors[] = "Attachment required for: {$label}";
        }
    }

    if (!$errors) {
        $stageBase = __DIR__ . "/uploads/stage_" . session_id();
        ensure_uploads_dir($stageBase);

        $stored = [];
        foreach ($uploads as $u) {
            $safeName = preg_replace("/[^a-zA-Z0-9_\-]/", "_", pathinfo($u['original_name'], PATHINFO_FILENAME));
            $filename = $safeName . "_" . bin2hex(random_bytes(6)) . "." . $u['ext'];
            $dest = $stageBase . "/" . $filename;

            if (!move_uploaded_file($u['tmp_path'], $dest)) {
                $errors[] = "Failed to save upload for: " . $u['requirement_label'];
                break;
            }

            $stored[] = [
                'requirement_label' => $u['requirement_label'],
                'file_path' => "uploads/stage_" . session_id() . "/" . $filename,
                'mime_type' => $u['mime'],
                'file_size' => $u['size'],
                'original_name' => $u['original_name'],
            ];
        }

        if (!$errors) {
            $_SESSION['uploads'] = $stored;
            header("Location: summary.php");
            exit();
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Upload Requirements</title>
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
              Step 3 of 4
            </div>
            <h1 class="app-title app-title--sm mb-1">Upload Requirements</h1>
            <div class="app-subtitle">
              Upload clear screenshots/photos (JPG/PNG/WebP, max 5MB each).
            </div>
          </div>

          <div class="app-topbar__actions">
            <a class="btn btn-outline-secondary app-btn-outline" href="select.php">Back</a>
          </div>
        </div>

        <!-- Stepper -->
        <div class="app-stepper app-reveal">
          <div class="app-step is-done">
            <div class="app-step__dot">1</div>
            <div class="app-step__label">Client Info</div>
          </div>
          <div class="app-step is-done">
            <div class="app-step__dot">2</div>
            <div class="app-step__label">Select</div>
          </div>
          <div class="app-step is-active">
            <div class="app-step__dot">3</div>
            <div class="app-step__label">Upload</div>
          </div>
          <div class="app-step">
            <div class="app-step__dot">4</div>
            <div class="app-step__label">Summary</div>
          </div>
        </div>

        <!-- Main Card -->
        <div class="app-card app-reveal">
          <div class="app-card__header">
            <div>
              <h5 class="mb-1"><?= h($sel['label']) ?></h5>
              <div class="app-card__hint">
                Add one or more photos per requirement. Keep text readable and not blurry.
              </div>
            </div>

            <div class="app-uploadhint">
              <div class="app-uploadhint__pill">Max: 5MB each</div>
              <div class="app-uploadhint__pill">Allowed: JPG/PNG/WebP</div>
            </div>
          </div>

          <div class="app-card__body">

            <?php if($errors): ?>
              <div class="alert alert-danger app-alert">
                <div class="app-alert__title">Please fix the following:</div>
                <ul class="mb-0">
                  <?php foreach($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="app-form" id="uploadForm">
              <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">

              <div class="row g-3">

                <?php foreach($sel['requirements'] as $i => $label): ?>
                  <div class="col-12">
                    <section class="app-reqcard" data-req-card="<?= (int)$i ?>">
                      <div class="app-reqcard__head">
                        <div class="app-reqcard__title">
                          <div class="app-reqcard__tag">Requirement <?= (int)($i+1) ?></div>
                          <h6 class="mb-0"><?= h($label) ?> <span class="req">*</span></h6>
                        </div>

                        <button
                          type="button"
                          class="btn btn-outline-primary app-btn-outline app-btn-outline--sm"
                          data-req-index="<?= (int)$i ?>"
                        >
                          + Add Picture
                        </button>
                      </div>

                      <div class="app-reqcard__body">
                        <div class="app-dropinfo">
                          <div class="app-dropinfo__icon" aria-hidden="true">⬆</div>
                          <div>
                            <div class="app-dropinfo__title">Upload clear screenshot/photo</div>
                            <div class="app-dropinfo__sub">You can add multiple photos for this requirement.</div>
                          </div>
                        </div>

                        <div id="req_group_<?= (int)$i ?>" class="app-filegroup">
                          <!-- first input -->
                          <div class="app-fileline">
                            <input
                              class="form-control app-input app-fileinput"
                              type="file"
                              name="req_<?= (int)$i ?>[]"
                              accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                              required
                              data-preview-target="prev_<?= (int)$i ?>_0"
                            >
                            <button type="button" class="btn btn-outline-danger btn-sm app-remove" disabled>Remove</button>
                          </div>
                        </div>

                        <div class="app-previews" id="previews_<?= (int)$i ?>">
                          <!-- preview thumbs will appear here -->
                        </div>

                        <div class="app-help mt-2">
                          Tip: Avoid glare. Make sure names, dates, and stamp/marks are visible.
                        </div>
                      </div>
                    </section>
                  </div>
                <?php endforeach; ?>

              </div>

              <div class="app-actions">
                <div class="app-note">
                  By continuing, you confirm the uploaded images are correct and readable.
                </div>
                <button class="btn btn-primary app-btn" type="submit" id="continueBtn">
                  Continue
                  <span class="app-btn__arrow" aria-hidden="true">→</span>
                </button>
              </div>
            </form>

          </div>
        </div>

        <footer class="app-footer">
          <div class="small">Need to change selection? Go back to Step 2.</div>
        </footer>

      </div>
    </div>
  </div>
</main>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  const maxBytes = 5 * 1024 * 1024;

  function formatBytes(n){
    if (!n || n < 0) return '0 B';
    const units = ['B','KB','MB','GB'];
    let i = 0, v = n;
    while (v >= 1024 && i < units.length-1) { v /= 1024; i++; }
    return (i === 0 ? v : v.toFixed(2)) + ' ' + units[i];
  }

  function makePreviewEl(file){
    const wrap = document.createElement('div');
    wrap.className = 'app-thumb';

    const img = document.createElement('img');
    img.alt = 'preview';
    img.loading = 'lazy';

    const meta = document.createElement('div');
    meta.className = 'app-thumb__meta';

    const name = document.createElement('div');
    name.className = 'app-thumb__name';
    name.textContent = file.name || 'image';

    const size = document.createElement('div');
    size.className = 'app-thumb__size';
    size.textContent = formatBytes(file.size);

    meta.appendChild(name);
    meta.appendChild(size);

    wrap.appendChild(img);
    wrap.appendChild(meta);

    const url = URL.createObjectURL(file);
    img.src = url;
    img.addEventListener('load', () => URL.revokeObjectURL(url), { once: true });

    return wrap;
  }

  function validateFile(file){
    if (!file) return null;
    if (file.size <= 0) return 'File is empty.';
    if (file.size > maxBytes) return 'File exceeds 5MB.';
    const ok = ['image/jpeg','image/png','image/webp'];
    if (!ok.includes(file.type)) return 'Only JPG/PNG/WebP allowed.';
    return null;
  }

  function renderPreviewsForGroup(groupEl, previewsEl){
    previewsEl.innerHTML = '';
    const inputs = groupEl.querySelectorAll('input[type="file"]');
    inputs.forEach(inp => {
      const f = inp.files && inp.files[0];
      if (!f) return;
      previewsEl.appendChild(makePreviewEl(f));
    });
  }

  function attachInputEvents(groupEl, previewsEl){
    groupEl.querySelectorAll('input[type="file"]').forEach(inp => {
      if (inp.dataset.bound === '1') return;
      inp.dataset.bound = '1';

      inp.addEventListener('change', () => {
        const f = inp.files && inp.files[0];
        const msg = f ? validateFile(f) : null;

        // show validation state (client-side helper only; server still validates)
        const line = inp.closest('.app-fileline');
        const errBox = line.querySelector('.app-fileerr');
        if (errBox) errBox.remove();

        if (msg) {
          inp.value = '';
          const e = document.createElement('div');
          e.className = 'app-fileerr';
          e.textContent = msg;
          line.appendChild(e);
        }

        renderPreviewsForGroup(groupEl, previewsEl);
      });
    });

    groupEl.querySelectorAll('.app-remove').forEach(btn => {
      if (btn.dataset.bound === '1') return;
      btn.dataset.bound = '1';

      btn.addEventListener('click', () => {
        const line = btn.closest('.app-fileline');
        if (!line) return;
        line.remove();

        // ensure at least 1 input remains
        const remaining = groupEl.querySelectorAll('input[type="file"]').length;
        if (remaining === 1) {
          const onlyRemove = groupEl.querySelector('.app-remove');
          if (onlyRemove) onlyRemove.disabled = true;
        }

        renderPreviewsForGroup(groupEl, previewsEl);
      });
    });
  }

  function addFileInput(index) {
    const group = document.getElementById('req_group_' + index);
    const previews = document.getElementById('previews_' + index);
    if (!group || !previews) return;

    const wrapper = document.createElement('div');
    wrapper.className = 'app-fileline';

    wrapper.innerHTML = `
      <input
        class="form-control app-input app-fileinput"
        type="file"
        name="req_${index}[]"
        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
        required
      >
      <button type="button" class="btn btn-outline-danger btn-sm app-remove">Remove</button>
    `;

    group.appendChild(wrapper);

    // enable remove buttons if >1
    const removes = group.querySelectorAll('.app-remove');
    if (removes.length > 1) removes.forEach(b => b.disabled = false);

    attachInputEvents(group, previews);
  }

  // Add Picture buttons
  document.querySelectorAll('button[data-req-index]').forEach(btn => {
    btn.addEventListener('click', function () {
      const idx = this.getAttribute('data-req-index');
      addFileInput(idx);
    });
  });

  // init preview listeners for existing inputs
  document.querySelectorAll('.app-filegroup').forEach(group => {
    const id = group.id.replace('req_group_','');
    const previews = document.getElementById('previews_' + id);
    attachInputEvents(group, previews);

    // ensure only first remove disabled
    const removes = group.querySelectorAll('.app-remove');
    if (removes.length === 1) removes[0].disabled = true;
  });
})();
</script>
</body>
</html>