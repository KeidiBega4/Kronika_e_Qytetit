<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/smtp_config.php';

require_once __DIR__ . '/PHPMailer-master/PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer-master/PHPMailer-master/src/SMTP.php';
require_once __DIR__ . '/PHPMailer-master/PHPMailer-master/src/Exception.php';


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Show errors while testing (you can remove later)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_err'] = 'Email i pavlefshëm.';
    header('Location: register.php');
    exit;
}

// Avoid revealing whether the user exists
$existing = find_user_by_email($email);

if (!$existing) {
    $rawToken  = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', time() + 30 * 60);

    $sql = "INSERT INTO email_verifications (email, token_hash, expires_at, used_at, created_at)
            VALUES (?, ?, ?, NULL, NOW())
            ON DUPLICATE KEY UPDATE
                token_hash = VALUES(token_hash),
                expires_at = VALUES(expires_at),
                used_at = NULL,
                created_at = NOW()";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        $_SESSION['flash_err'] = 'DB error: ' . $mysqli->error;
        header('Location: register.php');
        exit;
    }

    $stmt->bind_param('sss', $email, $tokenHash, $expiresAt);
    $stmt->execute();
    $stmt->close();

    // Build verification link
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $link   = $scheme . '://' . $host . $dir . '/verify.php?token=' . urlencode($rawToken);

    $subject = 'Verifikim email - Kronika e Qytetit';
    $body    = "Kliko linkun për të vazhduar regjistrimin:\n\n{$link}\n\nLinku skadon për 30 minuta.";

    // Send via Gmail SMTP
    try {
       $mail = new PHPMailer(true);


        // Turn this ON temporarily to see why it fails (prints SMTP errors)
        $mail->SMTPDebug = 2;

        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email);

        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
    } catch (Exception $e) {
        // Show the actual SMTP/PHPMailer error on screen while testing
        exit('Email failed: ' . $e->getMessage());
    }
}

$_SESSION['flash_msg'] = 'Nëse email-i është i vlefshëm, do të marrësh një link verifikimi.';
header('Location: register.php');
exit;
