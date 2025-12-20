<?php
// php/lajme.php
require_once __DIR__ . '/config.php'; // must define $conn as mysqli

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function rel_time($dt) {
    if (!$dt) return '';
    $ts = is_numeric($dt) ? (int)$dt : strtotime($dt);
    if (!$ts) return '';
    $diff = time() - $ts;
    if ($diff < 60) return 'now';
    $mins = intdiv($diff, 60);
    if ($mins < 60) return $mins . ' min ago';
    $hrs = intdiv($mins, 60);
    if ($hrs < 24) return $hrs . ' hours ago';
    $days = intdiv($hrs, 24);
    return $days . ' days ago';
}

function db_ok($conn) {
    return isset($conn) && ($conn instanceof mysqli) && $conn->connect_errno === 0;
}

/**
 * ✅ CHANGE THESE TABLE/COLUMN NAMES IF YOUR SCHEMA IS DIFFERENT
 * categories: id, name, slug
 * articles: id, title, image_url, created_at, author_name, category_id, views
 */
function get_categories($conn) {
    if (!db_ok($conn)) return [];
    $sql = "SELECT id, name, COALESCE(NULLIF(slug,''), id) AS slug
            FROM categories
            ORDER BY name ASC";
    $res = $conn->query($sql);
    if (!$res) return [];
    return $res->fetch_all(MYSQLI_ASSOC);
}

