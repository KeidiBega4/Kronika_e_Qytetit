<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';


function find_user_by_email(string $email): ?array
{
    global $mysqli;
    $sql = "SELECT id, email, name, username, password_hash, is_verified, role
            FROM users
            WHERE email = ?
            LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) return null;

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function login_user(array $user): void
{
    // regenerate session id for security
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_email'] = $user['email'] ?? '';
    $_SESSION['user_name'] = $user['name'] ?? ($user['username'] ?? '');
    $_SESSION['user_role'] = $user['role'] ?? 'reader';
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
    }
    session_destroy();
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
}

function current_user_id(): int {
    return (int)($_SESSION['user_id'] ?? ($_SESSION['uid'] ?? 0));
}

function current_user_role(): string
{
    return (string)($_SESSION['user_role'] ?? 'reader');
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ../login.php');
        exit;
    }
}

function require_role(array $roles): void
{
    require_login();
    $role = current_user_role();
    if (!in_array($role, $roles, true)) {
        http_response_code(403);
        echo "403 Forbidden";
        exit;
    }
}
