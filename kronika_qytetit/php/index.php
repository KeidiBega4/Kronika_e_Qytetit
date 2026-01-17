<?php
// php/index.php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}



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

/* REQUIRED: You were using excerpt_text() but it was missing */
function db_ok($conn) {
    return isset($conn) && ($conn instanceof mysqli) && $conn->connect_errno === 0;
}

$PHP_BASE  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); // .../php
function url_php($path) {
    global $PHP_BASE;
    $path = ltrim($path, '/');
    return $PHP_BASE . '/' . $path;
}

function get_categories($conn) {
    if (!db_ok($conn)) return [];
    $sql = "SELECT id, name, slug FROM categories ORDER BY id ASC";
    $res = $conn->query($sql);
    if (!$res) return [];
    $rows = $res->fetch_all(MYSQLI_ASSOC);

    $out = [];
    $seenSlug = [];
    $seenName = [];

    foreach ($rows as $c) {
        $id = (int)($c['id'] ?? 0);
        $name = trim((string)($c['name'] ?? ''));
        $slug = trim((string)($c['slug'] ?? ''));

        if ($id <= 0 || $name === '') continue;

        $nameKey = mb_strtolower($name, 'UTF-8');
        $slugKey = mb_strtolower($slug, 'UTF-8');

        if ($slugKey !== '' && isset($seenSlug[$slugKey])) continue;
        if (isset($seenName[$nameKey])) continue;

        if ($slugKey !== '') $seenSlug[$slugKey] = true;
        $seenName[$nameKey] = true;

        $out[] = ['id' => $id, 'name' => $name, 'slug' => $slug];
    }

    return $out;
}

/**
 * IMPORTANT: selects `content` so the preview paragraph can show.
 */
