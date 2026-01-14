<?php
require_once __DIR__ . '/../panel/_guard.php';
require_admin();
require_once __DIR__ . '/../config.php';

function flash_take(string $key): string
{
    if (empty($_SESSION[$key])) {
        return '';
    }
    $v = (string)$_SESSION[$key];
    unset($_SESSION[$key]);
    return $v;
}

function normalize_slug(string $slug): string
{
    $slug = strtolower(trim($slug));
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
    $slug = trim((string)$slug, '-');
    return $slug;
}

function slug_from_name(string $name): string
{
    return normalize_slug($name);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'add');

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['flash_err'] = 'Kategori e pavlefshme.';
            header('Location: categories.php');
            exit;
        }

        $stmt = $mysqli->prepare('DELETE FROM categories WHERE id = ?');
        if (!$stmt) {
            $_SESSION['flash_err'] = 'SQL error (delete category): ' . $mysqli->error;
            header('Location: categories.php');
            exit;
        }
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $affected = $stmt->affected_rows;
        $err = $stmt->error;
        $stmt->close();

        if (!$ok) {
            $_SESSION['flash_err'] = 'Nuk u fshi kategoria. ' . ($err ? ('Arsye: ' . $err) : '');
        } elseif ($affected < 1) {
            $_SESSION['flash_err'] = 'Kategoria nuk u gjet.';
        } else {
            $_SESSION['flash_ok'] = 'Kategoria u fshi.';
        }

        header('Location: categories.php');
        exit;
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $slug = trim((string)($_POST['slug'] ?? ''));
    if ($slug === '' && $name !== '') {
        $slug = slug_from_name($name);
    }
    $slug = normalize_slug($slug);

    if ($name === '' || $slug === '') {
        $_SESSION['flash_err'] = 'Plotëso emrin (dhe/ose slug).';
        header('Location: categories.php');
        exit;
    }

    $check = $mysqli->prepare('SELECT id FROM categories WHERE slug = ? LIMIT 1');
    if (!$check) {
        $_SESSION['flash_err'] = 'SQL error (check category): ' . $mysqli->error;
        header('Location: categories.php');
        exit;
    }
    $check->bind_param('s', $slug);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    $check->close();

    if ($exists) {
        $_SESSION['flash_err'] = 'Kjo kategori ekziston tashmë (slug: ' . $slug . ').';
        header('Location: categories.php');
        exit;
    }

    $stmt = $mysqli->prepare('INSERT INTO categories (name, slug) VALUES (?, ?)');
    if (!$stmt) {
        $_SESSION['flash_err'] = 'SQL error (insert category): ' . $mysqli->error;
        header('Location: categories.php');
        exit;
    }
    $stmt->bind_param('ss', $name, $slug);
    $stmt->execute();
    $stmt->close();

    $_SESSION['flash_ok'] = 'Kategoria u shtua.';
    header('Location: categories.php');
    exit;
}

$res = $mysqli->query('SELECT id, name, slug FROM categories ORDER BY id DESC');
$cats = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$page_title = 'Categories';
$sidebar_file = __DIR__ . '/../panel/_sidebar_admin.php';

$ok_msg = flash_take('flash_ok');
$err_msg = flash_take('flash_err');

ob_start();
?>

<?php if ($ok_msg): ?>
  <div class="alert success"><?= h($ok_msg) ?></div>
<?php endif; ?>

<?php if ($err_msg): ?>
  <div class="alert error"><?= h($err_msg) ?></div>
<?php endif; ?>

<div class="form-bar">
  <form method="post" class="form-inline">
    <input type="hidden" name="action" value="add">

    <div class="field">
      <div class="k">Name</div>
      <input name="name" required class="control w-220">
    </div>

    <div class="field">
      <div class="k">Slug</div>
      <input name="slug" placeholder="(optional)" class="control w-220">
    </div>

    <button class="btn primary" type="submit">Add Category</button>
  </form>
</div>

<table>
  <thead>
    <tr>
      <th class="col-id">ID</th>
      <th>Name</th>
      <th>Slug</th>
      <th class="col-actions">Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($cats as $c): ?>
      <tr>
        <td><?= (int)$c['id'] ?></td>
        <td><?= h((string)$c['name']) ?></td>
        <td><?= h((string)$c['slug']) ?></td>
        <td>
          <form method="post" class="form-reset" onsubmit="return confirm('Delete this category?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button class="btn danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>

    <?php if (!$cats): ?>
      <tr>
        <td colspan="4" class="muted">No categories yet.</td>
      </tr>
    <?php endif; ?>
  </tbody>
</table>

<?php
$content_html = ob_get_clean();
include __DIR__ . '/../panel/_layout.php';
