<?php
session_start();
require_once __DIR__ . "/includes/csrf.php";
require_once __DIR__ . "/includes/helpers.php";

$errors = $_SESSION['flash_errors'] ?? [];
unset($_SESSION['flash_errors']);

$old = $_SESSION['client_info'] ?? [
  'first_name'   => '',
  'middle_name'  => '',
  'last_name'    => '',
  'address'      => '',
  'address_line' => '',
  'cp_no'        => '',
];
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Appointment System - Client Info</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="app-body">
<main class="app-shell">
<div class="container py-4 py-lg-5">
<div class="row justify-content-center">
<div class="col-xl-10">

<!-- HERO -->
<div class="app-hero">

  <div class="app-hero__content">

    <!-- BRAND BADGE WITH LOGO -->
    <div class="app-badge app-badge--brand">
      <img src="assets/logo.jpeg" alt="ASAP Logo" class="app-badge__logo">
      <span class="app-badge__text">
        A.S.A.P • Assessor's Services Appointment Portal
      </span>
    </div>

    <h1 class="app-title">Book your appointment faster.</h1>

    <p class="app-subtitle">
      Please provide your client information to continue.
      Your details help us validate your request and process it efficiently.
    </p>

  </div>

  <div class="app-hero__art" aria-hidden="true">
    <div class="art-card art-card--top"></div>
    <div class="art-card art-card--mid"></div>
    <div class="art-card art-card--bot"></div>
  </div>

</div>


<!-- FORM CARD -->
<div class="app-card app-reveal">

<div class="app-card__header">
  <div>
    <h5 class="mb-1">Client Information</h5>
    <div class="app-card__hint">Fields with * are required.</div>
  </div>
</div>

<div class="app-card__body">

<?php if ($errors): ?>
<div class="alert alert-danger app-alert">
  <div class="app-alert__title">Please check the following:</div>
  <ul class="mb-0">
    <?php foreach($errors as $e): ?>
      <li><?= h($e) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<form method="post" action="select.php" novalidate class="app-form">
<input type="hidden" name="_csrf" value="<?= h(Csrf::token()) ?>">

<div class="row g-3">

<!-- NAME -->
<div class="col-md-4">
  <label class="form-label">First Name <span class="req">*</span></label>
  <input name="first_name"
         class="form-control app-input"
         required
         value="<?= h($old['first_name']) ?>"
         autocomplete="given-name">
</div>

<div class="col-md-4">
  <label class="form-label">Middle Name</label>
  <input name="middle_name"
         class="form-control app-input"
         value="<?= h($old['middle_name']) ?>"
         autocomplete="additional-name">
</div>

<div class="col-md-4">
  <label class="form-label">Last Name <span class="req">*</span></label>
  <input name="last_name"
         class="form-control app-input"
         required
         value="<?= h($old['last_name']) ?>"
         autocomplete="family-name">
</div>

<!-- ADDRESS -->
<div class="col-12">
<label class="form-label">Address <span class="req">*</span></label>

<div class="row g-2">

<div class="col-md-6">
<select class="form-select app-select" id="region" name="region_code" required>
  <option value="">-- Select Region --</option>
</select>
</div>

<div class="col-md-6">
<select class="form-select app-select" id="province" name="province_code" required disabled>
  <option value="">-- Select Province --</option>
</select>
</div>

<div class="col-md-6">
<select class="form-select app-select" id="citymun" name="citymun_code" required disabled>
  <option value="">-- Select City / Municipality --</option>
</select>
</div>

<div class="col-md-6">
<select class="form-select app-select" id="barangay" name="barangay_code" required disabled>
  <option value="">-- Select Barangay --</option>
</select>
</div>

<div class="col-12">
<input
  class="form-control app-input"
  id="addressLine"
  name="address_line"
  placeholder="House No. / Street / Subdivision (optional)"
  value="<?= h($old['address_line'] ?? '') ?>"
  autocomplete="street-address"
>
<div class="app-help mt-1">
Saved address format: (Optional line), Barangay, City/Municipality, Province, Region.
</div>
</div>

</div>

<input type="hidden" name="address" id="address_full"
       value="<?= h($old['address']) ?>">
</div>

