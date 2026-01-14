<?php

require_once __DIR__ . '/../auth.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= h($page_title ?? 'Panel') ?></title>
  <link rel="stylesheet" href="../../css/admin_panel.css">
  <link rel="stylesheet" href="../../css/journalist.css">
</head>
<body>
  <div class="wrap">
    <aside class="side">
  <div class="brand">Kronika Panel</div>

  <?php
  if (isset($sidebar_file) && file_exists($sidebar_file)) {
      require $sidebar_file;
  } else {
      echo '<p style="padding:10px;color:#999">Sidebar missing</p>';
  }
  ?>
</aside>

    <div class="main">
      <div class="top">
        <div class="top-title"><?= h($page_title ?? '') ?></div>
        <div class="top-user">
          <?= h($_SESSION['user_name'] ?? '') ?> (<?= h($_SESSION['user_role'] ?? '') ?>)
        </div>
      </div>

      <div class="content">
        <?= $content_html ?? '' ?>
      </div>
    </div>
  </div>
</body>
</html>
