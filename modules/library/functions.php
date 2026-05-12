<?php
// ═══════════════════════════════════════════════════════════
//  Module Library — Constantes & fonctions métier
// ═══════════════════════════════════════════════════════════

const CONDITION_META = [
    'ok'     => ['label' => 'OK',    'cls' => 'cond-ok'],
    'lost'   => ['label' => 'Perdu', 'cls' => 'cond-lost'],
    'broken' => ['label' => 'Cassé', 'cls' => 'cond-broken'],
];

function getLibCats(): array
{
    static $cache = null;
    if ($cache === null) {
        $rows = getDB()->query('SELECT slug, emoji, label FROM lib_categories ORDER BY sort_order ASC, label ASC')->fetchAll();
        $cache = [];
        foreach ($rows as $row) {
            $cache[$row['slug']] = ['emoji' => $row['emoji'], 'label' => $row['label']];
        }
        if (empty($cache)) {
            $cache = [
                'livre' => ['emoji' => '📚', 'label' => 'Livres'],
                'outil' => ['emoji' => '🔧', 'label' => 'Outils'],
                'jeu'   => ['emoji' => '🎲', 'label' => 'Jeux'],
                'autre' => ['emoji' => '📦', 'label' => 'Autre'],
            ];
        }
    }
    return $cache;
}

function getLibCat(string $slug): array
{
    $cats = getLibCats();
    return $cats[$slug] ?? ($cats['autre'] ?? ['emoji' => '📦', 'label' => 'Autre']);
}

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

function addLibCategory(string $slug, string $emoji, string $label): ?string
{
    $slug  = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($slug)));
    $label = trim($label);
    $emoji = trim($emoji) ?: '📦';
    if (!$slug)  return 'Le slug est obligatoire (lettres minuscules, chiffres, underscore).';
    if (!$label) return 'Le libellé est obligatoire.';
    if (strlen($slug) > 30 || strlen($label) > 50) return 'Slug (max 30) ou libellé (max 50) trop long.';
    try {
        $db       = getDB();
        $maxOrder = (int) $db->query('SELECT COALESCE(MAX(sort_order), 0) FROM lib_categories')->fetchColumn();
        $db->prepare('INSERT INTO lib_categories (slug, emoji, label, sort_order) VALUES (?, ?, ?, ?)')
           ->execute([$slug, $emoji, $label, $maxOrder + 1]);
        return null;
    } catch (\PDOException $e) {
        return str_contains($e->getMessage(), 'UNIQUE') ? 'Ce slug existe déjà.' : 'Erreur base de données.';
    }
}

function editLibCategory(int $id, string $emoji, string $label): ?string
{
    $label = trim($label);
    $emoji = trim($emoji) ?: '📦';
    if (!$label) return 'Le libellé est obligatoire.';
    if (strlen($label) > 50) return 'Libellé trop long (max 50 caractères).';
    getDB()->prepare('UPDATE lib_categories SET emoji = ?, label = ? WHERE id = ?')
           ->execute([$emoji, $label, $id]);
    return null;
}

function deleteLibCategory(int $id): ?string
{
    $db   = getDB();
    $stmt = $db->prepare('SELECT slug FROM lib_categories WHERE id = ?');
    $stmt->execute([$id]);
    $cat = $stmt->fetch();
    if (!$cat) return 'Catégorie introuvable.';
    if ($cat['slug'] === 'autre') return 'La catégorie "Autre" ne peut pas être supprimée.';
    $countStmt = $db->prepare('SELECT COUNT(*) FROM library_items WHERE category = ?');
    $countStmt->execute([$cat['slug']]);
    $count = (int) $countStmt->fetchColumn();
    if ($count > 0) return "Cette catégorie contient {$count} objet(s). Déplacez-les d'abord.";
    $db->prepare('DELETE FROM lib_categories WHERE id = ?')->execute([$id]);
    return null;
}