<!-- CP NUMBER -->
<div class="col-md-6">
<label class="form-label">CP Number <span class="req">*</span></label>
<input name="cp_no"
       class="form-control app-input"
       required
       value="<?= h($old['cp_no']) ?>"
       placeholder="e.g. 09xxxxxxxxx"
       autocomplete="tel">
<div class="app-help mt-1">
Use an active number for updates and confirmations.
</div>
</div>

</div>

<div class="app-actions">
  <div class="app-note">
    Only image uploads (JPG/PNG/WebP) are accepted for requirements.
  </div>

  <button class="btn btn-primary app-btn" type="submit">
    Continue
    <span class="app-btn__arrow" aria-hidden="true">→</span>
  </button>
</div>

</form>
</div>
</div>

<footer class="app-footer">
<div class="small">
© <?= date('Y') ?> A.S.A.P • Assessor's Services Appointment Portal
</div>
</footer>

</div>
</div>
</div>
</main>

<script src="assets/js/bootstrap.bundle.min.js"></script>

<!-- ===============================
     PSGC Loader (UNCHANGED)
================================== -->
<script>
const api = (level, code='') => {
  const url = new URL('api/psgc.php', window.location.href);
  url.searchParams.set('level', level);
  if (code) url.searchParams.set('code', code);
  return fetch(url.toString()).then(r => r.json());
};

const elRegion  = document.getElementById('region');
const elProv    = document.getElementById('province');
const elCityMun = document.getElementById('citymun');
const elBrgy    = document.getElementById('barangay');
const elLine    = document.getElementById('addressLine');
const elFull    = document.getElementById('address_full');

function normalizeItems(items){
  if(Array.isArray(items)) return items;
  if(items && Array.isArray(items.data)) return items.data;
  return [];
}

function setOptions(select, items, placeholder){
  select.innerHTML = `<option value="">${placeholder}</option>`;
  const arr = normalizeItems(items);

  arr.forEach(it=>{
    if(!it || !it.code || !it.name) return;
    const opt=document.createElement('option');
    opt.value=it.code;
    opt.textContent=it.name;
    select.appendChild(opt);
  });
}

function selectedText(select){
  return select.value
    ? select.options[select.selectedIndex]?.textContent || ''
    : '';
}

function rebuildFullAddress(){
  const parts=[];
  const line=(elLine?.value||'').trim();

  if(line) parts.push(line);
  if(selectedText(elBrgy)) parts.push(selectedText(elBrgy));
  if(selectedText(elCityMun)) parts.push(selectedText(elCityMun));
  if(selectedText(elProv)) parts.push(selectedText(elProv));
  if(selectedText(elRegion)) parts.push(selectedText(elRegion));

  elFull.value=parts.join(', ');
}

function resetSelect(select, placeholder){
  setOptions(select,[],placeholder);
  select.disabled=true;
}

async function loadRegions(){
  const res=await api('regions');
  if(res?.ok){
    setOptions(elRegion,res.items,'-- Select Region --');
  }
}

elRegion.addEventListener('change',async()=>{
  rebuildFullAddress();
  resetSelect(elProv,'-- Select Province --');
  resetSelect(elCityMun,'-- Select City / Municipality --');
  resetSelect(elBrgy,'-- Select Barangay --');

  if(!elRegion.value) return;

  const res=await api('provinces',elRegion.value);
  if(res?.ok){
    setOptions(elProv,res.items,'-- Select Province --');
    elProv.disabled=false;
  }
});

elProv.addEventListener('change',async()=>{
  rebuildFullAddress();
  resetSelect(elCityMun,'-- Select City / Municipality --');
  resetSelect(elBrgy,'-- Select Barangay --');

  if(!elProv.value) return;

  const res=await api('citiesmun',elProv.value);
  if(res?.ok){
    setOptions(elCityMun,res.items,'-- Select City / Municipality --');
    elCityMun.disabled=false;
  }
});

elCityMun.addEventListener('change',async()=>{
  rebuildFullAddress();
  resetSelect(elBrgy,'-- Select Barangay --');

  if(!elCityMun.value) return;

  const res=await api('barangays',elCityMun.value);
  if(res?.ok){
    setOptions(elBrgy,res.items,'-- Select Barangay --');
    elBrgy.disabled=false;
  }
});

elBrgy.addEventListener('change', rebuildFullAddress);
elLine?.addEventListener('input', rebuildFullAddress);

loadRegions();
rebuildFullAddress();
</script>

</body>
</html>