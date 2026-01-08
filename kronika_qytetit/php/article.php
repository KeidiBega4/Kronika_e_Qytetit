<?php
require_once __DIR__ . '/config.php';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  header("Location: index.php");
  exit;
}

$stmt = $conn->prepare("
  SELECT a.id, a.title, a.content, a.image_path, a.created_at, c.name AS category_name, c.slug AS category_slug
  FROM articles a
  LEFT JOIN categories c ON c.id = a.category_id
  WHERE a.id=? AND a.status='published'
  LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$a = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$a) {
  http_response_code(404);
  exit("Article not found or not published.");
}

$placeholder = "../img/placeholder.jpg";
?>
<!doctype html>
<html lang="sq">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($a['title']) ?> - Kronika e Qytetit</title>
  <link rel="stylesheet" href="../css/lajme.css">
  <style>
    .article-wrap{max-width:900px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden}
    .article-body{padding:18px 18px 26px}
    .article-meta{opacity:.7;margin-top:6px}
  </style>
</head>
<body>

<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="index.php">KRONIKA E QYTETIT</a>
    <nav class="nav">
      <?php if (!empty($a['category_slug'])): ?>
        <a class="nav-link" href="category.php?c=<?= h($a['category_slug']) ?>"><?= h($a['category_name']) ?></a>
      <?php endif; ?>
      <a class="nav-link" href="kontakt.php">Kontakt</a>
      <a class="nav-link" href="feedback.php">Feedback</a>
      <a class="nav-link" href="rrethnesh.php">Rreth nesh</a>
    </nav>
  </div>
</header>

<main class="wrap">
  <div class="article-wrap">
    <img src="<?= h($a['image_path'] ?: $placeholder) ?>" alt="<?= h($a['title']) ?>" style="width:100%;display:block">
    <div class="article-body">
      <h1 style="margin:0"><?= h($a['title']) ?></h1>
      <div class="article-meta">
        <?= h($a['category_name'] ?? '') ?> • <?= h($a['created_at'] ?? '') ?>
      </div>
      <hr style="margin:14px 0;opacity:.2">
      <div style="line-height:1.65">
        <?= nl2br(h($a['content'] ?? '')) ?>
      </div>
    </div>
  </div>

  <div style="margin-top:16px">
    <a class="nav-link" style="background:#1f4f7a" href="index.php">← Kthehu te lajmet</a>
  </div>
</main>

</body>
</html>
