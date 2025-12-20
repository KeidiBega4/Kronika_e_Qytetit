<?php
require_once __DIR__ . '/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = [];

$rawToken = $_GET['token'] ?? ($_POST['token'] ?? '');
if (!is_string($rawToken) || $rawToken === '') {
    $_SESSION['flash_err'] = 'Link verifikimi i pavlefshëm.';
    header('Location: register.php');
    exit;
}

$tokenHash = hash('sha256', $rawToken);

$stmt = $mysqli->prepare(
    "SELECT email, expires_at, used_at
     FROM email_verifications
     WHERE token_hash = ?
     LIMIT 1"
);

if (!$stmt) {
    $_SESSION['flash_err'] = 'Gabim i brendshëm. Provoni sërish.';
    header('Location: register.php');
    exit;
}

$stmt->bind_param('s', $tokenHash);
$stmt->execute();
$res = $stmt->get_result();
$ver = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$ver) {
    $_SESSION['flash_err'] = 'Ky link nuk është i vlefshëm.';
    header('Location: register.php');
    exit;
}

if (!empty($ver['used_at'])) {
    $_SESSION['flash_err'] = 'Ky link është përdorur tashmë.';
    header('Location: register.php');
    exit;
}

$expires = strtotime($ver['expires_at'] ?? '');
if (!$expires || $expires < time()) {
    $_SESSION['flash_err'] = 'Ky link ka skaduar. Dërgo një tjetër.';
    header('Location: register.php');
    exit;
}

$email = $ver['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if ($password === '' || $confirm === '') {
        $errors[] = 'Ju lutem plotësoni të gjitha fushat.';
    } elseif ($password !== $confirm) {
        $errors[] = 'Fjalëkalimet nuk përputhen.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Fjalëkalimi duhet të ketë të paktën 6 karaktere.';
    }

    if (!$errors) {
        $existing = find_user_by_email($email);
        if ($existing) {
            $errors[] = 'Ekziston tashmë një llogari me këtë email.';
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $username = explode('@', $email)[0];
       
        $sql = "INSERT INTO users (email,username, password_hash, is_verified, role, created_at)
                VALUES (?, ?, ?, 1 , 'user', NOW())";

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            $errors[] = 'Gabim i databazës. Provoni sërish.';
        } else {
            $stmt->bind_param('sss', $email,$username,
            
            $hash);
            if ($stmt->execute()) {
                $userId = $stmt->insert_id;
                $stmt->close();

                $upd = $mysqli->prepare("UPDATE email_verifications SET used_at = NOW() WHERE token_hash = ?");
                if ($upd) {
                    $upd->bind_param('s', $tokenHash);
                    $upd->execute();
                    $upd->close();
                }

                login_user([
                    'id'    => $userId,
                    'name'  => '',
                    'email' => $email,
                    'role'  => 'user'
                ]);

                header('Location: lajme.php');
                exit;
            }

            $stmt->close();
            $errors[] = 'Nuk u krijua dot llogaria. Provoni sërish.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <title>Vendos fjalëkalimin</title>
    <link rel="stylesheet" href="../css/register.css">
</head>

<body class="register-page">
    <div class="register-container">
        <h2>Vendos fjalëkalimin</h2>

        <p style="opacity:0.85; margin-bottom:12px;">Email: <strong><?php echo htmlspecialchars($email); ?></strong></p>

        <?php if ($errors): ?>
            <div style="color:#ff4d4d; margin-bottom:12px;">
                <?php foreach ($errors as $e): ?>
                    <div><?php echo htmlspecialchars($e); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="set_password.php" method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($rawToken); ?>">

            <div class="input-group">
                <input type="password" name="password" placeholder="Fjalëkalimi" required>
            </div>

            <div class="input-group">
                <input type="password" name="password_confirm" placeholder="Konfirmo fjalëkalimin" required>
            </div>

            <button type="submit" class="register-btn">Krijo llogarinë</button>
        </form>

        <a href="register.php" class="back-home">Kthehu</a>
    </div>
</body>
</html>
