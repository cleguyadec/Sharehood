<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/views.php';

return [
    'migrations' => require __DIR__ . '/migrations.php',
    'routes' => [
        'group_orders' => fn(?array $u) => viewGroupOrders(requireAuth()),
    ],
    'actions' => [
        'group_order_add' => function(?array $u): void {
            $user = requireAuth();
            if (empty(trim($_POST['title'] ?? ''))) {
                flash('error', 'Le titre est obligatoire.');
            } else {
                $id = addGroupOrder($user, $_POST);
                flash('success', 'Achat groupé créé.');
            }
            redirect('?action=group_orders');
        },
        'group_order_status' => function(?array $u): void {
            $user = requireAuth();
            if (!updateGroupOrderStatus((int)($_POST['order_id'] ?? 0), $_POST['status'] ?? '', $user)) {
                flash('error', 'Mise à jour non autorisée.');
            }
            redirect('?action=group_orders');
        },
        'group_order_delete' => function(?array $u): void {
            $user = requireAuth();
            if (!deleteGroupOrder((int)($_POST['order_id'] ?? 0), $user)) {
                flash('error', 'Suppression non autorisée.');
            }
            redirect('?action=group_orders');
        },
        'group_order_product_add' => function(?array $u): void {
            $user = requireAuth();
            $order_id = (int)($_POST['order_id'] ?? 0);
            if (empty(trim($_POST['name'] ?? ''))) {
                flash('error', 'Le nom du produit est obligatoire.');
            } elseif (!addGroupOrderProduct($order_id, $user, $_POST)) {
                flash('error', 'Ajout non autorisé ou commande non ouverte.');
            }
            redirect('?action=group_orders');
        },
        'group_order_product_delete' => function(?array $u): void {
            $user = requireAuth();
            if (!deleteGroupOrderProduct((int)($_POST['product_id'] ?? 0), $user)) {
                flash('error', 'Suppression non autorisée.');
            }
            redirect('?action=group_orders');
        },
        'group_order_request_set' => function(?array $u): void {
            $user = requireAuth();
            $qty = (float)str_replace(',', '.', $_POST['quantity'] ?? '0');
            setGroupOrderRequest((int)($_POST['product_id'] ?? 0), $user['id'], $qty);
            redirect('?action=group_orders');
        },
        'group_order_request_paid' => function(?array $u): void {
            $user = requireAuth();
            if (!setRequestPaid((int)($_POST['request_id'] ?? 0), (int)($_POST['paid'] ?? 0), $user)) {
                flash('error', 'Mise à jour non autorisée.');
            }
            redirect('?action=group_orders');
        },
        'group_order_request_dispatched' => function(?array $u): void {
            $user = requireAuth();
            if (!setRequestDispatched((int)($_POST['request_id'] ?? 0), (int)($_POST['dispatched'] ?? 0), $user)) {
                flash('error', 'Mise à jour non autorisée.');
            }
            redirect('?action=group_orders');
        },
    ],
];
