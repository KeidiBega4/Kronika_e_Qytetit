<?php
// Email-only registration step.
// User enters email -> we send verification link.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$msg = $_SESSION['flash_msg'] ?? '';
$err = $_SESSION['flash_err'] ?? '';
unset($_SESSION['flash_msg'], $_SESSION['flash_err']);
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <title>Kronika e Qytetit</title>
    <link rel="stylesheet" href="../css/register.css">
</head>

<body class="register-page">
    <div class="register-container">
        <h2>Regjistrohu</h2>

        <?php if ($err): ?>
            <p style="color:#ff4d4d; margin-bottom:12px;"><?php echo htmlspecialchars($err); ?></p>
        <?php endif; ?>

        <?php if ($msg): ?>
            <p style="color:#7CFC00; margin-bottom:12px;"><?php echo htmlspecialchars($msg); ?></p>
        <?php endif; ?>

        <form action="send_verification.php" method="POST">
            <div class="input-group">
                <input type="email" id="email" name="email" placeholder="Email address" required>
            </div>
            <button type="submit" class="register-btn">Dërgo linkun</button>
        </form>

        <a href="index.php" class="back-home">Kthehu te faqja kryesore</a>
    </div>
</body>
</html>
