<?php
session_start();
require_once __DIR__ . "/includes/csrf.php";
require_once __DIR__ . "/includes/helpers.php";

$client = $_SESSION['client_info'] ?? null;
$sel    = $_SESSION['selected'] ?? null;

if (!$client) { header("Location: index.php"); exit(); }
if (!$sel)    { header("Location: select.php"); exit(); }

$uploads = $_SESSION['uploads'] ?? [];
$total = (float)($sel['price'] ?? 0);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Summary</title>
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
              Step 4 of 4
            </div>
            <h1 class="app-title app-title--sm mb-1">Summary & Confirmation</h1>
            <div class="app-subtitle">Review details before submitting your appointment request.</div>
          </div>

          <div class="app-topbar__actions">
            <a class="btn btn-outline-secondary app-btn-outline" href="select.php">Change Selection</a>
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
          <div class="app-step is-done">
            <div class="app-step__dot">3</div>
            <div class="app-step__label">Upload</div>
          </div>
          <div class="app-step is-active">
            <div class="app-step__dot">4</div>
            <div class="app-step__label">Summary</div>
          </div>
        </div>

        <?php
          // Check: at least one uploaded file per requirement label
          $have = [];
          foreach ($uploads as $u) {
            $lbl = (string)($u['requirement_label'] ?? '');
            if ($lbl !== '') $have[$lbl] = true;
          }

          $missing = [];
          foreach (($sel['requirements'] ?? []) as $lbl) {
            if (empty($have[$lbl])) $missing[] = $lbl;
          }

          $hasMissing = !empty($missing);
        ?>

        <!-- Main Grid -->
        <div class="row g-3">
          <!-- Client -->
          <div class="col-lg-6">
            <div class="app-card app-reveal h-100">
              <div class="app-card__header">
                <div>
                  <h5 class="mb-1">Client Information</h5>
                  <div class="app-card__hint">Please confirm your details.</div>
                </div>
                <a class="btn btn-outline-secondary app-btn-outline app-btn-outline--sm" href="index.php">Edit</a>
              </div>

              <div class="app-card__body">
                <div class="app-kv">
                  <div class="app-kv__row">
                    <div class="app-kv__k">Full Name</div>
                    <div class="app-kv__v"><?= h(trim($client['first_name'].' '.$client['middle_name'].' '.$client['last_name'])) ?></div>
                  </div>
                  <div class="app-kv__row">
                    <div class="app-kv__k">Address</div>
                    <div class="app-kv__v"><?= h($client['address']) ?></div>
                  </div>
                  <div class="app-kv__row">
                    <div class="app-kv__k">CP Number</div>
                    <div class="app-kv__v"><?= h($client['cp_no']) ?></div>
                  </div>
                </div>

                <div class="app-help mt-3">
                  Tip: Use an active contact number for updates and SMS notifications (if enabled).
                </div>
              </div>
            </div>
          </div>

          <!-- Selected -->
          <div class="col-lg-6">
            <div class="app-card app-reveal h-100">
              <div class="app-card__header">
                <div>
                  <h5 class="mb-1">Selected Offering</h5>
                  <div class="app-card__hint">Details of your chosen certificate/service.</div>
                </div>
                <div class="app-totalpill">
                  <span class="app-totalpill__label">Total</span>
                  <span class="app-totalpill__value">₱<?= number_format($total, 2) ?></span>
                </div>
              </div>

              <div class="app-card__body">
                <div class="app-kv">
                  <div class="app-kv__row">
                    <div class="app-kv__k">Type</div>
                    <div class="app-kv__v"><?= h(ucfirst($sel['type'])) ?></div>
                  </div>
                  <div class="app-kv__row">
                    <div class="app-kv__k">Item</div>
                    <div class="app-kv__v"><?= h($sel['label']) ?></div>
                  </div>
                  <div class="app-kv__row">
                    <div class="app-kv__k">Price</div>
                    <div class="app-kv__v">₱<?= number_format($total, 2) ?></div>
                  </div>
                </div>

                <div class="app-divider"></div>

                <div class="app-minihead">
                  <div class="app-minihead__title">Attachments Status</div>
                  <?php if (empty($sel['requirements'])): ?>
                    <span class="app-status ok">No requirements</span>
                  <?php elseif ($hasMissing): ?>
                    <span class="app-status warn">Incomplete</span>
                  <?php else: ?>
                    <span class="app-status ok">Complete</span>
                  <?php endif; ?>
                </div>

                <?php if ($hasMissing): ?>
                  <div class="app-empty warn mt-2">
                    Missing attachments for:
                    <ul class="mb-0 mt-2">
                      <?php foreach ($missing as $m): ?>
                        <li><?= h($m) ?></li>
                      <?php endforeach; ?>
                    </ul>
                    <div class="app-help mt-2">Please go back and upload the required files.</div>
                  </div>
                <?php else: ?>
                  <ul class="app-summarylist mb-0 mt-2">
                    <?php foreach($uploads as $u): ?>
                      <li>
                        <span class="label"><?= h($u['requirement_label']) ?></span>
                        <span class="file"><?= h($u['original_name']) ?></span>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>

              </div>
            </div>
          </div>

          <!-- Attachments Preview -->
          <div class="col-12">
            <div class="app-card app-reveal">
              <div class="app-card__header">
                <div>
                  <h5 class="mb-1">Uploaded Images</h5>
                  <div class="app-card__hint">Preview your uploaded screenshots/photos before submitting.</div>
                </div>
                <?php if (!empty($sel['requirements'])): ?>
                  <a class="btn btn-outline-secondary app-btn-outline app-btn-outline--sm" href="upload.php">Manage Uploads</a>
                <?php endif; ?>
              </div>

              <div class="app-card__body">
                <?php if (empty($uploads)): ?>
                  <div class="app-empty warn">No uploads found. Please upload the required attachments.</div>
                <?php else: ?>
                  <div class="app-previews app-previews--summary">
                    <?php foreach($uploads as $u): ?>
                      <?php
                        $src = (string)($u['file_path'] ?? '');
                        $name = (string)($u['original_name'] ?? 'image');
                        $lbl = (string)($u['requirement_label'] ?? '');
                      ?>
                      <div class="app-thumb">
                        <img src="<?= h($src) ?>" alt="<?= h($name) ?>" loading="lazy">
                        <div class="app-thumb__meta">
                          <div class="app-thumb__name"><?= h($lbl) ?></div>
                          <div class="app-thumb__size"><?= h($name) ?></div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

                <div class="app-help mt-3">
                  Privacy note: Uploaded images are used only for validating your requirements and processing your request.
                </div>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="col-12">
            <div class="app-card app-reveal">
              <div class="app-card__body app-actions app-actions--tight">
                <a class="btn btn-outline-secondary app-btn-outline" href="<?= empty($sel['requirements']) ? 'select.php' : 'upload.php' ?>">Back</a>

                <form method="post" action="submit.php" class="mb-0">
                  <input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">
                  <button class="btn btn-primary app-btn" type="submit" <?= $hasMissing ? 'disabled' : '' ?>>
                    Confirm & Submit
                    <span class="app-btn__arrow" aria-hidden="true">→</span>
                  </button>
                </form>
              </div>
            </div>

            <div class="app-footer">
              <div class="small">
                By submitting, you confirm the information and attachments are accurate and readable.
              </div>
            </div>
          </div>

        </div>

      </div>
    </div>
  </div>
</main>

<script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>