<?php
require_once __DIR__ . '/../panel/_guard.php';
require_journalist();
require_once __DIR__ . '/../config.php';

$page_title = "Journalist Dashboard";
$sidebar_file = __DIR__ . '/_sidebar_journalist.php';

$uid = (int)($_SESSION['user_id'] ?? 0);

$total = $pending = $published = $rejected = 0;

$stmt = $conn->prepare("SELECT COUNT(*) c FROM articles WHERE author_id=?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$total = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) c FROM articles WHERE author_id=? AND status='pending'");
$stmt->bind_param("i", $uid);
$stmt->execute();
$pending = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) c FROM articles WHERE author_id=? AND status='published'");
$stmt->bind_param("i", $uid);
$stmt->execute();
$published = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) c FROM articles WHERE author_id=? AND status='rejected'");
$stmt->bind_param("i", $uid);
$stmt->execute();
$rejected = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

ob_start();
?>
<div class="jp-cards">
  <div class="jp-card"><div class="jp-k">My Articles</div><div class="jp-v"><?= $total ?></div></div>
  <div class="jp-card"><div class="jp-k">Pending</div><div class="jp-v"><?= $pending ?></div></div>
  <div class="jp-card"><div class="jp-k">Published</div><div class="jp-v"><?= $published ?></div></div>
  <div class="jp-card"><div class="jp-k">Rejected</div><div class="jp-v"><?= $rejected ?></div></div>
</div>

<div class="jp-actions">
  <a class="jp-btn jp-btn-primary" href="create_article.php">+ Create Article</a>
  <a class="jp-btn jp-btn-outline" href="my_articles.php">My Articles</a>
  <a class="jp-btn jp-btn-outline" href="change_password.php">Change Password</a>
</div>
<?php
$content_html = ob_get_clean();
include __DIR__ . '/../panel/_layout.php';