function get_latest_by_category($conn, $categoryId, $limit = 4) {
    if (!db_ok($conn)) return [];
    $sql = "SELECT id, title, image_url, created_at, author_name, views
            FROM articles
            WHERE category_id = ?
            ORDER BY created_at DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param("ii", $categoryId, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function get_most_read($conn, $limit = 5) {
    if (!db_ok($conn)) return [];
    $sql = "SELECT id, title, image_url, created_at, views
            FROM articles
            ORDER BY views DESC, created_at DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// ------- Data -------
$categories = get_categories($conn ?? null);

// Demo fallback so the page still renders if DB is not ready
$placeholder = "https://picsum.photos/seed/kronika/900/600";
if (!$categories) {
    $categories = [
        ['id'=>0,'name'=>'HOME','slug'=>'home'],
        ['id'=>1,'name'=>'POLITICS','slug'=>'politics'],
        ['id'=>2,'name'=>'SPORTS','slug'=>'sports'],
        ['id'=>3,'name'=>'ENTERTAINMENT','slug'=>'entertainment'],
        ['id'=>4,'name'=>'TECHNOLOGY','slug'=>'technology'],
        ['id'=>5,'name'=>'HEALTH','slug'=>'health'],
        ['id'=>6,'name'=>'BUSINESS','slug'=>'business'],
    ];
}

$mostRead = get_most_read($conn ?? null, 5);
if (!$mostRead) {
    $mostRead = [
        ['id'=>11,'title'=>'Google Reveals Breakthrough in Room-Temperature Nuclear Fusion','image_url'=>$placeholder,'created_at'=>strtotime('-2 days'),'views'=>15433],
        ['id'=>12,'title'=>'OpenAI Unveils GPT-5: Revolutionary AI Model Achieves Human-Level Reasoning','image_url'=>$placeholder,'created_at'=>strtotime('-2 days'),'views'=>12543],
        ['id'=>13,'title'=>'SpaceX Starlink 2.0 Satellites Enable Global 10Gbps Internet Coverage','image_url'=>$placeholder,'created_at'=>strtotime('-2 days'),'views'=>11234],
        ['id'=>14,'title'=>'Meta Launches Metaverse 3.0 with Photorealistic Avatar Technology','image_url'=>$placeholder,'created_at'=>strtotime('-2 days'),'views'=>10567],
        ['id'=>15,'title'=>'Apple Vision Pro 2 Launches with Mind-Blowing 8K Per Eye Resolution','image_url'=>$placeholder,'created_at'=>strtotime('-2 days'),'views'=>9877],
    ];
}

// Adjust these to your real pages:
function article_url($a) { return "article.php?id=" . urlencode($a['id']); }
function category_url($c) {
    $name = strtoupper((string)$c['name']);
    if ($name === 'HOME') return "index.php";
    return "category.php?c=" . urlencode($c['slug'] ?? $c['id']);
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Kronika e Qytetit • Lajme</title>

  <!-- If your CSS folder is different, change this path -->
  <link rel="stylesheet" href="../css/lajme.css" />
</head>
<body>

<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="index.php">Kronika e Qytetit</a>

    <nav class="nav">
      <?php
        // first 7 as tabs, rest inside MORE
        $primary = array_slice($categories, 0, 7);
        $more = array_slice($categories, 7);
        foreach ($primary as $cat):
          $name = strtoupper((string)$cat['name']);
      ?>
        <a class="nav-link <?= $name==='HOME' ? 'is-active' : '' ?>"
           href="<?= h(category_url($cat)) ?>"><?= h($name) ?></a>
      <?php endforeach; ?>

      <?php if (count($more) > 0): ?>
        <div class="nav-more">
          <button class="nav-more-btn" type="button">MORE ▾</button>
          <div class="nav-more-menu">
            <?php foreach ($more as $cat): ?>
              <a href="<?= h(category_url($cat)) ?>"><?= h(strtoupper((string)$cat['name'])) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </nav>

    <form class="search" action="search.php" method="get">
      <input name="q" placeholder="Search..." />
      <button type="submit" aria-label="Search">🔍</button>
    </form>
  </div>
</header>

<main class="page">
  <div class="layout">

    <section class="content">
      <?php
        // show a few sections (skip HOME)
        $sections = array_values(array_filter($categories, fn($c) => strtoupper($c['name']) !== 'HOME'));
        $sections = array_slice($sections, 0, 3); // increase later if you want

        foreach ($sections as $cat):
          $catId = (int)($cat['id'] ?? 0);
          $articles = get_latest_by_category($conn ?? null, $catId, 4);

          if (!$articles) {
            // demo
            $articles = [];
            for ($i=0; $i<4; $i++) {
              $articles[] = [
                'id' => $catId*100 + $i + 1,
                'title' => strtoupper($cat['name']) . " sample headline " . ($i+1),
                'image_url' => $placeholder,
                'created_at' => strtotime('-2 days'),
                'author_name' => 'Sarah Smith',
                'views' => 1000 + $i * 250,
              ];
            }
          }
      ?>
        <div class="section">
          <div class="section-head">
            <h2><?= h(strtoupper($cat['name'])) ?></h2>
            <a class="view-all" href="<?= h(category_url($cat)) ?>">View All →</a>
          </div>

          <div class="cards">
            <?php foreach ($articles as $a): ?>
              <article class="card">
                <a class="card-img" href="<?= h(article_url($a)) ?>">
                  <img src="<?= h($a['image_url'] ?: $placeholder) ?>" alt="<?= h($a['title']) ?>">
                </a>
                <div class="card-body">
                  <a class="card-title" href="<?= h(article_url($a)) ?>"><?= h($a['title']) ?></a>
                  <div class="card-meta">
                    <?= h(rel_time($a['created_at'] ?? null)) ?>
                    <?php if (!empty($a['author_name'])): ?>
                      • <?= h($a['author_name']) ?>
                    <?php endif; ?>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </section>

    <aside class="sidebar">
      <div class="side-card">
        <div class="side-head">MOST READ</div>

        <div class="most-read">
          <?php foreach ($mostRead as $m): ?>
            <a class="most-item" href="<?= h(article_url($m)) ?>">
              <img class="most-thumb" src="<?= h($m['image_url'] ?: $placeholder) ?>" alt="">
              <div class="most-text">
                <div class="most-title"><?= h($m['title']) ?></div>
                <div class="most-meta">
                  <?= h(rel_time($m['created_at'] ?? null)) ?>
                  <?php if (!empty($m['views'])): ?>
                    • <?= h(number_format((int)$m['views'])) ?>
                  <?php endif; ?>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </aside>

  </div>
</main>

<script>
document.addEventListener('click', (e) => {
  const wrap = document.querySelector('.nav-more');
  if (!wrap) return;
  const btn = wrap.querySelector('.nav-more-btn');
  const menu = wrap.querySelector('.nav-more-menu');
  if (!btn || !menu) return;

  if (btn.contains(e.target)) menu.classList.toggle('open');
  else if (!wrap.contains(e.target)) menu.classList.remove('open');
});
</script>

</body>
</html>
