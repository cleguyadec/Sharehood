<?php
// ═══════════════════════════════════════════════════════════
//  Module Group Orders — Constantes & fonctions métier
// ═══════════════════════════════════════════════════════════

const ORDER_STATUS_META = [
    'open'     => ['label' => 'Ouvert',   'cls' => 'go-status-open'],
    'ordered'  => ['label' => 'Commandé', 'cls' => 'go-status-ordered'],
    'received' => ['label' => 'Reçu',     'cls' => 'go-status-received'],
    'closed'   => ['label' => 'Clôturé',  'cls' => 'go-status-closed'],
];

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
