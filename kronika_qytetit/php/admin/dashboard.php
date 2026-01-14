<?php
require_once __DIR__ . '/../panel/_guard.php';
require_admin();
require_once __DIR__ . '/../config.php';

$total_articles = (int)$mysqli->query("SELECT COUNT(*) c FROM articles")->fetch_assoc()['c'];
$pending = (int)$mysqli->query("SELECT COUNT(*) c FROM articles WHERE status='pending'")->fetch_assoc()['c'];
$published = (int)$mysqli->query("SELECT COUNT(*) c FROM articles WHERE status='published'")->fetch_assoc()['c'];
$rejected = (int)$mysqli->query("SELECT COUNT(*) c FROM articles WHERE status='rejected'")->fetch_assoc()['c'];

$page_title = "Admin Dashboard";
$sidebar_file = __DIR__ . '/../panel/_sidebar_admin.php';

ob_start();
?>
<div class="cards">
  <div class="card"><div class="k">Total Articles</div><div class="v"><?= $total_articles ?></div></div>
  <div class="card"><div class="k">Pending</div><div class="v"><?= $pending ?></div></div>
  <div class="card"><div class="k">Published</div><div class="v"><?= $published ?></div></div>
  <div class="card"><div class="k">Rejected</div><div class="v"><?= $rejected ?></div></div>
</div>
<?php
$content_html = ob_get_clean();
include __DIR__ . '/../panel/_layout.php';
