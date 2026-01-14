<?php
require_once __DIR__ . '/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$flash_err = $_SESSION['flash_err'] ?? '';
unset($_SESSION['flash_err']);

$errors = [];

/**
 * Redirect helper based on role
 */
function redirect_by_role(): void
{
    $role = $_SESSION['user_role'] ?? 'user';

    if ($role === 'admin') {
        header("Location: admin/dashboard.php");
        exit;
    }
    if ($role === 'journalist') {
        header("Location: journalist/dashboard.php");
        exit;
    }

    header("Location: index.php");
    exit;
}

/**
 * If already logged in, go to the correct dashboard/home
 */
if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
    redirect_by_role();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Ju lutem plotësoni email dhe fjalëkalimin.';
    } else {
        $user = find_user_by_email($email);

        if (!$user) {
            $errors[] = 'Email ose fjalëkalim gabim.';
        } else {
            if (isset($user['is_verified']) && (int)$user['is_verified'] !== 1) {
                $errors[] = 'Ju lutem verifikoni email-in përpara se të hyni.';
            } elseif (!password_verify($password, $user['password_hash'])) {
                $errors[] = 'Email ose fjalëkalim gabim.';
            } else {
                login_user($user);

                // ✅ Send them where they belong (admin/journalist/home)
                redirect_by_role();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login - Kronika e Qytetit</title>

    <link rel="stylesheet" href="../css/login.css">
</head>
<body class="register-page">

    <div class="register-container">
        <h2>Hyr</h2>

        <?php if ($flash_err): ?>
            <div class="error-message">
                <p><?php echo htmlspecialchars($flash_err); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <?php foreach ($errors as $e): ?>
                    <p><?php echo htmlspecialchars($e); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="input-group">
                <input type="email" name="email" placeholder="Email address" required>
            </div>

            <div class="input-group">
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <button type="submit" class="register-btn">Hyr</button>
        </form>

        <div class="auth-links">
            <a href="index.php" class="back-home">Kthehu te faqja kryesore</a>
        </div>
    </div>

</body>
