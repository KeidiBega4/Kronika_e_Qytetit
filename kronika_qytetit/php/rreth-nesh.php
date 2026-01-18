<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$PHP_BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
function url_php($path) {
    global $PHP_BASE;
    return $PHP_BASE . '/' . ltrim($path, '/');
}

/* Load categories for navbar */
$categories = [];
$res = $conn->query("SELECT id, name, slug FROM categories ORDER BY id ASC");
if ($res) {
    $categories = $res->fetch_all(MYSQLI_ASSOC);
}
?>
<!doctype html>
<html lang="sq">
<head>
  <meta charset="utf-8">
  <title>Rreth Nesh – Kronika e Qytetit</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../css/lajme.css?v=1">
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<!-- TOP INFO BAR (same as index) -->
<div class="top-info-bar">
  <div class="top-info-inner">
    <div class="date-weather">
      <span class="current-date"><?= date("l, F j, Y"); ?></span>
      <span class="weather">18°C Tiranë <i class="fas fa-cloud-sun"></i></span>
    </div>

    <div class="social-links">
      <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
      <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
      <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
      <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
    </div>
  </div>
</div>

<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="<?= h(url_php('index.php')) ?>">KRONIKA E QYTETIT</a>

    <nav class="nav">
      <?php foreach ($categories as $c): ?>
        <a class="nav-link"
           href="<?= h(url_php('category.php?slug=' . urlencode($c['slug']))) ?>">
          <?= h(mb_strtoupper($c['name'], 'UTF-8')) ?>
        </a>
      <?php endforeach; ?>

      <!-- current page link -->
      <a class="nav-link" href="<?= h(url_php('rreth-nesh.php')) ?>">RRETH NESH</a>
    </nav>

    <div class="actions">
      <?php if (!empty($_SESSION['user_id'])): ?>
        <?php
          $role = $_SESSION['user_role'] ?? '';
          $dash = 'login.php';
          if ($role === 'admin') $dash = 'admin/dashboard.php';
          elseif ($role === 'journalist') $dash = 'journalist/dashboard.php';
        ?>
        <a href="<?= h(url_php($dash)) ?>" title="Dashboard">
          <img src="../img/user.png"
               alt="User"
               style="width:40px;height:40px;border-radius:50%;cursor:pointer;object-fit:cover;">
        </a>
      <?php else: ?>
        <a href="<?= h(url_php('login.php')) ?>" class="login-staff-btn">Login Staff</a>
      <?php endif; ?>
    </div>

  </div>
</header>

<main class="wrap">
  <section class="section">
    <div class="section-head">
      <h2>RRETH NESH</h2>
    </div>

    <div class="box" style="padding:18px;">
      <p>
        Kronika e Qytetit është një portal informativ që synon të sjellë lajme të sakta,
        të shpejta dhe të besueshme për publikun.
      </p>
      <p>
        Qëllimi ynë është informimi objektiv dhe transparenca.
      </p>

      <hr style="margin:16px 0;">

      <h3 style="margin:0 0 8px;">Kontakt</h3>
      <p style="margin:0 0 6px;">Email: info@kronikaeqytetit.al</p>
      <p style="margin:0;">Tel: +355 69 000 0000</p>
    </div>
  </section>
</main>

</body>
</html>
