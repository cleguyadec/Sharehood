<?php
// ═══════════════════════════════════════════════════════════
//  Module Auth — Fonctions métier
// ═══════════════════════════════════════════════════════════

function doLogin(string $name, string $password): string
{
    if (isLoginLocked($name)) {
        return 'Trop de tentatives. Réessayez dans ' . LOGIN_LOCKOUT_MINUTES . ' minutes.';
    }
    $stmt = getDB()->prepare('SELECT * FROM users WHERE display_name = ? AND is_active = 1');
    $stmt->execute([$name]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        recordLoginAttempt($name);
        return 'Nom ou mot de passe incorrect.';
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    getDB()->prepare('UPDATE users SET last_login = datetime("now") WHERE id = ?')
           ->execute([$user['id']]);
    return '';
}

function doLogout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    redirect('?action=login');
}

function doRegister(string $name, string $pw, string $invite, string $household, bool $gdpr): string
{
    if (!$gdpr) {
        return 'Vous devez accepter la politique de confidentialité.';
    }
    if ($invite !== INVITE_CODE) {
        return 'Code d\'invitation incorrect.';
    }
    if (mb_strlen($name) < 2 || mb_strlen($name) > 50) {
        return 'Prénom/pseudo : entre 2 et 50 caractères.';
    }
    if (strlen($pw) < 8) {
        return 'Mot de passe : 8 caractères minimum.';
    }

    $db   = getDB();
    $chk  = $db->prepare('SELECT id FROM users WHERE display_name = ?');
    $chk->execute([$name]);
    if ($chk->fetch()) {
        return 'Ce nom est déjà utilisé.';
    }

    $count = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $role  = ($count === 0) ? 'admin' : 'member';
    $hash  = password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]);

    $db->prepare('
        INSERT INTO users (display_name, password_hash, role, household, gdpr_consent_at)
        VALUES (?, ?, ?, ?, datetime("now"))
    ')->execute([$name, $hash, $role, $household ?: null]);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $db->lastInsertId();
    return '';
}

function changePassword(array $user, string $old, string $new): string
{
    if (!password_verify($old, $user['password_hash'])) {
        return 'Mot de passe actuel incorrect.';
    }
    if (strlen($new) < 8) {
        return 'Nouveau mot de passe : 8 caractères minimum.';
    }
    $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
    getDB()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
           ->execute([$hash, $user['id']]);
    return '';
}

function adminResetPassword(int $userId, string $newPassword): string
{
    if ($userId <= 0) return 'Utilisateur introuvable.';
    if (strlen($newPassword) < 8) return 'Nouveau mot de passe : 8 caractères minimum.';
    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    getDB()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);
    return '';
}

function createPasswordResetToken(string $displayName): string
{
    $db   = getDB();
    $stmt = $db->prepare('SELECT id FROM users WHERE display_name = ? AND is_active = 1');
    $stmt->execute([$displayName]);
    $row = $stmt->fetch();
    if (!$row) return '';
    $db->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')->execute([$row['id']]);
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600);
    $db->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)')
       ->execute([$row['id'], $token, $expires]);
    return $token;
}

function adminGenerateResetToken(int $userId): string
{
    $db   = getDB();
    $stmt = $db->prepare('SELECT id FROM users WHERE id = ? AND is_active = 1');
    $stmt->execute([$userId]);
    if (!$stmt->fetch()) return '';
    $db->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')->execute([$userId]);
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600);
    $db->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)')
       ->execute([$userId, $token, $expires]);
    return $token;
}

function validatePasswordResetToken(string $token): ?array
{
    if (strlen($token) !== 64) return null;
    $stmt = getDB()->prepare("
        SELECT t.id, t.user_id, u.display_name
        FROM   password_reset_tokens t
        JOIN   users u ON t.user_id = u.id
        WHERE  t.token_hash = ? AND t.used_at IS NULL AND t.expires_at > datetime('now')
    ");
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

function consumePasswordResetToken(string $token, string $newPassword): string
{
    if (strlen($newPassword) < 8) return 'Nouveau mot de passe : 8 caractères minimum.';
    $row = validatePasswordResetToken($token);
    if (!$row) return 'Ce lien est invalide ou expiré.';
    $db = getDB();
    $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
       ->execute([password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]), $row['user_id']]);
    $db->prepare("UPDATE password_reset_tokens SET used_at = datetime('now') WHERE token_hash = ?")
       ->execute([$token]);
    return '';
}

function getPendingResetRequests(): array
{
    $stmt = getDB()->query("
        SELECT t.user_id, t.token_hash AS token, t.created_at, t.expires_at, u.display_name
        FROM   password_reset_tokens t
        JOIN   users u ON t.user_id = u.id
        WHERE  t.used_at IS NULL AND t.expires_at > datetime('now')
        ORDER  BY t.created_at DESC
    ");
    return $stmt->fetchAll();
}

function cleanExpiredResetTokens(): void
{
    getDB()->exec("DELETE FROM password_reset_tokens WHERE expires_at <= datetime('now') OR used_at IS NOT NULL");
}
