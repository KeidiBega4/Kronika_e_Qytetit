<?php
require_once __DIR__ . '/../panel/_guard.php';
require_journalist();
require_once __DIR__ . '/../config.php';

$page_title = "Change Password";
$sidebar_file = __DIR__ . '/_sidebar_journalist.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$errors = [];
$success = '';

$stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
  http_response_code(404);
  die("User not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $current = (string)($_POST['current_password'] ?? '');
  $new     = (string)($_POST['new_password'] ?? '');
  $confirm = (string)($_POST['confirm_password'] ?? '');

  if ($current === '' || $new === '' || $confirm === '') {
    $errors[] = 'All fields are required.';
  } elseif (!password_verify($current, (string)$user['password_hash'])) {
    $errors[] = 'Current password is incorrect.';
  } elseif (strlen($new) < 8) {
    $errors[] = 'New password must be at least 8 characters.';
  } elseif ($new !== $confirm) {
    $errors[] = 'New passwords do not match.';
  }

  if (empty($errors)) {
    $newHash = password_hash($new, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->bind_param("si", $newHash, $userId);
    $stmt->execute();
    $stmt->close();

    $success = 'Password updated successfully.';
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

<?php if ($success !== ''): ?>
  <div class="jp-alert jp-alert-success">
    <?= h($success) ?>
  </div>
<?php endif; ?>

<form method="POST" class="jp-form-card" style="max-width:600px">
  <div class="jp-form-stack">

    <div>
      <div class="jp-label">Current password</div>
      <input class="jp-input" type="password" name="current_password" required>
    </div>

    <div>
      <div class="jp-label">New password</div>
      <input class="jp-input" type="password" name="new_password" required>
    </div>

    <div>
      <div class="jp-label">Confirm new password</div>
      <input class="jp-input" type="password" name="confirm_password" required>
    </div>

    <div class="jp-actions">
      <button class="jp-btn jp-btn-primary" type="submit">Update password</button>
      <a class="jp-btn jp-btn-outline" href="dashboard.php">Back</a>
    </div>

  </div>
</form>

<?php
$content_html = ob_get_clean();
include __DIR__ . '/../panel/_layout.php';
