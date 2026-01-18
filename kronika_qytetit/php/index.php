<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   HELPERS
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

function db_ok($conn) {
    return isset($conn) && ($conn instanceof mysqli) && $conn->connect_errno === 0;
}

$PHP_BASE = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
function url_php($path) {
    global $PHP_BASE;
    return $PHP_BASE . '/' . ltrim($path, '/');
}

function article_url($a) {
    return url_php('article.php?id=' . urlencode($a['id']));
}

/* =========================
   BREAKING NEWS (TEKNOLOGJI)
========================= */

$breakingNews = null;

$sql = "
  SELECT a.id, a.title, a.created_at
  FROM articles a
  INNER JOIN categories c ON c.id = a.category_id
  WHERE a.status = 'published'
    AND c.slug = 'teknologji'
  ORDER BY a.created_at DESC
  LIMIT 1
";

$res = $conn->query($sql);
if ($res && $res->num_rows > 0) {
  $breakingNews = $res->fetch_assoc();
}


/* =========================
   DATA FETCH
========================= */

$placeholder = "../img/placeholder.jpg";

/* Categories */
$categories = [];
$res = $conn->query("SELECT id, name, slug FROM categories ORDER BY id ASC");
if ($res) {
    $categories = $res->fetch_all(MYSQLI_ASSOC);
}

/* Featured slider (latest 5) */
$featuredSlides = [];
$res = $conn->query("
    SELECT id, title, image_path, created_at
    FROM articles
    WHERE status='published'
    ORDER BY created_at DESC
    LIMIT 5
");
if ($res) {
    $featuredSlides = $res->fetch_all(MYSQLI_ASSOC);
}

/* Side latest (4) */
$sideLatest = [];
$res = $conn->query("
    SELECT id, title, image_path, created_at
    FROM articles
    WHERE status='published'
    ORDER BY created_at DESC
    LIMIT 4
");
if ($res) {
    $sideLatest = $res->fetch_all(MYSQLI_ASSOC);
}

/* Category blocks (8 per category) */
$cat_articles = [];
foreach ($categories as $c) {
    if ($c['slug'] === 'te-gjitha') continue;

    $stmt = $conn->prepare("
        SELECT id, title, image_path, created_at
        FROM articles
        WHERE status='published' AND category_id=?
        ORDER BY created_at DESC
        LIMIT 8
    ");
    $stmt->bind_param("i", $c['id']);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $cat_articles[] = [
        'info' => $c,
        'articles' => $items
    ];
}
?>
<!doctype html>
<html lang="sq">
<head>
  <meta charset="utf-8">
  <title>Kronika e Qytetit</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../css/lajme.css?v=reset1">
  <link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<!-- TOP INFO BAR -->
<div class="top-info-bar">
  <div class="top-info-inner">

    <div class="date-weather">
      <span class="current-date">
        <?= date("l, F j, Y"); ?>
      </span>
      <span class="weather">
        18°C Tiranë
        <i class="fas fa-cloud-sun"></i>
      </span>
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
    </nav>
    <div class="actions">
  <?php if (!empty($_SESSION['user_id'])): ?>

    <?php
      $role = $_SESSION['user_role'] ?? '';
      $dash = 'login.php';
      if ($role === 'admin') {
        $dash = 'admin/dashboard.php';
      } elseif ($role === 'journalist') {
        $dash = 'journalist/dashboard.php';
      }
    ?>

    <a href="<?= h(url_php($dash)) ?>" title="Dashboard">
      <img
        src="../img/user.png"
        alt="User"
        style="width:40px;height:40px;border-radius:50%;cursor:pointer;object-fit:cover;"
      >
    </a>

  <?php else: ?>

    <!-- 🔴 CORE PROJECT BUTTON -->
    <a href="<?= h(url_php('login.php')) ?>" class="login-staff-btn">
      Login Staff
    </a>

  <?php endif; ?>
</div>

  </div>
</header>

<?php if ($breakingNews): ?>
<div class="breaking-news">
  <div class="breaking-inner">

    <div class="breaking-title">
      BREAKING
    </div>

    <div class="breaking-content">
      <div class="breaking-marquee">
        <a href="<?= h(url_php('article.php?id=' . $breakingNews['id'])) ?>">
          <?= h($breakingNews['title']) ?>
        </a>
      </div>
    </div>

  </div>
</div>
<?php endif; ?>

<main class="wrap">

<!-- =========================
     TOP FEATURED SECTION
========================= -->
<section class="section section-plain">
  <div class="hero-grid">

    <div class="hero">
      <?php if (empty($featuredSlides)): ?>
        <div class="hero-empty">Nuk ka artikuj ende.</div>
      <?php else: ?>
        <div class="hero-slider">
          <?php foreach ($featuredSlides as $i => $a): ?>
            <a class="hero-slide <?= $i === 0 ? 'is-active' : '' ?>"
              href="<?= h(article_url($a)) ?>">
              <img src="<?= h($a['image_path'] ?: $placeholder) ?>" alt="">
              <div class="hero-overlay">
                <div class="hero-title"><?= h($a['title']) ?></div>
                <div class="hero-meta"><?= h(rel_time($a['created_at'])) ?></div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>

        <!-- arrows -->
        <button class="hero-arrow left" aria-label="Previous">&#10094;</button>
        <button class="hero-arrow right" aria-label="Next">&#10095;</button>
      <?php endif; ?>
    </div>

    <aside class="hero-side">
      <div class="box">
        <div class="box-title">LAJMET E FUNDIT</div>
        <div class="video">
          <div class="akt-list">
            <?php foreach ($sideLatest as $a): ?>
              <a class="mini" href="<?= h(article_url($a)) ?>">
                <img src="<?= h($a['image_path'] ?: $placeholder) ?>" alt="">
                <div>
                  <div class="mini-title"><?= h($a['title']) ?></div>
                  <div class="mini-meta"><?= h(rel_time($a['created_at'])) ?></div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </aside>

  </div>
</section>

<!-- =========================
     CATEGORY SECTIONS
========================= -->
<?php foreach ($cat_articles as $i => $block): ?>
<section class="section<?= $i % 2 ? ' alt' : '' ?>">
  <div class="section-head">
    <h2><?= h(mb_strtoupper($block['info']['name'], 'UTF-8')) ?></h2>
    <a class="more"
       href="<?= h(url_php('category.php?slug=' . urlencode($block['info']['slug']))) ?>">
       Më shumë
    </a>
  </div>

  <div class="cards-row">
    <?php foreach ($block['articles'] as $a): ?>
      <a class="card" href="<?= h(article_url($a)) ?>">
        <img src="<?= h($a['image_path'] ?: $placeholder) ?>" alt="">
        <div class="card-title"><?= h($a['title']) ?></div>
        <div class="card-meta"><?= h(rel_time($a['created_at'])) ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endforeach; ?>

</main>

<footer class="site-footer">
  <div class="footer-bottom">
    © <?= date('Y') ?> Kronika e Qytetit
  </div>
</footer>

<script>
(function () {
  const slides = document.querySelectorAll(".hero-slider .hero-slide");
  const prev = document.querySelector(".hero-arrow.left");
  const next = document.querySelector(".hero-arrow.right");

  if (!slides.length) return;

  let i = 0;

  function show(n) {
    slides[i].classList.remove("is-active");
    i = (n + slides.length) % slides.length;
    slides[i].classList.add("is-active");
  }

  prev && prev.addEventListener("click", () => show(i - 1));
  next && next.addEventListener("click", () => show(i + 1));

  setInterval(() => show(i + 1), 5000);
})();
</script>


</body>
</html>
