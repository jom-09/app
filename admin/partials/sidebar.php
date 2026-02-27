<?php
// admin/partials/sidebar.php
$active = $active ?? '';
?>
<aside class="adm-sidebar" id="adminSidebar">

  <!-- Brand -->
  <div class="adm-sidebrand">
    <div class="adm-sidebrand__logo">A</div>
    <div class="adm-sidebrand__text">
      <div class="adm-sidebrand__title">Appointment</div>
      <div class="adm-sidebrand__sub">Admin Panel</div>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="adm-nav">

    <a class="adm-nav__item <?= $active==='dashboard'?'is-active':'' ?>" href="index.php">
      <span class="adm-nav__icon">🏠</span>
      <span class="adm-nav__label">Dashboard</span>
    </a>

    <a class="adm-nav__item <?= $active==='requests'?'is-active':'' ?>" href="requests.php">
      <span class="adm-nav__icon">📄</span>
      <span class="adm-nav__label">Requests</span>
    </a>

    <a class="adm-nav__item <?= $active==='offerings'?'is-active':'' ?>" href="offerings.php">
      <span class="adm-nav__icon">🧾</span>
      <span class="adm-nav__label">Certificates & Services</span>
    </a>

  </nav>

  <!-- Footer -->
  <div class="adm-sidefooter">
    <a class="adm-logout" href="logout.php">
      ⎋ Logout
    </a>
  </div>

</aside>

<!-- Main Content Wrapper -->
<main class="adm-main">