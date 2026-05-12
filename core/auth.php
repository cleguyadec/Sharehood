<?php
// ═══════════════════════════════════════════════════════════
//  Panneau Vivant — Session, CSRF, authentification
// ═══════════════════════════════════════════════════════════

function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfVerify(): bool
{
    return hash_equals(
        $_SESSION['csrf_token'] ?? '',
        $_POST['csrf_token']    ?? ''
    );
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '">';
}

function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = getDB()->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function requireAuth(): array
{
    $user = currentUser();
    if (!$user) {
        redirect('?action=login');
    }
    return $user;
}

function requireAdmin(): array
{
    $user = requireAuth();
    if ($user['role'] !== 'admin') {
        redirect('?action=board');
    }
    return $user;
}

function isLoginLocked(string $id): bool
{
    $cutoff = date('Y-m-d H:i:s', time() - LOGIN_LOCKOUT_MINUTES * 60);
    $stmt = getDB()->prepare(
        'SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND attempted_at > ?'
    );
    $stmt->execute([$id, $cutoff]);
    return (int) $stmt->fetchColumn() >= MAX_LOGIN_ATTEMPTS;
}

function recordLoginAttempt(string $id): void
{
    $db = getDB();
    $db->prepare('INSERT INTO login_attempts (identifier) VALUES (?)')->execute([$id]);
    $db->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')
       ->execute([date('Y-m-d H:i:s', time() - 86400)]);
}
