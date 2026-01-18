<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helper.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
$isAdmin = !empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  header("Location: index.php");
  exit;
}

/* ✅ Only change: admin can view any status, public only published */
if ($isAdmin) {
  $stmt = $conn->prepare("
    SELECT a.id, a.title, a.content, a.image_path, a.created_at,
           c.name AS category_name, c.slug AS category_slug
    FROM articles a
    LEFT JOIN categories c ON c.id = a.category_id
    WHERE a.id=?
    LIMIT 1
  ");
} else {
  $stmt = $conn->prepare("
    SELECT a.id, a.title, a.content, a.image_path, a.created_at,
           c.name AS category_name, c.slug AS category_slug
    FROM articles a
    LEFT JOIN categories c ON c.id = a.category_id
    WHERE a.id=? AND a.status='published'
    LIMIT 1
  ");
}

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
    .article-wrap {
      max-width: 900px;
      margin: 20px auto;
      background: #fff;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
    }

    .article-hero-img {
      width: 100%;
      height: 400px;
      object-fit: cover;
      display: block;
    }

    @media (max-width: 768px) {
      .article-hero-img {
        height: 280px;
      }
    }

    .article-body {
      padding: 24px 28px 32px;
    }

    .article-body h1 {
      margin: 0;
      font-size: 32px;
      font-weight: 900;
      line-height: 1.2;
      color: #111;
    }

    .article-meta {
      opacity: 0.7;
      margin-top: 10px;
      font-size: 14px;
      font-weight: 600;
    }

    .article-content {
      line-height: 1.7;
      font-size: 16px;
      color: #333;
    }

    .article-content p {
      margin: 16px 0;
    }

    .back-btn {
      display: inline-block;
      margin-top: 20px;
      padding: 12px 20px;
      background: #1f4f7a;
      color: #fff;
      text-decoration: none;
      border-radius: 8px;
      font-weight: 700;
      transition: all 0.2s ease;
      box-shadow: 0 4px 12px rgba(31, 79, 122, 0.2);
    }

    .back-btn:hover {
      background: #164060;
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(31, 79, 122, 0.3);
    }
  </style>
</head>
<body>

<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="index.php">KRONIKA E QYTETIT</a>
    <nav class="nav">
      <?php if (!empty($a['category_slug'])): ?>
        <a class="nav-link" href="index.php?c=<?= h($a['category_slug']) ?>"><?= h(mb_strtoupper($a['category_name'], 'UTF-8')) ?></a>
      <?php endif; ?>
      <a class="nav-link" href="index.php?c=rreth-nesh">RRETH NESH</a>
      <a class="nav-link" href="index.php?c=feedback">FEEDBACK</a>
      <a class="nav-link" href="index.php?c=kontakt">KONTAKT</a>
    </nav>
  </div>
</header>

<main class="wrap">
  <div class="article-wrap">
    <img
      class="article-hero-img"
      src="<?= h($a['image_path'] ?: $placeholder) ?>"
      alt="<?= h($a['title']) ?>"
    >
    <div class="article-body">
      <h1><?= h($a['title']) ?></h1>
      <div class="article-meta">
        <?= h($a['category_name'] ?? '') ?> • <?= h(date('d.m.Y H:i', strtotime($a['created_at'] ?? 'now'))) ?>
      </div>
      <hr style="margin: 18px 0; opacity: 0.15; border: none; border-top: 2px solid #ddd">
      <div class="article-content">
        <?= nl2br(h($a['content'] ?? '')) ?>
      </div>
    </div>
  </div>

 <?php if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>

  <a class="back-btn" href="admin/articles.php">← KTHEU</a>

<?php elseif (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'journalist'): ?>

  <a class="back-btn" href="journalist/my_articles.php">← KTHEU</a>

<?php else: ?>

  <a class="back-btn" href="index.php">← KTHEU</a>

<?php endif; ?>

</main>

</body>
</html>
