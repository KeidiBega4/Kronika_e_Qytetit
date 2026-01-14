<?php
require_once __DIR__ . '/../panel/_guard.php';
require_journalist();
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die("Method Not Allowed");
}

$uid = (int)($_SESSION['user_id'] ?? 0);
$article_id = (int)($_POST['article_id'] ?? 0);

if ($article_id <= 0) {
  header("Location: my_articles.php");
  exit;
}

$stmt = $conn->prepare("SELECT id, status, image_path FROM articles WHERE id=? AND author_id=? LIMIT 1");
$stmt->bind_param("ii", $article_id, $uid);
$stmt->execute();
$article = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$article) {
  header("Location: my_articles.php");
  exit;
}

if (($article['status'] ?? '') === 'published') {
  header("Location: my_articles.php");
  exit;
}

$stmt = $conn->prepare("DELETE FROM articles WHERE id=? AND author_id=?");
$stmt->bind_param("ii", $article_id, $uid);
$stmt->execute();
$stmt->close();

// Optional: delete file from disk (only if it's inside img/uploads)
$imagePath = (string)($article['image_path'] ?? '');
if ($imagePath !== '' && str_starts_with($imagePath, 'img/uploads/')) {
  $abs = __DIR__ . '/../../' . $imagePath;
  if (is_file($abs)) @unlink($abs);
}

header("Location: my_articles.php");
exit;
