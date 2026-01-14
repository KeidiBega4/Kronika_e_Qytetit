<?php
require_once __DIR__ . '/../panel/_guard.php';
require_admin();
require_once __DIR__ . '/../config.php';

function flash_set(string $k, string $v): void { $_SESSION[$k] = $v; }
function flash_take(string $k): string {
    if (empty($_SESSION[$k])) return '';
    $v = (string)$_SESSION[$k];
    unset($_SESSION[$k]);
    return $v;
}

function count_admins(mysqli $mysqli): int {
    $res = $mysqli->query("SELECT COUNT(*) AS c FROM users WHERE role='admin'");
    $row = $res ? $res->fetch_assoc() : null;
    return (int)($row['c'] ?? 0);
}

/**
 * Make a safe username from email/name and ensure uniqueness in DB.
 */
function make_unique_username(mysqli $mysqli, string $preferred, string $email): string {
    $u = trim($preferred);

    if ($u === '') {
        $u = explode('@', $email)[0] ?? 'user';
    }

    $u = strtolower($u);
    // keep letters, numbers, underscore, dot
    $u = preg_replace('/[^a-z0-9._]+/', '_', $u);
    $u = trim($u, '._');

    if ($u === '') $u = 'user';

    // Ensure unique: try u, u_2, u_3...
    $base = $u;
    for ($i = 1; $i <= 50; $i++) {
        $try = ($i === 1) ? $base : ($base . '_' . $i);

        $chk = $mysqli->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        if (!$chk) return $try; // fallback if prepare fails
        $chk->bind_param("s", $try);
        $chk->execute();
        $exists = $chk->get_result()->fetch_assoc();
        $chk->close();

        if (!$exists) return $try;
    }

    // last fallback
    return $base . '_' . time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'add') {
        $username = trim((string)($_POST['username'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role = trim((string)($_POST['role'] ?? 'journalist'));
        $is_verified = (int)($_POST['is_verified'] ?? 1);

        if ($email === '' || $password === '') {
            flash_set('flash_err', 'Plotëso email dhe password.');
            header('Location: users.php'); exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('flash_err', 'Email i pavlefshëm.');
            header('Location: users.php'); exit;
        }
        if (!in_array($role, ['admin', 'journalist'], true)) {
            flash_set('flash_err', 'Roli i pavlefshëm.');
            header('Location: users.php'); exit;
        }
        if (strlen($password) < 6) {
            flash_set('flash_err', 'Password duhet të ketë të paktën 6 karaktere.');
            header('Location: users.php'); exit;
        }

        if ($name === '') {
            $name = explode('@', $email)[0] ?: 'User';
        }

        // Email must be unique
        $chk = $mysqli->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        if (!$chk) {
            flash_set('flash_err', 'SQL error: ' . $mysqli->error);
            header('Location: users.php'); exit;
        }
        $chk->bind_param("s", $email);
        $chk->execute();
        $exists = $chk->get_result()->fetch_assoc();
        $chk->close();

        if ($exists) {
            flash_set('flash_err', 'Ky email ekziston tashmë.');
            header('Location: users.php'); exit;
        }

        // Build a valid unique username (required by DB)
        $username = make_unique_username($mysqli, $username, $email);

        $hash = password_hash($password, PASSWORD_DEFAULT);

        // ✅ include username in INSERT
        $ins = $mysqli->prepare("INSERT INTO users (username, name, email, password_hash, role, is_verified) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$ins) {
            flash_set('flash_err', 'SQL error: ' . $mysqli->error);
            header('Location: users.php'); exit;
        }
        $ins->bind_param("sssssi", $username, $name, $email, $hash, $role, $is_verified);
        $ok = $ins->execute();
        $err = $ins->error;
        $ins->close();

        if (!$ok) {
            flash_set('flash_err', 'Nuk u krijua user-i. ' . ($err ? ('Arsye: ' . $err) : ''));
        } else {
            flash_set('flash_ok', 'User u krijua me sukses.');
        }

        header('Location: users.php'); exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash_set('flash_err', 'User i pavlefshëm.');
            header('Location: users.php'); exit;
        }

        if ($id === current_user_id()) {
            flash_set('flash_err', 'Nuk mund të fshish veten.');
            header('Location: users.php'); exit;
        }

        $stmt = $mysqli->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        if (!$stmt) {
            flash_set('flash_err', 'SQL error: ' . $mysqli->error);
            header('Location: users.php'); exit;
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            flash_set('flash_err', 'User nuk u gjet.');
            header('Location: users.php'); exit;
        }

        if (($row['role'] ?? '') === 'admin' && count_admins($mysqli) <= 1) {
            flash_set('flash_err', 'Nuk mund të fshish adminin e fundit.');
            header('Location: users.php'); exit;
        }

        $del = $mysqli->prepare("DELETE FROM users WHERE id = ?");
        if (!$del) {
            flash_set('flash_err', 'SQL error: ' . $mysqli->error);
            header('Location: users.php'); exit;
        }
        $del->bind_param("i", $id);
        $ok = $del->execute();
        $err = $del->error;
        $aff = $del->affected_rows;
        $del->close();

        if (!$ok) {
            flash_set('flash_err', 'Nuk u fshi user-i. ' . ($err ? ('Arsye: ' . $err) : ''));
        } elseif ($aff < 1) {
            flash_set('flash_err', 'User nuk u gjet.');
        } else {
            flash_set('flash_ok', 'User u fshi me sukses.');
        }

        header('Location: users.php'); exit;
    }

    flash_set('flash_err', 'Veprim i panjohur.');
    header('Location: users.php'); exit;
}

// ✅ include username in list view
$res = $mysqli->query("SELECT id, username, name, email, role, is_verified FROM users ORDER BY id DESC LIMIT 500");
$users = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$page_title = "Staff / Users";
$sidebar_file = __DIR__ . '/../panel/_sidebar_admin.php';

$ok_msg  = flash_take('flash_ok');
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
      <div class="k">Username</div>
      <input name="username" placeholder="(optional)" class="control w-200">
    </div>

    <div class="field">
      <div class="k">Name</div>
      <input name="name" placeholder="(optional)" class="control w-200">
    </div>

    <div class="field">
      <div class="k">Email</div>
      <input type="email" name="email" required class="control w-240">
    </div>

    <div class="field">
      <div class="k">Password</div>
      <input type="password" name="password" required class="control">
    </div>

    <div class="field">
      <div class="k">Role</div>
      <select name="role" class="control w-160">
        <option value="journalist">journalist</option>
        <option value="admin">admin</option>
      </select>
    </div>

    <div class="field">
      <div class="k">Verified</div>
      <select name="is_verified" class="control w-130">
        <option value="1" selected>Yes</option>
        <option value="0">No</option>
      </select>
    </div>

    <button class="btn primary" type="submit">Create</button>
  </form>
</div>

<table>
  <thead>
    <tr>
      <th class="col-id-sm">ID</th>
      <th>Username</th>
      <th>Name</th>
      <th>Email</th>
      <th class="col-role">Role</th>
      <th class="col-verified">Verified</th>
      <th class="col-actions">Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= (int)$u['id'] ?></td>
        <td><?= h((string)($u['username'] ?? '')) ?></td>
        <td><?= h((string)($u['name'] ?? '')) ?></td>
        <td><?= h((string)($u['email'] ?? '')) ?></td>
        <td><?= h((string)($u['role'] ?? '')) ?></td>
        <td><?= ((int)($u['is_verified'] ?? 0) === 1) ? 'Yes' : 'No' ?></td>
        <td>
          <?php if ((int)$u['id'] === current_user_id()): ?>
            <span class="you-tag">(you)</span>
          <?php else: ?>
            <form method="post" class="form-reset" onsubmit="return confirm('Delete this user?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <button class="btn danger" type="submit">Delete</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>

    <?php if (!$users): ?>
      <tr><td colspan="7" class="muted">No users found.</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<?php
$content_html = ob_get_clean();
include __DIR__ . '/../panel/_layout.php';
