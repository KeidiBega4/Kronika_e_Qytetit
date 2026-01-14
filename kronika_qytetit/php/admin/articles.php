<?php
require_once __DIR__ . '/../panel/_guard.php';
require_admin();
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($id > 0) {

        // ✅ APPROVE / REJECT (only allowed actions)
        if (in_array($action, ['approve', 'reject'], true)) {
            $newStatus = ($action === 'approve') ? 'published' : 'rejected';

            // Optional: if you want to set published_at too
            if ($action === 'approve') {
                $stmt = $mysqli->prepare("UPDATE articles SET status=?, published_at=NOW() WHERE id=?");
                $stmt->bind_param("si", $newStatus, $id);
            } else {
                $stmt = $mysqli->prepare("UPDATE articles SET status=? WHERE id=?");
                $stmt->bind_param("si", $newStatus, $id);
            }

            $stmt->execute();
            $stmt->close();
        }

        // ✅ DELETE (admin can delete any article)
        if ($action === 'delete') {
            // get image path first (so we can delete file after row delete)
            $stmt = $mysqli->prepare("SELECT image_path FROM articles WHERE id=? LIMIT 1");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $article = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $stmt = $mysqli->prepare("DELETE FROM articles WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            // Optional: delete image file from disk (only if inside img/uploads/)
            $imagePath = (string)($article['image_path'] ?? '');
            if ($imagePath !== '' && str_starts_with($imagePath, 'img/uploads/')) {
                $abs = __DIR__ . '/../../' . $imagePath; // goes to project root
                if (is_file($abs)) @unlink($abs);
            }
        }
    }

    header("Location: articles.php");
    exit;
}

$sql = "SELECT a.id, a.title, a.status, a.created_at,
               u.name AS author_name,
               c.name AS category_name
        FROM articles a
        LEFT JOIN users u ON u.id = a.author_id
        LEFT JOIN categories c ON c.id = a.category_id
        ORDER BY a.created_at DESC
        LIMIT 200";

$res = $mysqli->query($sql);
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

function badge(string $st): string {
    if ($st === 'published') return '<span class="badge b-pub">Published</span>';
    if ($st === 'rejected')  return '<span class="badge b-rej">Rejected</span>';
    return '<span class="badge b-pen">Pending</span>';
}

$page_title = "Manage Articles";
$sidebar_file = __DIR__ . '/../panel/_sidebar_admin.php';

ob_start();
?>
<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Title</th>
      <th>Category</th>
      <th>Author</th>
      <th>Status</th>
      <th>Actions</th>
    </tr>
  </thead>

  <tbody>
    <?php foreach ($rows as $r): ?>
      <?php $st = $r['status'] ?? 'pending'; ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= h($r['title'] ?? '') ?></td>
        <td><?= h($r['category_name'] ?? '-') ?></td>
        <td><?= h($r['author_name'] ?? '-') ?></td>
        <td><?= badge($st) ?></td>

        <td>
          <?php if ($st === 'pending'): ?>
            <!-- ✅ Only show approve/reject when pending -->
            <form method="post" style="display:inline;">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn primary" name="action" value="approve" type="submit">Approve</button>
            </form>

            <form method="post" style="display:inline;">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn danger" name="action" value="reject" type="submit">Reject</button>
            </form>

          <?php else: ?>
            <!-- ✅ Show trash/delete when published or rejected -->
            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this article permanently?');">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn danger" name="action" value="delete" type="submit">🗑 Delete</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php
$content_html = ob_get_clean();
include __DIR__ . '/../panel/_layout.php';
