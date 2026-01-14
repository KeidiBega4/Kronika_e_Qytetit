<?php
require_once __DIR__ . '/../panel/_guard.php';
require_journalist();
require_once __DIR__ . '/../config.php';

$page_title = "Create Article";
$sidebar_file = __DIR__ . '/_sidebar_journalist.php';

$catRes = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
$categories = $catRes ? $catRes->fetch_all(MYSQLI_ASSOC) : [];

$errors = [];

$title = '';
$body  = '';
$category_id = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = trim($_POST['title'] ?? '');
  $body  = trim($_POST['content'] ?? '');
  $category_id = (int)($_POST['category_id'] ?? 0);

  $imagePath = null;

  if ($title === '' || $body === '' || $category_id <= 0) {
    $errors[] = 'Please fill all required fields.';
  }

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
          $imagePath = '../img/uploads/' . $filename;
        } else {
          $errors[] = 'Image upload failed.';
        }
      }
    }
  }

  if (empty($errors)) {
    $stmt = $conn->prepare(
      "INSERT INTO articles (title, content, category_id, author_id, image_path, status, created_at)
       VALUES (?, ?, ?, ?, ?, 'pending', NOW())"
    );

    $authorId = (int)($_SESSION['user_id'] ?? 0);
    $stmt->bind_param("ssiis", $title, $body, $category_id, $authorId, $imagePath);
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

    <div>
      <div class="jp-label">Main Image (optional)</div>
      <input type="file" name="image" accept="image/*">
    </div>

    <div>
      <div class="jp-label">Content *</div>
      <textarea class="jp-textarea" name="content" rows="12" required><?= h($body) ?></textarea>
    </div>

    <div class="jp-actions">
      <button class="jp-btn jp-btn-primary" type="submit">Submit for review</button>
      <a class="jp-btn jp-btn-outline" href="my_articles.php">Cancel</a>
    </div>

  </div>
</form>

<?php
$content_html = ob_get_clean();
include __DIR__ . '/../panel/_layout.php';
