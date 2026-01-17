<?php
require_once __DIR__ . '/../panel/_guard.php';
require_journalist();
require_once __DIR__ . '/../config.php';

$page_title = "My Articles";
$sidebar_file = __DIR__ . '/_sidebar_journalist.php';

$uid = (int)($_SESSION['user_id'] ?? 0);

$category = (int)($_GET['category'] ?? 0);
$status   = $_GET['status'] ?? 'all';
$sort     = $_GET['sort'] ?? 'new';
$q        = trim($_GET['q'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));

$perPage = 10;
$offset  = ($page - 1) * $perPage;

$catRes = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
$categories = $catRes ? $catRes->fetch_all(MYSQLI_ASSOC) : [];

$where = " WHERE author_id = ? ";
$params = [$uid];
$types = "i";

if ($category > 0) {
  $where .= " AND category_id = ? ";
  $params[] = $category;
  $types .= "i";
}

$allowedStatuses = ['pending', 'published', 'rejected'];
if ($status !== 'all' && in_array($status, $allowedStatuses, true)) {
  $where .= " AND status = ? ";
  $params[] = $status;
  $types .= "s";
}

if ($q !== '') {
  $where .= " AND title LIKE ? ";
  $params[] = "%$q%";
  $types .= "s";
}

switch ($sort) {
  case 'az':  $order = " ORDER BY title ASC "; break;
  case 'za':  $order = " ORDER BY title DESC "; break;
  case 'old': $order = " ORDER BY created_at ASC "; break;
  default:    $order = " ORDER BY created_at DESC ";
}

$countStmt = $conn->prepare("SELECT COUNT(*) AS c FROM articles $where");
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total = (int)($countStmt->get_result()->fetch_assoc()['c'] ?? 0);
$countStmt->close();

$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = $conn->prepare(
  "SELECT id, title, status, image_path, created_at
   FROM articles
   $where
   $order
   LIMIT $perPage OFFSET $offset"
);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$articles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function status_label(string $s): string {
  return match ($s) {
    'pending' => 'Pending',
    'published' => 'Published',
    'rejected' => 'Rejected',
    default => $s
  };
}

ob_start();
?>
<form method="GET" class="jp-filter">
  <div class="jp-field">
    <div class="jp-label">Category</div>
    <select class="jp-select" name="category">
      <option value="0">All</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $category === (int)$c['id'] ? 'selected' : '' ?>>
          <?= h($c['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="jp-field">
    <div class="jp-label">Status</div>
    <select class="jp-select" name="status">
      <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
      <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
      <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
      <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
    </select>
  </div>

  <div class="jp-field">
    <div class="jp-label">Sort</div>
    <select class="jp-select" name="sort">
      <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>Newest</option>
      <option value="old" <?= $sort === 'old' ? 'selected' : '' ?>>Oldest</option>
      <option value="az" <?= $sort === 'az' ? 'selected' : '' ?>>A–Z</option>
      <option value="za" <?= $sort === 'za' ? 'selected' : '' ?>>Z–A</option>
    </select>
  </div>

  <div class="jp-field jp-grow">
    <div class="jp-label">Search</div>
    <input class="jp-input" type="text" name="q" value="<?= h($q) ?>" placeholder="Search title...">
  </div>

  <button class="jp-btn jp-btn-primary" type="submit">Filter</button>
  <a class="jp-btn jp-btn-success" href="create_article.php">+ Create</a>
</form>

<div class="jp-table-wrap">
  <table class="jp-table">
    <thead>
      <tr>
        <th>Title</th>
        <th>Status</th>
        <th>Created</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($articles)): ?>
        <tr><td colspan="4">No articles found.</td></tr>
      <?php else: ?>
        <?php foreach ($articles as $a): ?>
          <tr>
            <td>
              <div class="jp-title-row">
                <?php if (!empty($a['image_path'])): ?>
                  <img class="jp-thumb" src="../../img/uploads/<?= h(basename($a['image_path'])) ?>" alt="">
                <?php endif; ?>
                <div><?= h($a['title']) ?></div>
              </div>
            </td>
            <td><?= h(status_label((string)$a['status'])) ?></td>
            <td><?= h(date('d.m.Y H:i', strtotime((string)$a['created_at']))) ?></td>
            <td>
              <?php if (($a['status'] ?? '') !== 'published'): ?>
                <a href="edit_article.php?id=<?= (int)$a['id'] ?>">Edit</a>

                <form method="POST" action="delete_article.php" style="display:inline"
                      onsubmit="return confirm('Delete this article?');">
                  <input type="hidden" name="article_id" value="<?= (int)$a['id'] ?>">
                  <button class="jp-btn jp-btn-danger" type="submit">Delete</button>
                </form>
              <?php else: ?>
                <a href="../article.php?id=<?= (int)$a['id'] ?>">View</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
  <div class="jp-pagination">
    <?php for ($i=1; $i<=$totalPages; $i++): ?>
      <?php $qs = http_build_query(array_merge($_GET, ['page' => $i])); ?>
      <a class="jp-page <?= $i === $page ? 'jp-active' : '' ?>" href="?<?= h($qs) ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
<?php endif; ?>

<?php
$content_html = ob_get_clean();
include __DIR__ . '/../panel/_layout.php';
