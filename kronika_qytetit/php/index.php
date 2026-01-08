<?php
// php/index.php
require_once __DIR__ . '/config.php'; // must define $conn as mysqli

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

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

/**
 * Safe base paths even if project folder changes.
 * Example:
 * - current script: /kronika_qytetit/php/index.php
 *   $PHP_BASE = /kronika_qytetit/php
 */
$PHP_BASE  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); // .../php

function url_php($path) {
    global $PHP_BASE;
    $path = ltrim($path, '/');
    return $PHP_BASE . '/' . $path;
}

function get_categories($conn) {
    if (!db_ok($conn)) return [];
    $res = $conn->query("SELECT id, name, slug FROM categories ORDER BY id ASC");
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
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

function get_latest($conn, $limit = 10, $catId = null) {
    if (!db_ok($conn)) return [];

    if ($catId !== null) {
        $sql = "SELECT id, title, slug, image_path, created_at
                FROM articles
                WHERE status='published' AND category_id=?
                ORDER BY created_at DESC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param("ii", $catId, $limit);
    } else {
        $sql = "SELECT id, title, slug, image_path, created_at
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

function get_latest_by_category($conn, $categoryId, $limit = 8) {
    return get_latest($conn, $limit, (int)$categoryId);
}

function article_url($a) {
    return url_php("article.php?id=" . urlencode($a['id']));
}

/**
 * Accepts:
 * - ?c=sport  (slug)
 * - ?c=3      (id)
 */
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

$placeholder = "../img/placeholder.jpg"; // from /php -> ../img/

$categories = get_categories($conn ?? null);
if (!$categories) {
    // fallback categories if DB is empty / not available
    $categories = [
        ['id'=>1,'name'=>'AKTUALITET','slug'=>'aktualitet'],
        ['id'=>2,'name'=>'METROPOL','slug'=>'metropol'],
        ['id'=>3,'name'=>'SPORT','slug'=>'sport'],
        ['id'=>4,'name'=>'SHOWBIZ','slug'=>'showbiz'],
    ];
}

$catParam = $_GET['c'] ?? '';
[$selectedCat, $selectedCatId] = find_selected_category($categories, (string)$catParam);

// Featured (always the same)
$featured = get_featured($conn ?? null, 6);
if (!$featured) {
    $featured = [
        ['id'=>1,'title'=>'Lajmi kryesor (featured) – vendos titullin këtu','image_path'=>$placeholder,'created_at'=>strtotime('-2 hours')],
        ['id'=>2,'title'=>'Featured 2 – shembull titulli','image_path'=>$placeholder,'created_at'=>strtotime('-1 day')],
        ['id'=>3,'title'=>'Featured 3 – shembull titulli','image_path'=>$placeholder,'created_at'=>strtotime('-1 day')],
        ['id'=>4,'title'=>'Featured 4 – shembull titulli','image_path'=>$placeholder,'created_at'=>strtotime('-2 days')],
        ['id'=>5,'title'=>'Featured 5 – shembull titulli','image_path'=>$placeholder,'created_at'=>strtotime('-2 days')],
        ['id'=>6,'title'=>'Featured 6 – shembull titulli','image_path'=>$placeholder,'created_at'=>strtotime('-3 days')],
    ];
}

// Latest changes when category selected
$latest = get_latest($conn ?? null, 12, $selectedCatId);
if (!$latest) $latest = $featured;

// AKTUALITET block items
$aktItems = [];
if ($selectedCatId !== null) {
    $aktItems = get_latest_by_category($conn ?? null, $selectedCatId, 8);
} else {
    $aktId = null;
    foreach ($categories as $c) {
        if (!empty($c['slug']) && $c['slug'] === 'aktualitet') { $aktId = (int)$c['id']; break; }
    }
    $aktItems = $aktId ? get_latest_by_category($conn ?? null, $aktId, 8) : array_slice($latest, 0, 8);
}
if (!$aktItems) $aktItems = array_slice($latest, 0, 8);

// Category sections to show: selected only, else all
$catsToShow = $selectedCat ? [$selectedCat] : $categories;

?><!doctype html>
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

    <a href="<?= h(url_php('login.php')) ?>" class="login-icon" title="Staff Login">👤</a>

    <!-- ✅ brand always goes home -->
    <a class="brand" href="<?= h(url_php('index.php')) ?>">KRONIKA E QYTETIT</a>

    <nav class="nav">
      <!-- ✅ tabs navigate through categories -->
      <a class="nav-link<?= ($selectedCatId === null ? ' is-active' : '') ?>"
         href="<?= h(url_php('index.php')) ?>">TË GJITHA</a>

      <?php foreach ($categories as $c): ?>
        <?php
          $slugOrId = !empty($c['slug']) ? $c['slug'] : (string)$c['id'];
          $active = ($selectedCatId !== null && (int)$c['id'] === (int)$selectedCatId);
        ?>
        <a class="nav-link<?= $active ? ' is-active' : '' ?>"
           href="<?= h(url_php('index.php?c=' . urlencode($slugOrId))) ?>">
           <?= h($c['name']) ?>
        </a>
      <?php endforeach; ?>

      <a class="nav-link" href="#rreth-nesh">RRETH NESH</a>
      <a class="nav-link" href="#feedback">FEEDBACK</a>
      <a class="nav-link" href="#kontakt">KONTAKT</a>
    </nav>

    <div class="actions">
      <a class="btn" href="<?= h(url_php('login.php')) ?>">STAFF LOGIN</a>
    </div>

  </div>
</header>

<main class="wrap">

  <!-- HERO GRID -->
  <section class="hero-grid">

    <!-- LEFT: slider -->
    <div class="hero">
      <div class="hero-slider" id="heroSlider">
        <?php foreach ($featured as $i => $a): ?>
          <a class="hero-slide<?= $i === 0 ? ' is-active' : '' ?>" href="<?= h(article_url($a)) ?>">
            <img src="<?= h(!empty($a['image_path']) ? $a['image_path'] : $placeholder) ?>" alt="<?= h($a['title']) ?>">
            <div class="hero-overlay">
              <div class="hero-title"><?= h($a['title']) ?></div>
              <div class="hero-meta"><?= h(rel_time($a['created_at'] ?? null)) ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

      <button class="hero-arrow left" type="button" id="heroPrev">‹</button>
      <button class="hero-arrow right" type="button" id="heroNext">›</button>

      <div class="hero-dots" id="heroDots">
        <?php foreach ($featured as $i => $a): ?>
          <button class="dot<?= $i === 0 ? ' is-active' : '' ?>" type="button" data-i="<?= (int)$i ?>"></button>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- RIGHT: video + ad -->
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

  <!-- AD BANNER ROW -->
  <section class="ad-row">
    <div class="ad-banner">BANNER REKLAMË (970x90)</div>
  </section>

  <!-- SECTION: "AKTUALITET style" -->
  <section class="section">
    <div class="section-head">
      <h2><?= h($selectedCat ? $selectedCat['name'] : 'AKTUALITET') ?></h2>

      <?php if ($selectedCat): ?>
        <a class="more" href="<?= h(url_php('index.php')) ?>">Të gjitha</a>
      <?php else: ?>
        <a class="more" href="<?= h(url_php('index.php?c=aktualitet')) ?>">Më shumë</a>
      <?php endif; ?>
    </div>

    <div class="akt-grid">
      <?php
        $big1 = $aktItems[0] ?? null;
        $big2 = $aktItems[1] ?? null;
        $rest = array_slice($aktItems, 2, 6);
      ?>

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

  <!-- CATEGORY SECTIONS -->
  <?php foreach ($catsToShow as $idx => $c): ?>
    <?php
      $items = get_latest_by_category($conn ?? null, (int)$c['id'], 8);
      if (!$items) $items = array_slice($latest, 0, 8);
      $bg = ($idx % 2 === 1) ? ' alt' : '';
      $slugOrId = !empty($c['slug']) ? $c['slug'] : (string)$c['id'];
    ?>
    <section class="section<?= $bg ?>">
      <div class="section-head">
        <h2><?= h($c['name']) ?></h2>
        <?php if ($selectedCat): ?>
          <a class="more" href="<?= h(url_php('index.php')) ?>">Të gjitha</a>
        <?php else: ?>
          <a class="more" href="<?= h(url_php('index.php?c=' . urlencode($slugOrId))) ?>">Më shumë</a>
        <?php endif; ?>
      </div>

      <div class="cards-row">
        <?php foreach ($items as $a): ?>
          <a class="card" href="<?= h(article_url($a)) ?>">
            <img src="<?= h(!empty($a['image_path']) ? $a['image_path'] : $placeholder) ?>" alt="">
            <div class="card-title"><?= h($a['title']) ?></div>
            <div class="card-meta"><?= h(rel_time($a['created_at'] ?? null)) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <!-- ABOUT / FEEDBACK / CONTACT AT BOTTOM -->
  <section id="rreth-nesh" class="info-section">
    <div class="container">
      <h2>Rreth Kronika e Qytetit</h2>
      <p class="section-subtitle">
        Portali yt për lajmet më të rëndësishme nga Tirana dhe qytetet e tjera.
      </p>
      <p>
        <strong>Kronika e Qytetit</strong> është një portal informativ i dedikuar
        për lajmet dhe kronikat më të rëndësishme të përditshme. Misioni ynë është
        të sjellim informacion të shpejtë, të verifikuar dhe të besueshëm për publikun.
      </p>
      <p>
        Ne mbulojmë tema nga kronika, politika, komuniteti, kultura dhe zhvillimet
        sociale, duke vendosur në qendër qytetarin dhe historitë e tij.
      </p>
    </div>
  </section>

  <section id="feedback" class="info-section feedback-section">
    <div class="container">
      <h2>Feedback nga lexuesit</h2>
      <p class="section-subtitle">
        Na trego çfarë mendon për Kronika e Qytetit – mendimi yt na ndihmon të përmirësohemi.
      </p>

      <form class="feedback-form" action="#" method="post"
            onsubmit="alert('Faleminderit! (Ruajtja në DB do shtohet më vonë)'); return false;">
        <label for="feedback-text">Mesazhi juaj</label>
        <textarea id="feedback-text" name="feedback" rows="5"
          placeholder="Shkruaj këtu mendimin ose sugjerimin tënd..."></textarea>

        <button type="submit">Dërgo Feedback</button>
      </form>
    </div>
  </section>

  <section id="kontakt" class="info-section contact-section">
    <div class="container">
      <h2>Na kontakto</h2>
      <p class="section-subtitle">
        Për bashkëpunime, informacione shtesë ose raportime nga terreni.
      </p>

      <div class="contact-details">
        <p><strong>Email:</strong> info@kronikaqytetit.com</p>
        <p><strong>Telefon:</strong> 069 734 3140</p>
        <p><strong>Adresa:</strong> Tiranë, Shqipëri</p>
      </div>
    </div>
  </section>

</main>

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

</body>
</html>
