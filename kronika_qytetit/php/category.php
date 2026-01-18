<?php
// php/category.php
require_once __DIR__ . '/config.php';
if (($_GET['slug'] ?? '') === 'te-gjitha') {
    header('Location: index.php');
    exit;
}

$categories = [];
$res = $conn->query("SELECT id, name, slug FROM categories ORDER BY id ASC");
if ($res) $categories = $res->fetch_all(MYSQLI_ASSOC);
/* =========================
   Helpers
========================= */

function rel_time($dt) {
    if (!$dt) return '';
    $ts = is_numeric($dt) ? (int)$dt : strtotime($dt);
    if (!$ts) return '';
    $diff = time() - $ts;
    if ($diff < 60) return 'tani';
    $mins = intdiv($diff, 60);
    if ($mins < 60) return $mins . ' min';
    $hrs = intdiv($mins, 60);
    if ($hrs < 24) return $hrs . ' orë';
    $days = intdiv($hrs, 24);
    return $days . ' ditë';
}

$PHP_BASE  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
function url_php($path) {
    global $PHP_BASE;
    return $PHP_BASE . '/' . ltrim($path, '/');
}

/* keep sort + slug when changing links */
function getSortUrl($sort, $page = null) {
    $params = [
        'slug' => $_GET['slug'] ?? '',
        'sort' => $sort
    ];
    if ($page !== null) {
        $params['page'] = $page;
    }
    return url_php('category.php?' . http_build_query($params));
}

$placeholder = "../img/placeholder.jpg";

/* =========================
   Input
========================= */

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') {
    die('Kategori jo e vlefshme');
}

$isAll = ($slug === 'te-gjitha');

/* =========================
   Sorting
========================= */

$allowedSorts = [
    'newest',
    'oldest',
    'title_az',
    'title_za'
];

$sort = $_GET['sort'] ?? 'newest';
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'newest';
}

switch ($sort) {
    case 'oldest':
        $orderBy = 'created_at ASC';
        break;
    case 'title_az':
        $orderBy = 'title ASC';
        break;
    case 'title_za':
        $orderBy = 'title DESC';
        break;
    default:
        $orderBy = 'created_at DESC';
}

/* =========================
   Load category
========================= */

if ($isAll) {
    $category = [
        'id' => null,
        'name' => 'Të gjitha',
        'slug' => 'te-gjitha'
    ];
    $categoryId = null;
} else {
    $stmt = $conn->prepare("SELECT id, name, slug FROM categories WHERE slug = ?");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $category = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$category) {
        die('Kategori nuk u gjet');
    }

    $categoryId = (int)$category['id'];
}

/* =========================
   Pagination
========================= */

$perPage = 12;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

/* =========================
   Total count
========================= */

$sql = "SELECT COUNT(*) FROM articles WHERE status='published'";
if (!$isAll) {
    $sql .= " AND category_id=?";
}

$stmt = $conn->prepare($sql);
if (!$isAll) {
    $stmt->bind_param("i", $categoryId);
}
$stmt->execute();
$stmt->bind_result($totalRows);
$stmt->fetch();
$stmt->close();

$totalPages = max(1, (int)ceil($totalRows / $perPage));

/* =========================
   Load articles
========================= */

$sql = "
    SELECT id, title, slug, image_path, created_at
    FROM articles
    WHERE status='published'
";

if (!$isAll) {
    $sql .= " AND category_id=?";
}

$sql .= " ORDER BY $orderBy LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);

if (!$isAll) {
    $stmt->bind_param("iii", $categoryId, $perPage, $offset);
} else {
    $stmt->bind_param("ii", $perPage, $offset);
}

$stmt->execute();
$articles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html>
<html lang="sq">
<head>
  <meta charset="utf-8">
  <title><?= h($category['name']) ?> – Kronika e Qytetit</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../css/lajme.css">
  <!-- Font Awesome (for icons) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

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

      <a class="nav-link" href="<?= h(url_php('rreth-nesh.php')) ?>">
        RRETH NESH
      </a>
    </nav>
  
  </div>
</header>

<main class="wrap">

  <section class="section">
    <div class="section-head">
      <h1><?= h(mb_strtoupper($category['name'], 'UTF-8')) ?></h1>
    </div>

    <!-- SORT CONTROLS -->
    <div class="sort-controls">
      <div class="sort-by">
        <span>Sort by:</span>
        <div class="sort-options">
          <a href="<?= h(getSortUrl('newest')) ?>" class="sort-option <?= $sort === 'newest' ? 'active' : '' ?>">
            <i class="fas fa-clock"></i> Newest
          </a>
          <a href="<?= h(getSortUrl('oldest')) ?>" class="sort-option <?= $sort === 'oldest' ? 'active' : '' ?>">
            <i class="fas fa-history"></i> Oldest
          </a>
          <a href="<?= h(getSortUrl('title_az')) ?>" class="sort-option <?= $sort === 'title_az' ? 'active' : '' ?>">
            <i class="fas fa-sort-alpha-down"></i> Title A–Z
          </a>
          <a href="<?= h(getSortUrl('title_za')) ?>" class="sort-option <?= $sort === 'title_za' ? 'active' : '' ?>">
            <i class="fas fa-sort-alpha-up"></i> Title Z–A
          </a>
        </div>
      </div>
    </div>

    <?php if (empty($articles)): ?>
      <div class="box" style="padding:16px;">
        Nuk ka artikuj në këtë kategori.
      </div>
    <?php else: ?>
      <div class="cards-row">
        <?php foreach ($articles as $a): ?>
          <a class="card" href="<?= h(url_php('article.php?id=' . $a['id'])) ?>">
            <img src="<?= h(!empty($a['image_path']) ? $a['image_path'] : $placeholder) ?>" alt="">
            <div class="card-title"><?= h($a['title']) ?></div>
            <div class="card-meta"><?= h(rel_time($a['created_at'])) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a class="page-btn" href="<?= h(getSortUrl($sort, $page - 1)) ?>">‹</a>
        <?php endif; ?>

        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <a class="page-btn<?= $p === $page ? ' is-active' : '' ?>"
             href="<?= h(getSortUrl($sort, $p)) ?>">
            <?= $p ?>
          </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
          <a class="page-btn" href="<?= h(getSortUrl($sort, $page + 1)) ?>">›</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </section>

</main>

</body>
</html>