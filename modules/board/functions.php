<?php
// ═══════════════════════════════════════════════════════════
//  Module Board — Constantes & fonctions métier
// ═══════════════════════════════════════════════════════════

const STATUS_META = [
    'idea'        => ['label' => 'Idée'],
    'a_planifier' => ['label' => 'À planifier'],
    'planifiee'   => ['label' => 'Planifiée'],
    'annulee'     => ['label' => 'Annulée'],
    'reportee'    => ['label' => 'Reportée'],
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
