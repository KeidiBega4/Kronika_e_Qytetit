<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rawToken = $_GET['token'] ?? '';
if (!is_string($rawToken) || $rawToken === '') {
    $_SESSION['flash_err'] = 'Link verifikimi i pavlefshëm.';
    header('Location: register.php');
    exit;
}

$tokenHash = hash('sha256', $rawToken);

$sql = "SELECT email, expires_at, used_at
        FROM email_verifications
        WHERE token_hash = ?
        LIMIT 1";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    $_SESSION['flash_err'] = 'Gabim i brendshëm. Provoni sërish.';
    header('Location: register.php');
    exit;
}

$stmt->bind_param('s', $tokenHash);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    $_SESSION['flash_err'] = 'Ky link nuk është i vlefshëm.';
    header('Location: register.php');
    exit;
}

if (!empty($row['used_at'])) {
    $_SESSION['flash_err'] = 'Ky link është përdorur tashmë.';
    header('Location: register.php');
    exit;
}

$expires = strtotime($row['expires_at'] ?? '');
if (!$expires || $expires < time()) {
    $_SESSION['flash_err'] = 'Ky link ka skaduar. Dërgo një tjetër.';
    header('Location: register.php');
    exit;
}

header('Location: set_password.php?token=' . urlencode($rawToken));
exit;
