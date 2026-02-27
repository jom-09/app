<?php
session_start();
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/includes/admin_auth.php";
require_admin();

$title = "Dashboard";
$active = "dashboard";

/* ===============================
   REQUEST COUNTS
================================= */
$total = (int)$conn->query("SELECT COUNT(*) c FROM requests")->fetch_assoc()['c'];
$pending = (int)$conn->query("SELECT COUNT(*) c FROM requests WHERE status IN ('PENDING','SUBMITTED')")->fetch_assoc()['c'];
$accepted = (int)$conn->query("SELECT COUNT(*) c FROM requests WHERE status='ACCEPTED'")->fetch_assoc()['c'];
$declined = (int)$conn->query("SELECT COUNT(*) c FROM requests WHERE status='DECLINED'")->fetch_assoc()['c'];

/* ===============================
   OFFERINGS COUNTS
================================= */
$totalCert = (int)$conn->query("
    SELECT COUNT(*) c
    FROM offerings
    WHERE type='certificate' AND is_active=1
")->fetch_assoc()['c'];

$totalSvc = (int)$conn->query("
    SELECT COUNT(*) c
    FROM offerings
    WHERE type='service' AND is_active=1
")->fetch_assoc()['c'];

/* ===============================
   REQUESTS BY MONTH (FORMATTED)
================================= */
$monthly = [];

$stmt = $conn->prepare("
  SELECT DATE_FORMAT(created_at,'%b %Y') AS ym,
         COUNT(*) cnt
  FROM requests
  WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
  GROUP BY YEAR(created_at), MONTH(created_at)
  ORDER BY YEAR(created_at), MONTH(created_at)
");
$stmt->execute();
$res = $stmt->get_result();

while($r = $res->fetch_assoc()){
    $monthly[] = $r;
}
$stmt->close();

$monthlyLabels = array_map(fn($x) => $x['ym'], $monthly);
$monthlyCounts = array_map(fn($x) => (int)$x['cnt'], $monthly);

/* ===============================
   TOP ADDRESSES
================================= */
$byAddress = [];

$stmt = $conn->prepare("
  SELECT c.address, COUNT(*) cnt
  FROM requests r
  JOIN clients c ON c.id = r.client_id
  GROUP BY c.address
  ORDER BY cnt DESC
  LIMIT 10
");
$stmt->execute();
$res = $stmt->get_result();

while($r = $res->fetch_assoc()){
    $byAddress[] = $r;
}
$stmt->close();

$addrLabels = array_map(fn($x) => $x['address'], $byAddress);
$addrCounts = array_map(fn($x) => (int)$x['cnt'], $byAddress);

require __DIR__ . "/partials/header.php";
?>

<div class="adm-wrap">

<!-- ================= TOPBAR ================= -->
<div class="adm-topbar">
  <div class="adm-topbar__left">
    <div class="adm-badge">
      <span class="adm-badge__dot"></span>
      Admin Panel
    </div>
    <h1 class="adm-title">Dashboard</h1>
    <div class="adm-subtitle">Overview of requests and offerings.</div>
  </div>

  <div class="adm-topbar__right">
    <div class="adm-user">
      <div class="adm-user__label">Logged in as</div>
      <div class="adm-user__name"><?= htmlspecialchars($_SESSION['admin_user'] ?? 'admin') ?></div>
    </div>
  </div>
</div>

<!-- ================= REQUEST STATS ================= -->
<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="adm-card adm-stat">
      <div class="adm-stat__label">Total Requests</div>
      <div class="adm-stat__value"><?= $total ?></div>
      <div class="adm-stat__hint">All submissions</div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="adm-card adm-stat adm-stat--pending">
      <div class="adm-stat__label">Pending</div>
      <div class="adm-stat__value"><?= $pending ?></div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="adm-card adm-stat adm-stat--accepted">
      <div class="adm-stat__label">Accepted</div>
      <div class="adm-stat__value"><?= $accepted ?></div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="adm-card adm-stat adm-stat--declined">
      <div class="adm-stat__label">Declined</div>
      <div class="adm-stat__value"><?= $declined ?></div>
    </div>
  </div>
</div>

<!-- ================= OFFERINGS STATS ================= -->
<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="adm-card adm-stat">
      <div class="adm-stat__label">Total Certificates</div>
      <div class="adm-stat__value"><?= $totalCert ?></div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="adm-card adm-stat">
      <div class="adm-stat__label">Total Services</div>
      <div class="adm-stat__value"><?= $totalSvc ?></div>
    </div>
  </div>
</div>

<!-- ================= CHARTS ================= -->
<div class="row g-3 mb-3">

  <!-- LINE GRAPH -->
  <div class="col-lg-6">
    <div class="adm-card">
      <div class="adm-card__head">
        <div>
          <div class="adm-card__title">Requests by Month</div>
          <div class="adm-card__sub">Last 12 months</div>
        </div>
      </div>
      <div class="adm-card__body">
        <div style="height:300px;">
          <canvas id="monthlyLineChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- HORIZONTAL BAR -->
  <div class="col-lg-6">
    <div class="adm-card">
      <div class="adm-card__head">
        <div>
          <div class="adm-card__title">Top Addresses</div>
        </div>
      </div>
      <div class="adm-card__body">
        <div style="height:300px;">
          <canvas id="topAddressBarChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- DOUGHNUT -->
  <div class="col-12">
    <div class="adm-card">
      <div class="adm-card__head">
        <div class="adm-card__title">Certificates vs Services</div>
      </div>
      <div class="adm-card__body">
        <div style="height:260px; max-width:520px; margin:auto;">
          <canvas id="certSvcChart"></canvas>
        </div>
      </div>
    </div>
  </div>

</div>

</div>

<!-- ================= CHART.JS LOCAL ================= -->
<script src="../includes/chart.js/chart.umd.min.js"></script>

<script>
(function(){
if(typeof Chart==='undefined') return;

/* ===== Monthly Line ===== */
new Chart(document.getElementById('monthlyLineChart'),{
type:'line',
data:{
labels: <?= json_encode($monthlyLabels) ?>,
datasets:[{
label:'Requests',
data: <?= json_encode($monthlyCounts) ?>,
tension:.35,
borderWidth:2,
pointRadius:3
}]
},
options:{
responsive:true,
maintainAspectRatio:false,
scales:{ y:{beginAtZero:true,ticks:{precision:0}}}
}
});

/* ===== Top Address Bar ===== */
new Chart(document.getElementById('topAddressBarChart'),{
type:'bar',
data:{
labels: <?= json_encode($addrLabels) ?>,
datasets:[{
data: <?= json_encode($addrCounts) ?>,
borderWidth:1
}]
},
options:{
indexAxis:'y',
responsive:true,
maintainAspectRatio:false,
plugins:{legend:{display:false}},
scales:{x:{beginAtZero:true,ticks:{precision:0}}}
}
});

/* ===== Doughnut ===== */
new Chart(document.getElementById('certSvcChart'),{
type:'doughnut',
data:{
labels:['Certificates','Services'],
datasets:[{
data:[<?= $totalCert ?>,<?= $totalSvc ?>],
borderWidth:1
}]
},
options:{
responsive:true,
maintainAspectRatio:false,
plugins:{legend:{position:'bottom'}},
cutout:'65%'
}
});

})();
</script>

<?php require __DIR__ . "/partials/footer.php"; ?>