function get_latest($conn, $limit = 10, $catId = null) {
    if (!db_ok($conn)) return [];

    if ($catId !== null) {
        $sql = "SELECT id, title, slug, image_path, content, created_at
                FROM articles
                WHERE status='published' AND category_id=?
                ORDER BY created_at DESC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param("ii", $catId, $limit);
    } else {
        $sql = "SELECT id, title, slug, image_path, content, created_at
                FROM articles
                WHERE status='published'
                ORDER BY created_at DESC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param("i", $limit);
    }

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function article_url($a) {
    return url_php("article.php?id=" . urlencode($a['id']));
}

function find_selected_category(array $categories, string $cParam) {
    $cParam = trim($cParam);
    if ($cParam === '') return [null, null];

    if (ctype_digit($cParam)) {
        $id = (int)$cParam;
        foreach ($categories as $c) {
            if ((int)$c['id'] === $id) return [$c, $id];
        }
        return [null, null];
    }

    foreach ($categories as $c) {
        if (!empty($c['slug']) && $c['slug'] === $cParam) {
            return [$c, (int)$c['id']];
        }
    }
    return [null, null];
}

function is_static_page_slug(string $slug): bool {
    return in_array($slug, ['rreth-nesh', 'feedback', 'kontakt'], true);
}

$placeholder = "../img/placeholder.jpg";
$categories = get_categories($conn ?? null);

// default category
$catParam = (string)($_GET['c'] ?? '');
if ($catParam === '') {
    foreach ($categories as $c) {
        if (!empty($c['slug']) && $c['slug'] === 'te-gjitha') {
            $catParam = 'te-gjitha';
            break;
        }
    }
}

[$selectedCat, $selectedCatId] = find_selected_category($categories, $catParam);
$selectedSlug = $selectedCat['slug'] ?? '';

if ($selectedSlug === 'te-gjitha') {
    $selectedCat = null;
    $selectedCatId = null;
    $selectedSlug = '';
}

$isStaticPage = ($selectedSlug !== '' && is_static_page_slug($selectedSlug));
$isAllFeed = (!$isStaticPage && (($catParam === 'te-gjitha') || ($catParam === '')));

// feedback demo
$feedback_ok = false;
$feedback_err = '';
if ($isStaticPage && $selectedSlug === 'feedback' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $msg = trim((string)($_POST['feedback'] ?? ''));
    if ($msg === '') $feedback_err = 'Ju lutem shkruani një mesazh.';
    else $feedback_ok = true;
}

?><!doctype html>
<html lang="sq">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Kronika e Qytetit</title>

  <!-- IMPORTANT: cache-bust so CSS updates actually show -->
  <link rel="stylesheet" href="../css/lajme.css?v=999" />
</head>
<body>

<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="<?= h(url_php('index.php?c=te-gjitha')) ?>">KRONIKA E QYTETIT</a>

    <nav class="nav">
      <?php foreach ($categories as $c): ?>
        <?php
          $slug = trim((string)($c['slug'] ?? ''));
          if ($slug === '') continue;
          $active = ($catParam === $slug) || ($catParam === '' && $slug === 'te-gjitha');
        ?>
        <a class="nav-link<?= $active ? ' is-active' : '' ?>"
           href="<?= h(url_php('index.php?c=' . urlencode($slug))) ?>">
           <?= h(mb_strtoupper($c['name'], 'UTF-8')) ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="actions">
      <?php if (isset($_SESSION['user_id'])): ?>
        <?php
          $role = $_SESSION['user_role'] ?? '';
          $dash = 'login.php';
          if ($role === 'admin') $dash = 'admin/dashboard.php';
          elseif ($role === 'journalist') $dash = 'journalist/dashboard.php';
        ?>
        <a href="<?= h(url_php($dash)) ?>" title="Dashboard">
          <img src="../img/user.png" alt="User"
               style="width:40px;height:40px;border-radius:50%;cursor:pointer;object-fit:cover;">
        </a>
      <?php else: ?>
        <a href="<?= h(url_php('login.php')) ?>" class="login-staff-btn">Login Staff</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<main class="wrap">

<?php if ($isStaticPage): ?>

  <section class="static-page">
    <div class="static-card">

      <?php if ($selectedSlug === 'rreth-nesh'): ?>
        <h1>Rreth Nesh</h1>
        <p class="static-subtitle">Kush jemi dhe çfarë bëjmë.</p>
        <p><strong>Kronika e Qytetit</strong> është një portal informativ i dedikuar për lajmet më të rëndësishme.</p>
        <p>Synimi ynë është të ofrojmë përditësime të shpejta, të qarta dhe të besueshme.</p>
        <p>Nëse keni sugjerime ose dëshironi të bashkëpunoni, na shkruani te faqja <strong>Kontakt</strong>.</p>

      <?php elseif ($selectedSlug === 'feedback'): ?>
        <h1>Feedback</h1>
        <p class="static-subtitle">Na ndihmoni të përmirësohemi — mendimi juaj ka rëndësi.</p>

        <?php if ($feedback_ok): ?>
          <div class="static-alert success">Faleminderit! Mesazhi juaj u pranua (demo).</div>
        <?php elseif ($feedback_err): ?>
          <div class="static-alert error"><?= h($feedback_err) ?></div>
        <?php endif; ?>

        <form class="static-form" method="post" action="<?= h(url_php('index.php?c=feedback')) ?>">
          <label for="feedback-text">Mesazhi juaj</label>
          <textarea id="feedback-text" name="feedback" rows="6"
            placeholder="Shkruaj këtu..."><?= isset($_POST['feedback']) ? h($_POST['feedback']) : '' ?></textarea>

          <div class="static-actions">
            <button type="submit" class="static-btn">Dërgo</button>
            <a class="static-btn secondary" href="<?= h(url_php('index.php?c=te-gjitha')) ?>">Kthehu</a>
          </div>
        </form>

      <?php else: ?>
        <h1>Kontakt</h1>
        <p class="static-subtitle">Na kontakto për pyetje ose bashkëpunime.</p>

        <div class="contact-grid">
          <div class="contact-item">
            <div class="contact-label">Email</div>
            <div class="contact-value">info@kronikaqytetit.com</div>
          </div>
          <div class="contact-item">
            <div class="contact-label">Telefon</div>
            <div class="contact-value">+355 69 734 3140</div>
          </div>
          <div class="contact-item">
            <div class="contact-label">Adresa</div>
            <div class="contact-value">Tiranë, Shqipëri</div>
          </div>
        </div>

        <div class="static-actions">
          <a class="static-btn" href="<?= h(url_php('index.php?c=feedback')) ?>">Dërgo Feedback</a>
          <a class="static-btn secondary" href="<?= h(url_php('index.php?c=te-gjitha')) ?>">Kthehu</a>
        </div>
      <?php endif; ?>

    </div>
  </section>

<?php else: ?>

  <?php
    $featuredPool = get_latest($conn ?? null, 10, $selectedCatId);
    $featured = $featuredPool[0] ?? null;

    $sideLatest = get_latest($conn ?? null, 12, null);

    if ($isAllFeed) {
        $mainFeed = get_latest($conn ?? null, 40, null);
        $feedTitle = 'LAJMET E FUNDIT';
    } else {
        $mainFeed = get_latest($conn ?? null, 40, $selectedCatId);
        $feedTitle = $selectedCat ? mb_strtoupper($selectedCat['name'], 'UTF-8') : 'LAJMET E FUNDIT';
    }

    $belowFeed = $mainFeed;
    if (!empty($featured)) {
        $belowFeed = array_values(array_filter($belowFeed, function ($x) use ($featured) {
            return (int)$x['id'] !== (int)$featured['id'];
        }));
    }
  ?>

  <section class="section section-plain">
    <div class="section-head section-head-plain">
      <h2 class="section-title"><?= h($feedTitle) ?></h2>
    </div>

    <div class="hero-grid">
      <div class="hero">
        <?php if (!$featured): ?>
          <div class="hero-empty">Nuk ka artikuj ende.</div>
        <?php else: ?>
          <a class="hero-slide" href="<?= h(article_url($featured)) ?>">
            <img src="<?= h(!empty($featured['image_path']) ? $featured['image_path'] : $placeholder) ?>" alt="<?= h($featured['title']) ?>">
            <div class="hero-overlay">
              <div class="hero-title"><?= h($featured['title']) ?></div>
              <div class="hero-meta"><?= h(rel_time($featured['created_at'] ?? null)) ?></div>
            </div>
          </a>
        <?php endif; ?>
      </div>

      <aside class="hero-side">
        <div class="box">
          <div class="ad">
            <div class="ad-ph">REKLAMË</div>
          </div>
        </div>

        <div class="box">
          <div class="box-title">LAJMET E FUNDIT</div>
          <div class="video">
            <div class="akt-list">
              <?php if (empty($sideLatest)): ?>
                <div class="muted">Nuk ka lajme ende.</div>
              <?php else: ?>
                <?php foreach ($sideLatest as $a): ?>
                  <a class="mini" href="<?= h(article_url($a)) ?>">
                    <img src="<?= h(!empty($a['image_path']) ? $a['image_path'] : $placeholder) ?>" alt="">
                    <div>
                      <div class="mini-title"><?= h($a['title']) ?></div>
                      <div class="mini-meta"><?= h(rel_time($a['created_at'] ?? null)) ?></div>
                    </div>
                  </a>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </aside>
    </div>
  </section>

  <div class="ad-row">
    <div class="ad-banner">BANNER REKLAME</div>
  </div>

  <section class="section">
    <div class="section-head">
      <h2><?= h($isAllFeed ? 'Më të rejat' : 'Artikuj') ?></h2>
      <a class="more" href="<?= h(url_php('index.php?c=te-gjitha')) ?>">Te gjitha</a>
    </div>

    <?php if (empty($belowFeed)): ?>
      <div class="box" style="padding:16px;">Nuk ka lajme ende.</div>
    <?php else: ?>
      <div class="news-grid-2">
        <?php foreach ($belowFeed as $a): ?>
          <div class="news-item">

            <a class="news-thumb" href="<?= h(article_url($a)) ?>">
              <img src="<?= h(!empty($a['image_path']) ? $a['image_path'] : $placeholder) ?>" alt="">
            </a>

            <a class="news-title" href="<?= h(article_url($a)) ?>">
              <?= h($a['title']) ?>
            </a>

            <div class="news-excerpt">
              <?= h(excerpt_text($a['content'] ?? '', 260)) ?>
            </div>

            <div class="news-meta">
              <?= h(rel_time($a['created_at'] ?? null)) ?>
            </div>

          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

<?php endif; ?>

</main>

<footer class="site-footer">
  <div class="footer-inner">
    <div class="footer-section">
      <h3>Rreth Nesh</h3>
      <p>Kronika e Qytetit është një portal informativ që synon të ofrojë lajme të sakta, të shpejta dhe të besueshme.</p>
    </div>

    <div class="footer-section">
      <h3>Kontakt</h3>
      <p><strong>Email:</strong> info@kronikaqytetit.al</p>
      <p><strong>Telefon:</strong> +355 69 123 4567</p>
      <p><strong>Adresa:</strong> Tiranë, Shqipëri</p>
    </div>
  </div>

  <div class="footer-bottom">
    © <?= date('Y'); ?> Kronika e Qytetit. Të gjitha të drejtat e rezervuara.
  </div>
</footer>

</body>
</html>
