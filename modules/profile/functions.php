<?php
// ═══════════════════════════════════════════════════════════
//  Module Profile — Fonctions métier
// ═══════════════════════════════════════════════════════════

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
