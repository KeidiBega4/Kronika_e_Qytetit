<?php
require_once __DIR__ . '/../panel/_guard.php';
require_journalist();
require_once __DIR__ . '/../config.php';

$page_title = "Edit Article";
$sidebar_file = __DIR__ . '/../panel/_sidebar_journalist.php';

$id  = (int)($_GET['id'] ?? 0);
$uid = (int)($_SESSION['user_id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM articles WHERE id=? AND author_id=? LIMIT 1");
$stmt->bind_param("ii", $id, $uid);
$stmt->execute();
$article = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$article) {
  http_response_code(404);
  die("Article not found.");
}

if (($article['status'] ?? '') === 'published') {
  http_response_code(403);
  die("Published articles cannot be edited.");
}

$catRes = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
$categories = $catRes ? $catRes->fetch_all(MYSQLI_ASSOC) : [];

$errors = [];

$title = (string)($article['title'] ?? '');
$body  = (string)($article['content'] ?? '');
$category_id = (int)($article['category_id'] ?? 0);
$imagePath   = $article['image_path'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = trim($_POST['title'] ?? '');
  $body  = trim($_POST['content'] ?? '');
  $category_id = (int)($_POST['category_id'] ?? 0);

  if ($title === '' || $body === '' || $category_id <= 0) {
    $errors[] = 'Please fill all required fields.';
  }

  // Optional image update
  if (!empty($_FILES['image']['name'])) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $tmp = $_FILES['image']['tmp_name'] ?? '';

    if ($tmp === '' || !is_uploaded_file($tmp)) {
      $errors[] = 'Image upload failed.';
    } else {
      $fileType = mime_content_type($tmp);
      if (!in_array($fileType, $allowedTypes, true)) {
        $errors[] = 'Only JPG, PNG, WEBP allowed.';
      } else {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $filename = uniqid('img_', true) . '.' . $ext;

        $uploadDir = __DIR__ . '/../../img/uploads/';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);

        $targetPath = $uploadDir . $filename;
        if (move_uploaded_file($tmp, $targetPath)) {
          $imagePath = 'img/uploads/' . $filename;
        } else {
          $errors[] = 'Image upload failed.';
        }
      }
    }
  }

  if (empty($errors)) {
    // Put back to pending after edits (so admin can re-approve)
    $stmt = $conn->prepare(
      "UPDATE articles
       SET title=?, content=?, category_id=?, image_path=?, status='pending'
       WHERE id=? AND author_id=?"
    );
    $stmt->bind_param("ssissi", $title, $body, $category_id, $imagePath, $id, $uid);
    $stmt->execute();
    $stmt->close();

    header("Location: my_articles.php");
    exit;
  }
}

ob_start();
?>

<?php if (!empty($errors)): ?>
  <div class="jp-alert jp-alert-error">
    <?php foreach ($errors as $e): ?>
      <div><?= h($e) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="jp-form-card">
  <div class="jp-form-stack">

    <div>
      <div class="jp-label">Title *</div>
      <input class="jp-input" type="text" name="title" value="<?= h($title) ?>" required>
    </div>

    <div>
      <div class="jp-label">Category *</div>
      <select class="jp-select" name="category_id" required>
        <option value="0">Select...</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $category_id === (int)$c['id'] ? 'selected' : '' ?>>
            <?= h($c['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <?php if (!empty($imagePath)): ?>
      <div>
        <div class="jp-label">Current image</div>
        <img class="jp-thumb" src="../../<?= h($imagePath) ?>" alt="">
      </div>
    <?php endif; ?>

    <div>
      <div class="jp-label">Replace image (optional)</div>
      <input type="file" name="image" accept="image/*">
    </div>

    <div>
      <div class="jp-label">Content *</div>
      <textarea class="jp-textarea" name="content" rows="12" required><?= h($body) ?></textarea>
    </div>

    <div class="jp-actions">
      <button class="jp-btn jp-btn-primary" type="submit">Save changes</button>
      <a class="jp-btn jp-btn-outline" href="my_articles.php">Cancel</a>
    </div>

  </div>
</form>

<?php
$content_html = ob_get_clean();
include __DIR__ . '/../panel/_layout.php';
