<?php
// ═══════════════════════════════════════════════════════════
//  Panneau Vivant — Fonctions métier
// ═══════════════════════════════════════════════════════════

require_once __DIR__ . '/config.php';

// ──────────────────────────────────────────────
//  BASE DE DONNÉES
// ──────────────────────────────────────────────

function getDB(): PDO
{
    static $db = null;
    if ($db === null) {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('PRAGMA journal_mode = WAL');
        $db->exec('PRAGMA foreign_keys = ON');
    }
    return $db;
}

function initDB(): void
{
    $db = getDB();
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            display_name     TEXT    NOT NULL,
            password_hash    TEXT    NOT NULL,
            role             TEXT    NOT NULL DEFAULT 'member',
            household        TEXT,
            created_at       TEXT    NOT NULL DEFAULT (datetime('now')),
            last_login       TEXT,
            is_active        INTEGER NOT NULL DEFAULT 1,
            gdpr_consent_at  TEXT
        );

        CREATE TABLE IF NOT EXISTS cards (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            column_id    INTEGER NOT NULL DEFAULT 0,
            tag          TEXT    NOT NULL DEFAULT 'autre',
            title        TEXT    NOT NULL,
            body         TEXT,
            author_id    INTEGER,
            audience     TEXT    DEFAULT 'adultes',
            event_date   TEXT,
            status       TEXT    NOT NULL DEFAULT 'idea',
            created_at   TEXT    NOT NULL DEFAULT (datetime('now')),
            updated_at   TEXT,
            FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS interests (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            card_id    INTEGER NOT NULL,
            user_id    INTEGER NOT NULL,
            created_at TEXT    NOT NULL DEFAULT (datetime('now')),
            UNIQUE(card_id, user_id),
            FOREIGN KEY (card_id)  REFERENCES cards(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE
        );

        -- Prêt-o-thèque
        CREATE TABLE IF NOT EXISTS library_items (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            category      TEXT    NOT NULL DEFAULT 'autre',
            title         TEXT    NOT NULL,
            subtitle      TEXT,
            description   TEXT,
            owner_id      INTEGER,
            available     INTEGER NOT NULL DEFAULT 1,
            condition     TEXT    NOT NULL DEFAULT 'ok',
            url           TEXT,
            game_duration TEXT,
            age_min       INTEGER,
            player_min    INTEGER,
            player_max    INTEGER,
            book_genre    TEXT,
            created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS loans (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            item_id      INTEGER NOT NULL,
            borrower_id  INTEGER NOT NULL,
            loaned_at    TEXT    NOT NULL DEFAULT (datetime('now')),
            due_date     TEXT,
            returned_at  TEXT,
            notes        TEXT,
            FOREIGN KEY (item_id)     REFERENCES library_items(id) ON DELETE CASCADE,
            FOREIGN KEY (borrower_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS date_polls (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            card_id       INTEGER NOT NULL,
            proposed_date TEXT    NOT NULL,
            created_by    INTEGER,
            created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (card_id)    REFERENCES cards(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS date_poll_votes (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            poll_id    INTEGER NOT NULL,
            user_id    INTEGER NOT NULL,
            created_at TEXT    NOT NULL DEFAULT (datetime('now')),
            UNIQUE(poll_id, user_id),
            FOREIGN KEY (poll_id) REFERENCES date_polls(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id)      ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS presences (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            card_id    INTEGER NOT NULL,
            user_id    INTEGER NOT NULL,
            attending  INTEGER NOT NULL DEFAULT 1,
            created_at TEXT    NOT NULL DEFAULT (datetime('now')),
            UNIQUE(card_id, user_id),
            FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS comments (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            card_id    INTEGER NOT NULL,
            user_id    INTEGER NOT NULL,
            body       TEXT    NOT NULL,
            created_at TEXT    NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        -- Tentatives de connexion (anti-brute force)
        CREATE TABLE IF NOT EXISTS login_attempts (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            identifier   TEXT NOT NULL,
            attempted_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        -- Tokens de réinitialisation de mot de passe
        CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            token_hash TEXT    NOT NULL,
            created_at TEXT    NOT NULL DEFAULT (datetime('now')),
            expires_at TEXT    NOT NULL,
            used_at    TEXT
        );

        -- Achats groupés
        CREATE TABLE IF NOT EXISTS group_orders (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            title       TEXT    NOT NULL,
            description TEXT,
            deadline    TEXT,
            status      TEXT    NOT NULL DEFAULT 'open',
            creator_id  INTEGER,
            created_at  TEXT    NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS group_order_products (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id     INTEGER NOT NULL,
            name         TEXT    NOT NULL,
            unit         TEXT    NOT NULL DEFAULT 'unité',
            unit_price   REAL    NOT NULL DEFAULT 0,
            conditioning REAL,
            created_at   TEXT    NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (order_id) REFERENCES group_orders(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS group_order_requests (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id  INTEGER NOT NULL,
            user_id     INTEGER NOT NULL,
            quantity    REAL    NOT NULL DEFAULT 0,
            paid        INTEGER NOT NULL DEFAULT 0,
            dispatched  INTEGER NOT NULL DEFAULT 0,
            created_at  TEXT    NOT NULL DEFAULT (datetime('now')),
            UNIQUE(product_id, user_id),
            FOREIGN KEY (product_id) REFERENCES group_order_products(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE CASCADE
        );
    ");
}

function migrateDB(): void
{
    $db = getDB();
    try {
        $db->exec("ALTER TABLE cards ADD COLUMN status TEXT NOT NULL DEFAULT 'idea'");
        $db->exec("UPDATE cards SET status = 'planifiee'   WHERE column_id = 1 AND event_date IS NOT NULL AND status = 'idea'");
        $db->exec("UPDATE cards SET status = 'a_planifier' WHERE column_id = 1 AND event_date IS NULL     AND status = 'idea'");
    } catch (\PDOException) { /* colonne déjà présente */ }
    foreach ([
        "ALTER TABLE library_items ADD COLUMN condition     TEXT    NOT NULL DEFAULT 'ok'",
        "ALTER TABLE library_items ADD COLUMN url           TEXT",
        "ALTER TABLE library_items ADD COLUMN game_duration TEXT",
        "ALTER TABLE library_items ADD COLUMN age_min       INTEGER",
        "ALTER TABLE library_items ADD COLUMN player_min    INTEGER",
        "ALTER TABLE library_items ADD COLUMN player_max    INTEGER",
        "ALTER TABLE library_items ADD COLUMN book_genre    TEXT",
    ] as $sql) {
        try { $db->exec($sql); } catch (\PDOException) { /* colonne déjà présente */ }
    }
    // Achats groupés (pour installations existantes)
    foreach ([
        "CREATE TABLE IF NOT EXISTS group_orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, description TEXT,
            deadline TEXT, status TEXT NOT NULL DEFAULT 'open', creator_id INTEGER,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE SET NULL)",
        "CREATE TABLE IF NOT EXISTS group_order_products (
            id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL,
            name TEXT NOT NULL, unit TEXT NOT NULL DEFAULT 'unité', unit_price REAL NOT NULL DEFAULT 0,
            conditioning REAL,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (order_id) REFERENCES group_orders(id) ON DELETE CASCADE)",
        "ALTER TABLE group_order_products ADD COLUMN conditioning REAL",
        "CREATE TABLE IF NOT EXISTS group_order_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL, quantity REAL NOT NULL DEFAULT 0,
            paid INTEGER NOT NULL DEFAULT 0, dispatched INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            UNIQUE(product_id, user_id),
            FOREIGN KEY (product_id) REFERENCES group_order_products(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)",
    ] as $sql) {
        try { $db->exec($sql); } catch (\PDOException) { /* table déjà présente */ }
    }
}

// ──────────────────────────────────────────────
//  SESSION
// ──────────────────────────────────────────────

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

// ──────────────────────────────────────────────
//  CSRF
// ──────────────────────────────────────────────

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

// ──────────────────────────────────────────────
//  AUTHENTIFICATION
// ──────────────────────────────────────────────

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

// ──────────────────────────────────────────────
//  RÉINITIALISATION DE MOT DE PASSE
// ──────────────────────────────────────────────

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

// ──────────────────────────────────────────────
//  CARTES
// ──────────────────────────────────────────────

function getCards(): array
{
    $stmt = getDB()->query('
        SELECT c.*, u.display_name AS author_name
        FROM   cards c
        LEFT JOIN users u ON c.author_id = u.id
        ORDER  BY c.column_id ASC, c.created_at DESC
    ');
    $cols = [0 => [], 1 => [], 2 => []];
    foreach ($stmt->fetchAll() as $card) {
        $cols[(int) $card['column_id']][] = $card;
    }
    return $cols;
}

function getCardInterests(int $cardId): array
{
    $stmt = getDB()->prepare('
        SELECT i.user_id, u.display_name
        FROM   interests i
        JOIN   users u ON i.user_id = u.id
        WHERE  i.card_id = ?
    ');
    $stmt->execute([$cardId]);
    return $stmt->fetchAll();
}

function addCard(array $user, array $data): void
{
    $colId     = max(0, min(2, (int) ($data['column_id'] ?? 0)));
    $eventDate = $data['event_date'] ?: null;
    $status    = 'idea';
    if ($colId === 1) {
        $status = $eventDate ? 'planifiee' : 'a_planifier';
    }
    getDB()->prepare('
        INSERT INTO cards (column_id, tag, title, body, author_id, audience, event_date, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ')->execute([
        $colId,
        $data['tag']      ?? 'autre',
        trim($data['title']),
        trim($data['body']  ?? ''),
        $user['id'],
        $data['audience'] ?? 'adultes',
        $eventDate,
        $status,
    ]);
}

function moveCard(int $id, int $col, array $user): bool
{
    $db   = getDB();
    $stmt = $db->prepare('SELECT author_id FROM cards WHERE id = ?');
    $stmt->execute([$id]);
    $card = $stmt->fetch();
    if (!$card) {
        return false;
    }
    if ($card['author_id'] !== $user['id'] && $user['role'] !== 'admin') {
        return false;
    }
    $newCol = max(0, min(2, $col));
    if ($newCol === 1) {
        $db->prepare('UPDATE cards SET column_id = ?, status = ?, updated_at = datetime("now") WHERE id = ?')
           ->execute([$newCol, 'a_planifier', $id]);
    } else {
        $db->prepare('UPDATE cards SET column_id = ?, updated_at = datetime("now") WHERE id = ?')
           ->execute([$newCol, $id]);
    }
    return true;
}

function deleteCard(int $id, array $user): bool
{
    $db   = getDB();
    $stmt = $db->prepare('SELECT author_id FROM cards WHERE id = ?');
    $stmt->execute([$id]);
    $card = $stmt->fetch();
    if (!$card) {
        return false;
    }
    if ($card['author_id'] !== $user['id'] && $user['role'] !== 'admin') {
        return false;
    }
    $db->prepare('DELETE FROM cards WHERE id = ?')->execute([$id]);
    return true;
}

function toggleInterest(int $cardId, int $userId): bool
{
    $db   = getDB();
    $chk  = $db->prepare('SELECT id FROM interests WHERE card_id = ? AND user_id = ?');
    $chk->execute([$cardId, $userId]);
    if ($chk->fetch()) {
        $db->prepare('DELETE FROM interests WHERE card_id = ? AND user_id = ?')
           ->execute([$cardId, $userId]);
        return false;
    }
    $db->prepare('INSERT INTO interests (card_id, user_id) VALUES (?, ?)')
       ->execute([$cardId, $userId]);
    return true;
}

// ──────────────────────────────────────────────
//  COMMENTAIRES
// ──────────────────────────────────────────────

function getAllComments(): array
{
    $stmt = getDB()->query('
        SELECT c.*, u.display_name AS author_name
        FROM   comments c
        JOIN   users u ON c.user_id = u.id
        ORDER  BY c.card_id ASC, c.created_at ASC
    ');
    $byCard = [];
    foreach ($stmt->fetchAll() as $row) {
        $byCard[(int) $row['card_id']][] = $row;
    }
    return $byCard;
}

function addComment(int $cardId, int $userId, string $body): bool
{
    $body = trim($body);
    if ($body === '') {
        return false;
    }
    getDB()->prepare('INSERT INTO comments (card_id, user_id, body) VALUES (?, ?, ?)')
           ->execute([$cardId, $userId, $body]);
    return true;
}

function deleteComment(int $commentId, array $user): bool
{
    $db   = getDB();
    $stmt = $db->prepare('SELECT user_id FROM comments WHERE id = ?');
    $stmt->execute([$commentId]);
    $c = $stmt->fetch();
    if (!$c) {
        return false;
    }
    if ($c['user_id'] != $user['id'] && $user['role'] !== 'admin') {
        return false;
    }
    $db->prepare('DELETE FROM comments WHERE id = ?')->execute([$commentId]);
    return true;
}

// ──────────────────────────────────────────────
//  SONDAGES DE DATES
// ──────────────────────────────────────────────

function getDatePolls(int $cardId): array
{
    $db    = getDB();
    $polls = $db->prepare('
        SELECT dp.*, u.display_name AS creator_name
        FROM   date_polls dp
        LEFT JOIN users u ON dp.created_by = u.id
        WHERE  dp.card_id = ?
        ORDER  BY dp.proposed_date ASC
    ');
    $polls->execute([$cardId]);
    $result = [];
    foreach ($polls->fetchAll() as $poll) {
        $votes = $db->prepare('
            SELECT dpv.user_id, u.display_name
            FROM   date_poll_votes dpv
            JOIN   users u ON dpv.user_id = u.id
            WHERE  dpv.poll_id = ?
        ');
        $votes->execute([$poll['id']]);
        $poll['votes'] = $votes->fetchAll();
        $result[] = $poll;
    }
    return $result;
}

function addDatePoll(int $cardId, string $date, int $userId): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    $db   = getDB();
    $card = $db->prepare('SELECT id FROM cards WHERE id = ? AND column_id = 1');
    $card->execute([$cardId]);
    if (!$card->fetch()) {
        return false;
    }
    $dup = $db->prepare('SELECT id FROM date_polls WHERE card_id = ? AND proposed_date = ?');
    $dup->execute([$cardId, $date]);
    if ($dup->fetch()) {
        return false;
    }
    $db->prepare('INSERT INTO date_polls (card_id, proposed_date, created_by) VALUES (?, ?, ?)')
       ->execute([$cardId, $date, $userId]);
    return true;
}

function deleteDatePoll(int $pollId, array $user): bool
{
    $db   = getDB();
    $stmt = $db->prepare('
        SELECT dp.created_by, c.author_id
        FROM   date_polls dp
        JOIN   cards c ON dp.card_id = c.id
        WHERE  dp.id = ?
    ');
    $stmt->execute([$pollId]);
    $poll = $stmt->fetch();
    if (!$poll) {
        return false;
    }
    if ($poll['created_by'] != $user['id'] && $poll['author_id'] != $user['id'] && $user['role'] !== 'admin') {
        return false;
    }
    $db->prepare('DELETE FROM date_polls WHERE id = ?')->execute([$pollId]);
    return true;
}

function toggleDatePollVote(int $pollId, int $userId): bool
{
    $db  = getDB();
    $chk = $db->prepare('SELECT id FROM date_poll_votes WHERE poll_id = ? AND user_id = ?');
    $chk->execute([$pollId, $userId]);
    if ($chk->fetch()) {
        $db->prepare('DELETE FROM date_poll_votes WHERE poll_id = ? AND user_id = ?')->execute([$pollId, $userId]);
        return false;
    }
    $db->prepare('INSERT INTO date_poll_votes (poll_id, user_id) VALUES (?, ?)')->execute([$pollId, $userId]);
    return true;
}

function confirmCardDate(int $cardId, string $date, array $user): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    $db   = getDB();
    $stmt = $db->prepare('SELECT author_id, column_id FROM cards WHERE id = ?');
    $stmt->execute([$cardId]);
    $card = $stmt->fetch();
    if (!$card || (int) $card['column_id'] !== 1) {
        return false;
    }
    if ($card['author_id'] != $user['id'] && $user['role'] !== 'admin') {
        return false;
    }
    $db->prepare('UPDATE cards SET event_date = ?, status = ?, updated_at = datetime("now") WHERE id = ?')
       ->execute([$date, 'planifiee', $cardId]);
    return true;
}

function updateCardStatus(int $cardId, string $status, array $user): bool
{
    $allowed = ['a_planifier', 'planifiee', 'annulee', 'reportee'];
    if (!in_array($status, $allowed, true)) {
        return false;
    }
    $db   = getDB();
    $stmt = $db->prepare('SELECT author_id FROM cards WHERE id = ?');
    $stmt->execute([$cardId]);
    $card = $stmt->fetch();
    if (!$card) {
        return false;
    }
    if ($card['author_id'] != $user['id'] && $user['role'] !== 'admin') {
        return false;
    }
    $db->prepare('UPDATE cards SET status = ?, updated_at = datetime("now") WHERE id = ?')
       ->execute([$status, $cardId]);
    return true;
}

// ──────────────────────────────────────────────
//  PRÉSENCES
// ──────────────────────────────────────────────

function getPresences(int $cardId): array
{
    $stmt = getDB()->prepare('
        SELECT p.user_id, p.attending, u.display_name
        FROM   presences p
        JOIN   users u ON p.user_id = u.id
        WHERE  p.card_id = ?
        ORDER  BY p.attending DESC, p.created_at ASC
    ');
    $stmt->execute([$cardId]);
    return $stmt->fetchAll();
}

function togglePresence(int $cardId, int $userId, int $attending): void
{
    $db  = getDB();
    $chk = $db->prepare('SELECT id, attending FROM presences WHERE card_id = ? AND user_id = ?');
    $chk->execute([$cardId, $userId]);
    $existing = $chk->fetch();
    if ($existing) {
        if ((int) $existing['attending'] === $attending) {
            $db->prepare('DELETE FROM presences WHERE card_id = ? AND user_id = ?')->execute([$cardId, $userId]);
        } else {
            $db->prepare('UPDATE presences SET attending = ? WHERE card_id = ? AND user_id = ?')
               ->execute([$attending, $cardId, $userId]);
        }
    } else {
        $db->prepare('INSERT INTO presences (card_id, user_id, attending) VALUES (?, ?, ?)')
           ->execute([$cardId, $userId, $attending]);
    }
}

// ──────────────────────────────────────────────
//  PRÊT-O-THÈQUE
// ──────────────────────────────────────────────

function getLibraryItems(?string $cat = null): array
{
    $db = getDB();
    if ($cat) {
        $stmt = $db->prepare('
            SELECT li.*, u.display_name AS owner_name,
                (SELECT borrower_id FROM loans
                 WHERE item_id = li.id AND returned_at IS NULL LIMIT 1) AS borrower_id,
                (SELECT u2.display_name FROM loans l2
                 JOIN users u2 ON l2.borrower_id = u2.id
                 WHERE l2.item_id = li.id AND l2.returned_at IS NULL LIMIT 1) AS borrower_name,
                (SELECT id FROM loans WHERE item_id = li.id AND returned_at IS NULL LIMIT 1) AS loan_id,
                (SELECT loaned_at FROM loans WHERE item_id = li.id AND returned_at IS NULL LIMIT 1) AS loaned_at,
                (SELECT COALESCE(SUM(CAST(julianday(COALESCE(returned_at, CURRENT_TIMESTAMP)) - julianday(loaned_at) AS INTEGER)), 0) FROM loans WHERE item_id = li.id) AS total_days
            FROM library_items li
            LEFT JOIN users u ON li.owner_id = u.id
            WHERE li.category = ?
            ORDER BY li.title ASC
        ');
        $stmt->execute([$cat]);
    } else {
        $stmt = $db->query('
            SELECT li.*, u.display_name AS owner_name,
                (SELECT borrower_id FROM loans
                 WHERE item_id = li.id AND returned_at IS NULL LIMIT 1) AS borrower_id,
                (SELECT u2.display_name FROM loans l2
                 JOIN users u2 ON l2.borrower_id = u2.id
                 WHERE l2.item_id = li.id AND l2.returned_at IS NULL LIMIT 1) AS borrower_name,
                (SELECT id FROM loans WHERE item_id = li.id AND returned_at IS NULL LIMIT 1) AS loan_id,
                (SELECT loaned_at FROM loans WHERE item_id = li.id AND returned_at IS NULL LIMIT 1) AS loaned_at,
                (SELECT COALESCE(SUM(CAST(julianday(COALESCE(returned_at, CURRENT_TIMESTAMP)) - julianday(loaned_at) AS INTEGER)), 0) FROM loans WHERE item_id = li.id) AS total_days
            FROM library_items li
            LEFT JOIN users u ON li.owner_id = u.id
            ORDER BY li.category ASC, li.title ASC
        ');
    }
    return $stmt->fetchAll();
}

function addLibraryItem(array $user, array $data): void
{
    $intOrNull = fn($v) => (isset($v) && is_numeric($v) && $v !== '') ? (int)$v : null;
    $strOrNull = fn($v) => (trim($v ?? '') !== '') ? trim($v) : null;
    getDB()->prepare('
        INSERT INTO library_items
            (category, title, subtitle, description, owner_id, url, game_duration, age_min, player_min, player_max, book_genre)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ')->execute([
        $data['category']      ?? 'autre',
        trim($data['title']),
        $strOrNull($data['subtitle']      ?? ''),
        $strOrNull($data['description']   ?? ''),
        $user['id'],
        $strOrNull($data['url']           ?? ''),
        $strOrNull($data['game_duration'] ?? ''),
        $intOrNull($data['age_min']    ?? ''),
        $intOrNull($data['player_min'] ?? ''),
        $intOrNull($data['player_max'] ?? ''),
        $strOrNull($data['book_genre'] ?? ''),
    ]);
}

function deleteLibraryItem(int $id, array $user): bool
{
    $db   = getDB();
    $stmt = $db->prepare('SELECT owner_id FROM library_items WHERE id = ?');
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if (!$item) {
        return false;
    }
    if ($item['owner_id'] !== $user['id'] && $user['role'] !== 'admin') {
        return false;
    }
    $db->prepare('DELETE FROM library_items WHERE id = ?')->execute([$id]);
    return true;
}

function updateLibraryItem(int $itemId, array $data, array $user): bool
{
    $db   = getDB();
    $stmt = $db->prepare('SELECT owner_id FROM library_items WHERE id = ?');
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    if (!$item) {
        return false;
    }
    if ($item['owner_id'] != $user['id'] && $user['role'] !== 'admin') {
        return false;
    }
    $intOrNull = fn($v) => (isset($v) && is_numeric($v) && $v !== '') ? (int)$v : null;
    $strOrNull = fn($v) => (trim($v ?? '') !== '') ? trim($v) : null;
    $db->prepare('
        UPDATE library_items
        SET category = ?, title = ?, subtitle = ?, description = ?,
            url = ?, game_duration = ?, age_min = ?, player_min = ?, player_max = ?, book_genre = ?
        WHERE id = ?
    ')->execute([
        $data['category']      ?? 'autre',
        trim($data['title']    ?? ''),
        $strOrNull($data['subtitle']      ?? ''),
        $strOrNull($data['description']   ?? ''),
        $strOrNull($data['url']           ?? ''),
        $strOrNull($data['game_duration'] ?? ''),
        $intOrNull($data['age_min']    ?? ''),
        $intOrNull($data['player_min'] ?? ''),
        $intOrNull($data['player_max'] ?? ''),
        $strOrNull($data['book_genre'] ?? ''),
        $itemId,
    ]);
    return true;
}

function borrowItem(int $itemId, int $userId, ?string $dueDate): bool
{
    $db   = getDB();
    $stmt = $db->prepare('SELECT available FROM library_items WHERE id = ?');
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    if (!$item || !$item['available']) {
        return false;
    }
    $db->prepare('INSERT INTO loans (item_id, borrower_id, due_date) VALUES (?, ?, ?)')
       ->execute([$itemId, $userId, $dueDate ?: null]);
    $db->prepare('UPDATE library_items SET available = 0 WHERE id = ?')->execute([$itemId]);
    return true;
}

function returnItem(int $loanId, array $user): bool
{
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM loans WHERE id = ?');
    $stmt->execute([$loanId]);
    $loan = $stmt->fetch();
    if (!$loan || $loan['returned_at']) {
        return false;
    }
    // Borrower or admin or item owner can return
    $db->prepare('UPDATE loans SET returned_at = datetime("now") WHERE id = ?')->execute([$loanId]);
    $db->prepare('UPDATE library_items SET available = 1 WHERE id = ?')->execute([$loan['item_id']]);
    return true;
}

function getActiveLoans(): array
{
    $stmt = getDB()->query('
        SELECT l.id, l.item_id, l.loaned_at, l.due_date,
               li.title AS item_title, li.category,
               u.display_name AS borrower_name,
               CAST(julianday("now") - julianday(l.loaned_at) AS INTEGER) AS days_out
        FROM   loans l
        JOIN   library_items li ON l.item_id = li.id
        JOIN   users u ON l.borrower_id = u.id
        WHERE  l.returned_at IS NULL
        ORDER  BY l.loaned_at ASC
    ');
    return $stmt->fetchAll();
}

function getTopItems(int $limit = 10): array
{
    $stmt = getDB()->prepare('
        SELECT li.id, li.title, li.category,
               COALESCE(li.condition, "ok") AS condition,
               COUNT(l.id) AS loan_count,
               COALESCE(SUM(CAST(julianday(COALESCE(l.returned_at, datetime("now"))) - julianday(l.loaned_at) AS INTEGER)), 0) AS total_days
        FROM   library_items li
        LEFT JOIN loans l ON l.item_id = li.id
        GROUP  BY li.id
        ORDER  BY loan_count DESC, total_days DESC
        LIMIT  ?
    ');
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function setItemCondition(int $itemId, string $condition, array $user): bool
{
    if (!in_array($condition, ['ok', 'lost', 'broken'], true)) {
        return false;
    }
    $db   = getDB();
    $stmt = $db->prepare('SELECT owner_id FROM library_items WHERE id = ?');
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    if (!$item) {
        return false;
    }
    if ($item['owner_id'] != $user['id'] && $user['role'] !== 'admin') {
        return false;
    }
    if ($condition !== 'ok') {
        $db->prepare('UPDATE loans SET returned_at = datetime("now") WHERE item_id = ? AND returned_at IS NULL')
           ->execute([$itemId]);
        $db->prepare('UPDATE library_items SET available = 0, condition = ? WHERE id = ?')
           ->execute([$condition, $itemId]);
    } else {
        $db->prepare('UPDATE library_items SET available = 1, condition = ? WHERE id = ?')
           ->execute([$condition, $itemId]);
    }
    return true;
}

function getDashboardData(int $userId): array
{
    $db = getDB();

    $lent = $db->prepare('
        SELECT li.id, li.title, li.category,
               u.display_name AS borrower_name,
               l.id AS loan_id, l.loaned_at, l.due_date,
               CAST(julianday("now") - julianday(l.loaned_at) AS INTEGER) AS days_out
        FROM   library_items li
        JOIN   loans l ON l.item_id = li.id AND l.returned_at IS NULL
        JOIN   users u ON l.borrower_id = u.id
        WHERE  li.owner_id = ?
        ORDER  BY l.loaned_at ASC
    ');
    $lent->execute([$userId]);

    $borrowed = $db->prepare('
        SELECT li.id, li.title, li.category,
               u.display_name AS owner_name,
               l.id AS loan_id, l.loaned_at, l.due_date,
               CAST(julianday("now") - julianday(l.loaned_at) AS INTEGER) AS days_out
        FROM   loans l
        JOIN   library_items li ON l.item_id = li.id
        LEFT JOIN users u ON li.owner_id = u.id
        WHERE  l.borrower_id = ? AND l.returned_at IS NULL
        ORDER  BY l.loaned_at ASC
    ');
    $borrowed->execute([$userId]);

    $activities = $db->prepare('
        SELECT c.id, c.title, c.tag, c.status, c.event_date, c.column_id,
               u.display_name AS author_name,
               (SELECT COUNT(*) FROM interests WHERE card_id = c.id) AS interest_count
        FROM   interests i
        JOIN   cards c ON i.card_id = c.id
        LEFT JOIN users u ON c.author_id = u.id
        WHERE  i.user_id = ?
        ORDER  BY c.column_id ASC, c.event_date IS NULL ASC, c.event_date ASC
    ');
    $activities->execute([$userId]);

    $myCards = $db->prepare('
        SELECT c.id, c.title, c.tag, c.status, c.event_date, c.column_id,
               (SELECT COUNT(*) FROM interests WHERE card_id = c.id) AS interest_count
        FROM   cards c
        WHERE  c.author_id = ?
        ORDER  BY c.column_id ASC, c.created_at DESC
    ');
    $myCards->execute([$userId]);

    $presences = $db->prepare('
        SELECT c.id, c.title, c.tag, c.event_date, p.attending
        FROM   presences p
        JOIN   cards c ON p.card_id = c.id
        WHERE  p.user_id = ? AND c.status = "planifiee"
        ORDER  BY c.event_date IS NULL ASC, c.event_date ASC
    ');
    $presences->execute([$userId]);

    // Achats groupés où j'ai des demandes (non clôturés)
    $myOrderRequests = $db->prepare('
        SELECT go.id, go.title, go.status, go.deadline, go.creator_id,
            u.display_name AS creator_name,
            COUNT(DISTINCT gor.product_id)              AS product_count,
            COALESCE(SUM(gor.quantity * gop.unit_price), 0) AS my_total,
            SUM(gor.paid)       AS paid_count,
            SUM(gor.dispatched) AS dispatched_count,
            COUNT(gor.id)       AS request_count
        FROM group_order_requests gor
        JOIN group_order_products gop ON gop.id = gor.product_id
        JOIN group_orders go ON go.id = gop.order_id
        LEFT JOIN users u ON u.id = go.creator_id
        WHERE gor.user_id = ?
        GROUP BY go.id
        ORDER BY
            CASE go.status WHEN "received" THEN 0 WHEN "ordered" THEN 1 WHEN "open" THEN 2 ELSE 3 END,
            go.deadline IS NULL ASC, go.deadline ASC
    ');
    $myOrderRequests->execute([$userId]);

    // Achats groupés que j'ai créés
    $myCreatedOrders = $db->prepare('
        SELECT go.id, go.title, go.status, go.deadline,
            COUNT(DISTINCT gop.id)    AS product_count,
            COUNT(DISTINCT CASE WHEN gor.quantity > 0 THEN gor.user_id END) AS participant_count,
            COALESCE(SUM(CASE WHEN gor.quantity > 0 THEN gor.quantity * gop.unit_price ELSE 0 END), 0) AS total_amount
        FROM group_orders go
        LEFT JOIN group_order_products gop ON gop.order_id = go.id
        LEFT JOIN group_order_requests gor ON gor.product_id = gop.id
        WHERE go.creator_id = ?
        GROUP BY go.id
        ORDER BY go.created_at DESC
        LIMIT 10
    ');
    $myCreatedOrders->execute([$userId]);

    return [
        'lent'              => $lent->fetchAll(),
        'borrowed'          => $borrowed->fetchAll(),
        'activities'        => $activities->fetchAll(),
        'my_cards'          => $myCards->fetchAll(),
        'presences'         => $presences->fetchAll(),
        'my_order_requests' => $myOrderRequests->fetchAll(),
        'my_created_orders' => $myCreatedOrders->fetchAll(),
    ];
}

// ──────────────────────────────────────────────
//  ADMIN — GESTION UTILISATEURS
// ──────────────────────────────────────────────

function getAllUsers(): array
{
    return getDB()->query('
        SELECT id, display_name, role, household, created_at, last_login, is_active, gdpr_consent_at
        FROM   users
        ORDER  BY created_at ASC
    ')->fetchAll();
}

function toggleUserActive(int $id): void
{
    getDB()->prepare('UPDATE users SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id = ?')
           ->execute([$id]);
}

function setUserRole(int $id, string $role): void
{
    if (!in_array($role, ['admin', 'member', 'external'], true)) {
        return;
    }
    getDB()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $id]);
}

// ──────────────────────────────────────────────
//  RGPD
// ──────────────────────────────────────────────

function getUserData(int $id): array
{
    $db = getDB();
    $profile = $db->prepare(
        'SELECT id, display_name, role, household, created_at, last_login, gdpr_consent_at FROM users WHERE id = ?'
    );
    $profile->execute([$id]);

    $cards = $db->prepare('SELECT id, title, column_id, tag, created_at FROM cards WHERE author_id = ?');
    $cards->execute([$id]);

    $ints = $db->prepare('
        SELECT i.card_id, c.title, i.created_at
        FROM   interests i JOIN cards c ON i.card_id = c.id
        WHERE  i.user_id = ?
    ');
    $ints->execute([$id]);

    $loans = $db->prepare('
        SELECT l.id, li.title, l.loaned_at, l.due_date, l.returned_at
        FROM   loans l JOIN library_items li ON l.item_id = li.id
        WHERE  l.borrower_id = ?
    ');
    $loans->execute([$id]);

    $pollVotes = $db->prepare('
        SELECT dpv.poll_id, dp.proposed_date, c.title AS card_title, dpv.created_at
        FROM   date_poll_votes dpv
        JOIN   date_polls dp ON dpv.poll_id = dp.id
        JOIN   cards c ON dp.card_id = c.id
        WHERE  dpv.user_id = ?
    ');
    $pollVotes->execute([$id]);

    $pres = $db->prepare('
        SELECT p.card_id, c.title AS card_title, p.attending, p.created_at
        FROM   presences p
        JOIN   cards c ON p.card_id = c.id
        WHERE  p.user_id = ?
    ');
    $pres->execute([$id]);

    return [
        'profile'         => $profile->fetch(),
        'cards'           => $cards->fetchAll(),
        'interests'       => $ints->fetchAll(),
        'date_poll_votes' => $pollVotes->fetchAll(),
        'presences'       => $pres->fetchAll(),
        'loans'           => $loans->fetchAll(),
    ];
}

function deleteAccount(int $id): void
{
    $db = getDB();
    // Anonymiser le contenu (on conserve l'historique, on supprime l'auteur)
    $db->prepare('UPDATE cards          SET author_id = NULL WHERE author_id = ?')->execute([$id]);
    $db->prepare('UPDATE library_items  SET owner_id  = NULL WHERE owner_id  = ?')->execute([$id]);
    // Les intérêts et emprunts sont supprimés en cascade (FK)
    $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
}

// ──────────────────────────────────────────────
//  UTILITAIRES
// ──────────────────────────────────────────────

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function flash(string $key, string $msg = ''): string
{
    if ($msg !== '') {
        $_SESSION['flash'][$key] = $msg;
        return '';
    }
    $v = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $v;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function fmtDate(?string $d): string
{
    if (!$d) {
        return '';
    }
    $ts = strtotime($d);
    return $ts ? date('d/m/Y', $ts) : $d;
}

// ──────────────────────────────────────────────
//  CONSTANTES D'AFFICHAGE
// ──────────────────────────────────────────────

const STATUS_META = [
    'idea'        => ['label' => 'Idée'],
    'a_planifier' => ['label' => 'À planifier'],
    'planifiee'   => ['label' => 'Planifiée'],
    'annulee'     => ['label' => 'Annulée'],
    'reportee'    => ['label' => 'Reportée'],
];

const CONDITION_META = [
    'ok'     => ['label' => 'OK',    'cls' => 'cond-ok'],
    'lost'   => ['label' => 'Perdu', 'cls' => 'cond-lost'],
    'broken' => ['label' => 'Cassé', 'cls' => 'cond-broken'],
];

const TAG_META = [
    'savoir'  => ['emoji' => '🎁', 'label' => 'Savoir-faire', 'cls' => 'tag-savoir'],
    'jeux'    => ['emoji' => '🎲', 'label' => 'Jeux',         'cls' => 'tag-jeux'  ],
    'lecture' => ['emoji' => '📖', 'label' => 'Lecture',      'cls' => 'tag-lecture'],
    'nature'  => ['emoji' => '🌿', 'label' => 'Nature',       'cls' => 'tag-nature' ],
    'cinema'  => ['emoji' => '🎬', 'label' => 'Cinéma',       'cls' => 'tag-cinema' ],
    'autre'   => ['emoji' => '✨', 'label' => 'Autre',        'cls' => 'tag-autre'  ],
];

const AUDIENCE_META = [
    'adultes' => '👤 Adultes',
    'mixte'   => '👨‍👩‍👧 Mixte',
    'enfants' => '🧒 Enfants',
];

const COL_META = [
    0 => ['icon' => '📬', 'title' => 'Dans la boîte',  'desc' => 'Une envie, un savoir-faire — dépose-la ici.'],
    1 => ['icon' => '📅', 'title' => 'À venir',        'desc' => 'L\'activité a une date. Qui est partant·e ?'],
    2 => ['icon' => '✅', 'title' => 'Vécu',           'desc' => 'Les moments partagés — pour s\'en souvenir.'],
];

const LIB_CAT_META = [
    'livre' => ['emoji' => '📚', 'label' => 'Livres'],
    'outil' => ['emoji' => '🔧', 'label' => 'Outils'],
    'jeu'   => ['emoji' => '🎲', 'label' => 'Jeux'],
    'autre' => ['emoji' => '📦', 'label' => 'Autre'],
];

const ORDER_STATUS_META = [
    'open'     => ['label' => 'Ouvert',   'cls' => 'go-status-open'],
    'ordered'  => ['label' => 'Commandé', 'cls' => 'go-status-ordered'],
    'received' => ['label' => 'Reçu',     'cls' => 'go-status-received'],
    'closed'   => ['label' => 'Clôturé',  'cls' => 'go-status-closed'],
];

// ══════════════════════════════════════════════════════════
//  ACHATS GROUPÉS
// ══════════════════════════════════════════════════════════

function getGroupOrders(): array
{
    return getDB()->query("
        SELECT go.*, u.display_name AS creator_name,
            COUNT(DISTINCT gop.id) AS product_count,
            COUNT(DISTINCT CASE WHEN gor.quantity > 0 THEN gor.user_id END) AS participant_count,
            COALESCE(SUM(CASE WHEN gor.quantity > 0 THEN gor.quantity * gop.unit_price ELSE 0 END), 0) AS total_amount
        FROM group_orders go
        LEFT JOIN users u ON u.id = go.creator_id
        LEFT JOIN group_order_products gop ON gop.order_id = go.id
        LEFT JOIN group_order_requests gor ON gor.product_id = gop.id
        GROUP BY go.id
        ORDER BY go.created_at DESC
    ")->fetchAll();
}

function getGroupOrder(int $id): ?array
{
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT go.*, u.display_name AS creator_name
        FROM group_orders go
        LEFT JOIN users u ON u.id = go.creator_id
        WHERE go.id = ?
    ");
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (!$order) return null;

    $stmt = $db->prepare("
        SELECT gop.*,
            COALESCE(SUM(CASE WHEN gor.quantity > 0 THEN gor.quantity ELSE 0 END), 0) AS total_qty,
            COALESCE(SUM(CASE WHEN gor.quantity > 0 THEN gor.quantity * gop.unit_price ELSE 0 END), 0) AS total_price
        FROM group_order_products gop
        LEFT JOIN group_order_requests gor ON gor.product_id = gop.id
        WHERE gop.order_id = ?
        GROUP BY gop.id
        ORDER BY gop.created_at
    ");
    $stmt->execute([$id]);
    $order['products'] = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT gor.id, gor.product_id, gor.user_id, gor.quantity, gor.paid, gor.dispatched,
            u.display_name AS user_name,
            gop.name AS product_name, gop.unit, gop.unit_price,
            gor.quantity * gop.unit_price AS line_price
        FROM group_order_requests gor
        JOIN users u ON u.id = gor.user_id
        JOIN group_order_products gop ON gop.id = gor.product_id
        WHERE gop.order_id = ? AND gor.quantity > 0
        ORDER BY u.display_name, gop.created_at
    ");
    $stmt->execute([$id]);
    $requests = $stmt->fetchAll();

    $byProduct = [];
    $byUser    = [];
    foreach ($requests as $r) {
        $byProduct[$r['product_id']][$r['user_id']] = $r;
        $byUser[$r['user_id']][] = $r;
    }
    $order['requests']            = $requests;
    $order['requests_by_product'] = $byProduct;
    $order['requests_by_user']    = $byUser;

    return $order;
}

function addGroupOrder(array $user, array $data): int
{
    $db = getDB();
    $db->prepare("INSERT INTO group_orders (title, description, deadline, creator_id) VALUES (?,?,?,?)")
       ->execute([trim($data['title']), trim($data['description'] ?? ''), $data['deadline'] ?: null, $user['id']]);
    return (int)$db->lastInsertId();
}

function updateGroupOrderStatus(int $id, string $status, array $user): bool
{
    if (!array_key_exists($status, ORDER_STATUS_META)) return false;
    $db   = getDB();
    $stmt = $db->prepare("SELECT creator_id FROM group_orders WHERE id = ?");
    $stmt->execute([$id]);
    $row  = $stmt->fetch();
    if (!$row || ($user['role'] !== 'admin' && $row['creator_id'] !== $user['id'])) return false;
    $db->prepare("UPDATE group_orders SET status = ? WHERE id = ?")->execute([$status, $id]);
    return true;
}

function deleteGroupOrder(int $id, array $user): bool
{
    $db   = getDB();
    $stmt = $db->prepare("SELECT creator_id FROM group_orders WHERE id = ?");
    $stmt->execute([$id]);
    $row  = $stmt->fetch();
    if (!$row || ($user['role'] !== 'admin' && $row['creator_id'] !== $user['id'])) return false;
    $db->prepare("DELETE FROM group_orders WHERE id = ?")->execute([$id]);
    return true;
}

function addGroupOrderProduct(int $order_id, array $user, array $data): bool
{
    $db   = getDB();
    $stmt = $db->prepare("SELECT creator_id, status FROM group_orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $row  = $stmt->fetch();
    if (!$row || $row['status'] !== 'open') return false;
    if ($user['role'] !== 'admin' && $row['creator_id'] !== $user['id']) return false;
    $conditioning = trim($data['conditioning'] ?? '');
    $conditioning = $conditioning !== '' ? (float)str_replace(',', '.', $conditioning) : null;
    if ($conditioning !== null && $conditioning <= 0) $conditioning = null;
    $db->prepare("INSERT INTO group_order_products (order_id, name, unit, unit_price, conditioning) VALUES (?,?,?,?,?)")
       ->execute([
           $order_id,
           trim($data['name']),
           trim($data['unit'] ?: 'unité'),
           (float)str_replace(',', '.', $data['unit_price'] ?? '0'),
           $conditioning,
       ]);
    return true;
}

function deleteGroupOrderProduct(int $product_id, array $user): bool
{
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT go.creator_id, go.status
        FROM group_order_products gop
        JOIN group_orders go ON go.id = gop.order_id
        WHERE gop.id = ?
    ");
    $stmt->execute([$product_id]);
    $row  = $stmt->fetch();
    if (!$row || $row['status'] !== 'open') return false;
    if ($user['role'] !== 'admin' && $row['creator_id'] !== $user['id']) return false;
    $db->prepare("DELETE FROM group_order_products WHERE id = ?")->execute([$product_id]);
    return true;
}

function setGroupOrderRequest(int $product_id, int $user_id, float $quantity): bool
{
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT go.status
        FROM group_order_products gop
        JOIN group_orders go ON go.id = gop.order_id
        WHERE gop.id = ?
    ");
    $stmt->execute([$product_id]);
    $row  = $stmt->fetch();
    if (!$row || $row['status'] !== 'open') return false;
    if ($quantity <= 0) {
        $db->prepare("DELETE FROM group_order_requests WHERE product_id = ? AND user_id = ?")
           ->execute([$product_id, $user_id]);
    } else {
        $db->prepare("
            INSERT INTO group_order_requests (product_id, user_id, quantity) VALUES (?,?,?)
            ON CONFLICT(product_id, user_id) DO UPDATE SET quantity = excluded.quantity
        ")->execute([$product_id, $user_id, $quantity]);
    }
    return true;
}

function setRequestPaid(int $request_id, int $paid, array $user): bool
{
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT go.creator_id
        FROM group_order_requests gor
        JOIN group_order_products gop ON gop.id = gor.product_id
        JOIN group_orders go ON go.id = gop.order_id
        WHERE gor.id = ?
    ");
    $stmt->execute([$request_id]);
    $row  = $stmt->fetch();
    if (!$row || ($user['role'] !== 'admin' && $row['creator_id'] !== $user['id'])) return false;
    $db->prepare("UPDATE group_order_requests SET paid = ? WHERE id = ?")->execute([$paid ? 1 : 0, $request_id]);
    return true;
}

function setRequestDispatched(int $request_id, int $dispatched, array $user): bool
{
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT go.creator_id
        FROM group_order_requests gor
        JOIN group_order_products gop ON gop.id = gor.product_id
        JOIN group_orders go ON go.id = gop.order_id
        WHERE gor.id = ?
    ");
    $stmt->execute([$request_id]);
    $row  = $stmt->fetch();
    if (!$row || ($user['role'] !== 'admin' && $row['creator_id'] !== $user['id'])) return false;
    $db->prepare("UPDATE group_order_requests SET dispatched = ? WHERE id = ?")->execute([$dispatched ? 1 : 0, $request_id]);
    return true;
}
