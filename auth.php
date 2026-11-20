<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function h(?string $text): string {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

function home_for(string $role): string {
    return $role === 'librarian' ? 'librarian_home.php' : 'student_home.php';
}

function login_for(string $role): string {
    return $role === 'librarian' ? 'librarian_login.php' : 'student_login.php';
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid($token): bool {
    return isset($_SESSION['csrf']) && is_string($token)
        && hash_equals($_SESSION['csrf'], $token);
}

function attempt_login(PDO $pdo, string $idNo, string $password, string $role): bool {
    $stmt = $pdo->prepare(
        'SELECT id, id_no, full_name, password_hash, role
           FROM users
          WHERE id_no = ? AND role = ?
          LIMIT 1'
    );
    $stmt->execute([$idNo, $role]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);  
    $_SESSION['user'] = [
        'id'    => (int) $user['id'],
        'id_no' => $user['id_no'],
        'name'  => $user['full_name'],
        'role'  => $user['role'],
    ];
    return true;
}

function require_role(string $role): array {
    if (empty($_SESSION['user'])) {
        header('Location: index.php');
        exit;
    }
    if ($_SESSION['user']['role'] !== $role) {
        http_response_code(403);
        exit('Access denied. This page is for ' . h($role) . 's only.');
    }
    return $_SESSION['user'];
}
