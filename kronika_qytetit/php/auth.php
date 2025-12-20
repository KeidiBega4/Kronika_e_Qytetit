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
    $user = $res->fetch_assoc();
    $stmt->close();

    return $user ?: null;
}


function login_user(array $user): void
{
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'] ?? '';
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'] ?? 'user';
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    session_destroy();
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user_name(): string
{
    return $_SESSION['user_name'] ?? '';
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}
?>
