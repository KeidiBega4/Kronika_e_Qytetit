<?php
// php/search.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   BASIC GUARDS
========================= */

if (!isset($conn) || !$conn instanceof mysqli) {
    die('DB connection missing');
}

$q = trim((string)($_GET['q'] ?? ''));
if ($q === '') {
    header('Location: index.php');
    exit;
}

/* =========================
   CATEGORIES (needed for nav)
========================= */

$categories = [];
$res = $conn->query("SELECT id, name, slug FROM categories ORDER BY id ASC");
if ($res) {
    $categories = $res->fetch_all(MYSQLI_ASSOC);
}

/* =========================
   PAGINATION
========================= */

$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

/* =========================
   SORTING (NO MOST VIEWED)
========================= */

$sortMap = [
    'newest'   => 'a.created_at DESC',
    'oldest'   => 'a.created_at ASC',
    'title_az' => 'a.title ASC',
    'title_za' => 'a.title DESC',
];

$sort = $_GET['sort'] ?? 'newest';
$orderBy = $sortMap[$sort] ?? $sortMap['newest'];

$like = '%' . $q . '%';

/* =========================
   COUNT RESULTS
========================= */

$countSql = "
    SELECT COUNT(*) AS total
    FROM articles a
    WHERE a.status='published'
      AND (a.title LIKE ? OR a.content LIKE ?)
";
$stmt = $conn->prepare($countSql);
$stmt->bind_param("ss", $like, $like);
$stmt->execute();
$total = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);

/* =========================
   FETCH RESULTS
========================= */

$sql = "
    SELECT a.id, a.title, a.slug, a.image_path, a.content, a.created_at,
           c.name AS category_name, c.slug AS category_slug
    FROM articles a
    JOIN categories c ON a.category_id = c.id
    WHERE a.status='published'
      AND (a.title LIKE ? OR a.content LIKE ?)
    ORDER BY $orderBy
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssii", $like, $like, $perPage, $offset);
$stmt->execute();
$results = $stmt->get_result();
$stmt->close();

/* =========================
   HELPERS
========================= */

function pageUrl($p) {
    $q = $_GET['q'] ?? '';
    $s = $_GET['sort'] ?? 'newest';
    return "search.php?q=" . urlencode($q) . "&sort=" . urlencode($s) . "&page=" . $p;
}

function article_url($a) {
    return "article.php?id=" . urlencode($a['id']);
}

function rel_time($dt) {
    if (!$dt) return '';
    $ts = strtotime($dt);
    if (!$ts) return '';
    $diff = time() - $ts;
    if ($diff < 60) return 'tani';
    if ($diff < 3600) return floor($diff / 60) . ' min';
    if ($diff < 86400) return floor($diff / 3600) . ' orë';
    return floor($diff / 86400) . ' ditë';
}

$placeholder = "../img/placeholder.jpg";
?>
<!doctype html>
<html lang="sq">
<head>
    <meta charset="utf-8">
    <title>Kërkim: <?= h($q) ?> – Kronika e Qytetit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../css/lajme.css">
</head>
<body>

<header class="topbar">
  <div class="topbar-inner">

    <a class="brand" href="index.php">KRONIKA E QYTETIT</a>

    <nav class="nav">
      <?php foreach ($categories as $c): ?>
        <?php if (!empty($c['slug'])): ?>
          <a class="nav-link"
             href="category.php?slug=<?= h($c['slug']) ?>">
            <?= h(mb_strtoupper($c['name'], 'UTF-8')) ?>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>

    <div class="search-box">
      <form action="search.php" method="get">
        <input type="text" name="q" value="<?= h($q) ?>" placeholder="Kërko lajme..." required>
        <button type="submit">🔍</button>
      </form>
    </div>

    <div class="actions">
      <?php if (!empty($_SESSION['user_id'])): ?>
        <?php
          $role = $_SESSION['user_role'] ?? '';
          $dash = 'login.php';
          if ($role === 'admin') $dash = 'admin/dashboard.php';
          elseif ($role === 'journalist') $dash = 'journalist/dashboard.php';
        ?>
        <a href="<?= h($dash) ?>">
          <img src="../img/user.png" style="width:40px;height:40px;border-radius:50%;">
        </a>
      <?php else: ?>
        <a href="login.php" class="login-staff-btn">Login Staff</a>
      <?php endif; ?>
    </div>

  </div>
</header>

<main class="wrap">

<section class="section">

  <div class="section-head">
    <h2>Rezultatet për “<?= h($q) ?>”</h2>
  </div>

  <div class="sort-controls">
    <div class="sort-by">
      <span>Rendit sipas:</span>
      <div class="sort-options">
        <a class="sort-option <?= $sort==='newest'?'active':'' ?>" href="<?= pageUrl(1) ?>&sort=newest">Më të rejat</a>
        <a class="sort-option <?= $sort==='oldest'?'active':'' ?>" href="<?= pageUrl(1) ?>&sort=oldest">Më të vjetrat</a>
        <a class="sort-option <?= $sort==='title_az'?'active':'' ?>" href="<?= pageUrl(1) ?>&sort=title_az">Titull A–Z</a>
        <a class="sort-option <?= $sort==='title_za'?'active':'' ?>" href="<?= pageUrl(1) ?>&sort=title_za">Titull Z–A</a>
      </div>
    </div>
  </div>

  <?php if ($total === 0): ?>

    <div class="box" style="padding:20px;">
      Nuk u gjet asnjë artikull.
    </div>

  <?php else: ?>

    <div class="news-grid-2">
      <?php while ($a = $results->fetch_assoc()): ?>
        <div class="news-item">
          <a class="news-thumb" href="<?= h(article_url($a)) ?>">
            <img src="<?= h($a['image_path'] ?: $placeholder) ?>" alt="">
          </a>
          <a class="news-title" href="<?= h(article_url($a)) ?>">
            <?= h($a['title']) ?>
          </a>
          <div class="news-excerpt">
            <?= h(excerpt_text($a['content'], 200)) ?>
          </div>
          <div class="news-meta">
            <?= h(rel_time($a['created_at'])) ?> · <?= h($a['category_name']) ?>
          </div>
        </div>
      <?php endwhile; ?>
    </div>

    <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a class="page-btn <?= $i===$page?'is-active':'' ?>" href="<?= pageUrl($i) ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>

  <?php endif; ?>

</section>

</main>

<footer class="site-footer">
  <div class="footer-bottom">
    © <?= date('Y') ?> Kronika e Qytetit
  </div>
</footer>

</body>
</html>
