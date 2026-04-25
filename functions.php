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
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            category    TEXT    NOT NULL DEFAULT 'autre',
            title       TEXT    NOT NULL,
            subtitle    TEXT,
            description TEXT,
            owner_id    INTEGER,
            available   INTEGER NOT NULL DEFAULT 1,
            created_at  TEXT    NOT NULL DEFAULT (datetime('now')),
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

        -- Tentatives de connexion (anti-brute force)
        CREATE TABLE IF NOT EXISTS login_attempts (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            identifier   TEXT NOT NULL,
            attempted_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
    ");
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
    getDB()->prepare('
        INSERT INTO cards (column_id, tag, title, body, author_id, audience, event_date)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ')->execute([
        max(0, min(2, (int) ($data['column_id'] ?? 0))),
        $data['tag']      ?? 'autre',
        trim($data['title']),
        trim($data['body']  ?? ''),
        $user['id'],
        $data['audience'] ?? 'adultes',
        $data['event_date'] ?: null,
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
    $db->prepare('UPDATE cards SET column_id = ?, updated_at = datetime("now") WHERE id = ?')
       ->execute([max(0, min(2, $col)), $id]);
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
                (SELECT id FROM loans WHERE item_id = li.id AND returned_at IS NULL LIMIT 1) AS loan_id
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
                (SELECT id FROM loans WHERE item_id = li.id AND returned_at IS NULL LIMIT 1) AS loan_id
            FROM library_items li
            LEFT JOIN users u ON li.owner_id = u.id
            ORDER BY li.category ASC, li.title ASC
        ');
    }
    return $stmt->fetchAll();
}

function addLibraryItem(array $user, array $data): void
{
    getDB()->prepare('
        INSERT INTO library_items (category, title, subtitle, description, owner_id)
        VALUES (?, ?, ?, ?, ?)
    ')->execute([
        $data['category']    ?? 'autre',
        trim($data['title']),
        trim($data['subtitle']     ?? ''),
        trim($data['description']  ?? ''),
        $user['id'],
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

    return [
        'profile'   => $profile->fetch(),
        'cards'     => $cards->fetchAll(),
        'interests' => $ints->fetchAll(),
        'loans'     => $loans->fetchAll(),
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
