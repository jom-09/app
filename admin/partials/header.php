<?php
require_once __DIR__ . "/../../includes/helpers.php";

$title  = $title  ?? "Admin";
$active = $active ?? "dashboard";
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?></title>

  <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="adm-body">

<nav class="adm-navtop">
  <div class="adm-navtop__inner">

    <a class="adm-brand" href="index.php">
      <span class="adm-brand__mark">A</span>
      <span class="adm-brand__txt">
        <span class="adm-brand__title">Appointment Admin</span>
        <span class="adm-brand__sub">LGU Panel</span>
      </span>
    </a>

    <button class="adm-navtoggle" type="button"
            data-bs-toggle="collapse" data-bs-target="#admNav"
            aria-controls="admNav" aria-expanded="false" aria-label="Toggle navigation">
      <span></span><span></span><span></span>
    </button>

    <div class="collapse adm-navlinks" id="admNav">
      <a class="adm-link <?= $active==='dashboard'?'is-active':'' ?>" href="index.php">Dashboard</a>
      <a class="adm-link <?= $active==='requests'?'is-active':'' ?>" href="requests.php">Requests</a>
      <a class="adm-link <?= $active==='offerings'?'is-active':'' ?>" href="offerings.php">Certificates & Services</a>

      <div class="adm-navsep"></div>

      <div class="adm-userpill">
        <span class="adm-userpill__label">Logged in</span>
        <span class="adm-userpill__name"><?= htmlspecialchars($_SESSION['admin_user'] ?? 'admin') ?></span>
      </div>

      <a class="adm-link adm-link--logout" href="logout.php">Logout</a>
    </div>

  </div>
</nav>

<main class="adm-container">