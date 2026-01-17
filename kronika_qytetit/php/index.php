<?php
// php/index.php
require_once __DIR__ . '/config.php';



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

$PHP_BASE  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); // .../php
$ROOT_BASE = rtrim(dirname($PHP_BASE), '/\\'); 
function url_php($path) {
    global $PHP_BASE;
    $path = ltrim($path, '/');
    return $PHP_BASE . '/' . $path;
}

/**
 * ✅ Load ALL navbar items from DB (no reserved filtering)
 * ✅ De-duplicate
 */
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

        $out[] = [
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
        ];
    }

    return $out;
}

function get_featured($conn, $limit = 6) {
    if (!db_ok($conn)) return [];
    $sql = "SELECT id, title, slug, image_path, created_at
            FROM articles
            WHERE status='published' AND is_featured=1
            ORDER BY created_at DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function get_latest($conn, $limit = null, $catId = null) {
    if (!db_ok($conn)) return [];

    if ($catId !== null) {
        $sql = "SELECT id, title, slug, image_path, created_at
                FROM articles
                WHERE status='published' AND category_id=?
                ORDER BY created_at DESC";
    } else {
        $sql = "SELECT id, title, slug, image_path, created_at
                FROM articles
                WHERE status='published'
                ORDER BY created_at DESC";
    }

    if ($limit !== null) {
        $sql .= " LIMIT ?";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];

    if ($catId !== null && $limit !== null) {
        $stmt->bind_param("ii", $catId, $limit);
    } elseif ($catId !== null) {
        $stmt->bind_param("i", $catId);
    } elseif ($limit !== null) {
        $stmt->bind_param("i", $limit);
    }

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function get_latest_by_category($conn, $categoryId, $limit = 8) {
    return get_latest($conn, $limit, (int)$categoryId);
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

/** ✅ slugs that should open a static page, not news */
function is_static_page_slug(string $slug): bool {
    return in_array($slug, ['rreth-nesh', 'feedback', 'kontakt'], true);
}

$placeholder = "../img/placeholder.jpg";

$categories = get_categories($conn ?? null);

/**
 * ✅ Default: if no ?c= provided, behave like "te-gjitha" if it exists
 * (so your home loads normally)
 */
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

/** ✅ if te-gjitha -> treat as no filtering */
if ($selectedSlug === 'te-gjitha') {
    $selectedCat = null;
    $selectedCatId = null;
    $selectedSlug = '';
}

/** =========================
 *  STATIC PAGE MODE
 * ========================= */
$isStaticPage = ($selectedSlug !== '' && is_static_page_slug($selectedSlug));

/** ===== Normal homepage data (only when not static page) ===== */
$featured = [];
$latest = [];
$aktItems = [];
$catsToShow = [];

if (!$isStaticPage) {
    // ✅ Only DB content (NO hardcoded fallbacks)
    $featured = get_featured($conn ?? null, 6);
    $latest = get_latest($conn ?? null, null, $selectedCatId);

    // ✅ AKTUALITET block items
    if ($selectedCatId !== null) {
        $aktItems = get_latest_by_category($conn ?? null, $selectedCatId, 8);
    } else {
        $aktId = null;
        foreach ($categories as $c) {
            if (!empty($c['slug']) && mb_strtolower($c['slug'], 'UTF-8') === 'aktualitet') {
                $aktId = (int)$c['id'];
                break;
            }
        }
        $aktItems = $aktId ? get_latest_by_category($conn ?? null, $aktId, 8) : [];
    }

    // ✅ Category sections to show: selected only, else all (but skip static page slugs)
    $catsToShow = [];
    if ($selectedCat) {
        $catsToShow = [$selectedCat];
    } else {
        foreach ($categories as $c) {
            $slug = (string)($c['slug'] ?? '');
            if ($slug !== '' && is_static_page_slug($slug)) continue;
            if ($slug === 'te-gjitha') continue;
            $catsToShow[] = $c;
        }
    }
}

/** ===== Feedback submission (demo) ===== */
$feedback_ok = false;
$feedback_err = '';
if ($isStaticPage && $selectedSlug === 'feedback' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $msg = trim((string)($_POST['feedback'] ?? ''));
    if ($msg === '') {
        $feedback_err = 'Ju lutem shkruani një mesazh.';
    } else {
        // demo only (later you can save it to DB)
        $feedback_ok = true;
    }
}

$cat_articles = [];

if (!$isStaticPage) {
    foreach ($catsToShow as $c) {
        $cat_articles[] = [
            'info' => $c,
            'articles' => get_latest_by_category(
                $conn ?? null,
                (int)$c['id'],
                8
            )
        ];
    }
}


?>

<!doctype html>
<html lang="sq">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Kronika e Qytetit</title>
  <link rel="stylesheet" href="../css/lajme.css" />
</head>
<body>

<header class="topbar">
  <div class="topbar-inner">

    <a class="brand" href="<?= h(url_php('index.php')) ?>">KRONIKA E QYTETIT</a>

    <nav class="nav">
      <?php foreach ($categories as $c): ?>
        <?php
          $slug = trim((string)($c['slug'] ?? ''));
          if ($slug === '') continue;
          $active = ($catParam === $slug) || ($catParam === '' && $slug === 'te-gjitha');
        ?>
        <a class="nav-link"
            href="<?= h(url_php('category.php?slug=' . urlencode($slug))) ?>">
           <?= h(mb_strtoupper($c['name'], 'UTF-8')) ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="actions">
      <?php
// ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<?php if (isset($_SESSION['user_id'])): ?>
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
    <a href="<?= h(url_php('login.php')) ?>" class="login-staff-btn">
        Login Staff
    </a>
<?php endif; ?>


    </div>

  </div>
</header>

<main class="wrap">

<?php if ($isStaticPage): ?>

  <!-- ✅ STATIC PAGES (About / Feedback / Contact) -->
  <section class="static-page">
    <div class="static-card">

      <?php if ($selectedSlug === 'rreth-nesh'): ?>
        <h1>Rreth Nesh</h1>
        <p class="static-subtitle">Kush jemi dhe çfarë bëjmë.</p>

        <p>
          <strong>Kronika e Qytetit</strong> është një portal informativ i dedikuar për
          lajmet më të rëndësishme nga Tirana dhe qytetet e tjera.
        </p>
        <p>
          Synimi ynë është të ofrojmë përditësime të shpejta, të qarta dhe të besueshme,
          duke mbuluar aktualitetin, showbiz-in, sportin dhe teknologjinë.
        </p>
        <p>
          Nëse keni sugjerime ose dëshironi të bashkëpunoni, na shkruani te faqja
          <strong>Kontakt</strong>.
        </p>

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
            placeholder="Shkruaj këtu mendimin ose sugjerimin tënd..."><?= isset($_POST['feedback']) ? h($_POST['feedback']) : '' ?></textarea>

          <div class="static-actions">
            <button type="submit" class="static-btn">Dërgo</button>
            <a class="static-btn secondary" href="<?= h(url_php('index.php?c=te-gjitha')) ?>">Kthehu</a>
          </div>
        </form>

      <?php else: /* kontakt */ ?>
        <h1>Kontakt</h1>
        <p class="static-subtitle">Na kontakto për pyetje, bashkëpunime ose sugjerime.</p>

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

  <!-- ✅ NORMAL HOMEPAGE / NEWS -->
  <section class="hero-grid">

    <div class="hero">
      <div class="hero-slider" id="heroSlider">
  <?php if (empty($featured)): ?>
        <div class="box" style="padding:16px;">Nuk ka artikuj featured ende.</div>
      <?php else: ?>
        <?php foreach ($featured as $i => $a): ?>
          <a class="hero-slide<?= $i === 0 ? ' is-active' : '' ?>" href="<?= h(article_url($a)) ?>">
            <img src="<?= h(!empty($a['image_path']) ? $a['image_path'] : $placeholder) ?>" alt="<?= h($a['title']) ?>">
            <div class="hero-overlay">
              <div class="hero-title"><?= h($a['title']) ?></div>
              <div class="hero-meta"><?= h(rel_time($a['created_at'] ?? null)) ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

      <button class="hero-arrow left" type="button" id="heroPrev">‹</button>
      <button class="hero-arrow right" type="button" id="heroNext">›</button>

    <div class="hero-dots" id="heroDots">
  <?php foreach ($featured as $i => $a): ?>
    <button class="dot<?= $i === 0 ? ' is-active' : '' ?>" type="button" data-i="<?= (int)$i ?>"></button>
  <?php endforeach; ?>
</div>

    </div>

    <aside class="hero-side">
      <div class="box">
        <div class="box-title">VIDEO</div>
        <div class="video">
          <div class="video-ph">Video placeholder</div>
        </div>
      </div>

      <div class="box ad">
        <div class="ad-ph">REKLAMË</div>
      </div>
    </aside>

  </section>

  <section class="ad-row">
    <div class="ad-banner">BANNER REKLAMË (970x90)</div>
  </section>

  <section class="section">
    <div class="section-head">
      <h2>AKTUALITET</h2>
      <a class="more" href="<?= h(url_php('category.php?slug=aktualitet')) ?>">Më shumë</a>
    </div>

    <div class="akt-grid">
      <?php
        $big1 = $aktItems[0] ?? null;
        $big2 = $aktItems[1] ?? null;
        $rest = array_slice($aktItems, 2, 6);
      ?>
      <?php if (empty($aktItems)): ?>
        <div class="box" style="padding:16px;">Nuk ka lajme në Aktualitet ende.</div>
      <?php endif; ?>

      <div class="akt-big">
        <?php if ($big1): ?>
          <a class="big-card" href="<?= h(article_url($big1)) ?>">
            <img src="<?= h(!empty($big1['image_path']) ? $big1['image_path'] : $placeholder) ?>" alt="">
            <div class="big-title"><?= h($big1['title']) ?></div>
            <div class="big-meta"><?= h(rel_time($big1['created_at'] ?? null)) ?></div>
          </a>
        <?php endif; ?>
      </div>

      <div class="akt-big">
        <?php if ($big2): ?>
          <a class="big-card" href="<?= h(article_url($big2)) ?>">
            <img src="<?= h(!empty($big2['image_path']) ? $big2['image_path'] : $placeholder) ?>" alt="">
            <div class="big-title"><?= h($big2['title']) ?></div>
            <div class="big-meta"><?= h(rel_time($big2['created_at'] ?? null)) ?></div>
          </a>
        <?php endif; ?>
      </div>

      <div class="akt-list">
        <?php foreach ($rest as $a): ?>
          <a class="mini" href="<?= h(article_url($a)) ?>">
            <img src="<?= h(!empty($a['image_path']) ? $a['image_path'] : $placeholder) ?>" alt="">
            <div>
              <div class="mini-title"><?= h($a['title']) ?></div>
              <div class="mini-meta"><?= h(rel_time($a['created_at'] ?? null)) ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <?php foreach ($cat_articles as $idx => $block): ?>
  <?php
    $c = $block['info'];
    $items = $block['articles'];
    $bg = ($idx % 2 === 1) ? ' alt' : '';
    $slugOrId = !empty($c['slug']) ? $c['slug'] : (string)$c['id'];
  ?>
  <section class="section<?= $bg ?>">
    <div class="section-head">
      <h2><?= h(mb_strtoupper($c['name'], 'UTF-8')) ?></h2>
      <a class="more" href="<?= h(url_php('category.php?slug=' . urlencode($c['slug']))) ?>">
        Më shumë
      </a>
    </div>

    <?php if (empty($items)): ?>
      <div class="box" style="padding:16px;">
        Nuk ka lajme në këtë kategori ende.
      </div>
    <?php else: ?>
      <div class="cards-row">
        <?php foreach ($items as $a): ?>
          <a class="card" href="<?= h(article_url($a)) ?>">
            <img src="<?= h(!empty($a['image_path']) ? $a['image_path'] : $placeholder) ?>" alt="">
            <div class="card-title"><?= h($a['title']) ?></div>
            <div class="card-meta"><?= h(rel_time($a['created_at'] ?? null)) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
<?php endforeach; ?>


  <script>
  (function(){
    const slides = Array.from(document.querySelectorAll(".hero-slide"));
    const dots = Array.from(document.querySelectorAll("#heroDots .dot"));
    const prev = document.getElementById("heroPrev");
    const next = document.getElementById("heroNext");
    if (!slides.length) return;

    let i = 0;
    function show(n){
      slides[i].classList.remove("is-active");
      dots[i] && dots[i].classList.remove("is-active");
      i = (n + slides.length) % slides.length;
      slides[i].classList.add("is-active");
      dots[i] && dots[i].classList.add("is-active");
    }
    prev && prev.addEventListener("click", ()=>show(i-1));
    next && next.addEventListener("click", ()=>show(i+1));
    dots.forEach(d => d.addEventListener("click", ()=>show(parseInt(d.dataset.i,10)||0)));
    setInterval(()=>show(i+1), 6000);
  })();
  </script>

<?php endif; ?>

</main>


<footer class="site-footer">
    <div class="footer-inner">

        <div class="footer-section">
            <h3>Rreth Nesh</h3>
            <p>
                Kronika e Qytetit është një portal informativ që synon të ofrojë
                lajme të sakta, të shpejta dhe të besueshme për publikun.
            </p>
        </div>

        <div class="footer-section">
            <h3>Kontakt</h3>
            <p><strong>Email:</strong> info@kronikaqytetit.al</p>
            <p><strong>Telefon:</strong> +355 69 123 4567</p>
            <p><strong>Adresa:</strong> Tiranë, Shqipëri</p>
        </div>

    </div>
    <div class="footer-bottom">
        © <?php echo date('Y'); ?> Kronika e Qytetit. Të gjitha të drejtat e rezervuara.
    </div>
</footer>
          
</body>
</html>
 