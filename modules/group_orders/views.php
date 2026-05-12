<?php
// ═══════════════════════════════════════════════════════════
//  Module Group Orders — Vues
// ═══════════════════════════════════════════════════════════

function viewGroupOrders(array $user): void
{
    $orders  = getGroupOrders();
    $isAdmin = $user['role'] === 'admin';
    $csrf    = csrfField();
    $err     = flash('error');
    $ok      = flash('success');

    layoutOpen('Achats groupés', $user, 'group_orders');
    echo '<div class="page">';
    echo '<div class="page-header flex-between" style="flex-wrap:wrap;gap:.75rem">';
    echo '<div><h1 style="font-family:\'Lora\',serif;font-size:1.6rem">🛒 Achats groupés</h1>';
    echo '<p style="color:var(--muted);margin-top:.3rem;font-size:.9rem">Organisez des commandes collectives pour l\'habitat</p></div>';
    echo '<button class="btn btn-primary" onclick="document.getElementById(\'modal-new-order\').classList.add(\'open\')">+ Nouvel achat</button>';
    echo '</div>';

    if ($err) echo '<div class="alert alert-error">' . h($err) . '</div>';
    if ($ok)  echo '<div class="alert alert-success">' . h($ok) . '</div>';

    if (empty($orders)) {
        echo '<div style="text-align:center;padding:3rem;color:var(--muted)">';
        echo '<p style="font-size:2rem">🛒</p><p style="margin-top:.5rem">Aucun achat groupé pour l\'instant.<br>Créez le premier !</p>';
        echo '</div>';
    } else {
        $statusFilter = preg_replace('/[^a-z]/', '', $_GET['status'] ?? 'all');
        echo '<div class="go-tabs">';
        foreach (['all' => 'Tous', 'open' => 'Ouverts', 'ordered' => 'Commandés', 'received' => 'Reçus', 'closed' => 'Clôturés'] as $key => $label) {
            $active = $statusFilter === $key ? 'active' : '';
            echo "<a class='go-tab {$active}' href='?action=group_orders&status={$key}'>{$label}</a>";
        }
        echo '</div>';

        foreach ($orders as $summary) {
            if ($statusFilter !== 'all' && $summary['status'] !== $statusFilter) continue;
            $order = getGroupOrder((int)$summary['id']);
            if (!$order) continue;
            renderGroupOrder($order, $user, $csrf, $isAdmin);
        }
    }

    // Modal création d'un achat groupé
    echo <<<HTML
<div class="modal-overlay" id="modal-new-order" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal" role="dialog" aria-modal="true" aria-label="Nouvel achat groupé">
    <h3>🛒 Nouvel achat groupé</h3>
    <form method="post" action="?action=group_order_add">
HTML;
    echo $csrf;
    echo <<<HTML
      <div class="form-group">
        <label>Titre *</label>
        <input type="text" name="title" required maxlength="120" placeholder="ex : Oranges bio marché Villeneuve">
      </div>
      <div class="form-group">
        <label>Description</label>
        <textarea name="description" placeholder="Source, lien, informations pratiques…"></textarea>
      </div>
      <div class="form-group">
        <label>Date limite de commande</label>
        <input type="date" name="deadline">
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-new-order').classList.remove('open')">Annuler</button>
        <button type="submit" class="btn btn-primary">Créer</button>
      </div>
    </form>
  </div>
</div>
HTML;

    echo '</div>'; // .page
    layoutClose();
}

function renderGroupOrder(array $order, array $user, string $csrf, bool $isAdmin): void
{
    $oid          = (int)$order['id'];
    $isCreator    = (int)$order['creator_id'] === (int)$user['id'];
    $canManage    = $isAdmin || $isCreator;
    $isOpen       = $order['status'] === 'open';
    $statusMeta   = ORDER_STATUS_META[$order['status']] ?? ['label' => $order['status'], 'cls' => ''];
    $statusLabel  = h($statusMeta['label']);
    $statusCls    = h($statusMeta['cls']);
    $title        = h($order['title']);
    $creator      = h($order['creator_name'] ?? '—');
    $deadline     = $order['deadline'] ? 'Commande avant le ' . h(fmtDate($order['deadline'])) : '';
    $productCount = count($order['products']);
    $partCount    = count($order['requests_by_user']);

    $totalAmount  = array_sum(array_column($order['products'], 'total_price'));
    $totalFmt     = number_format($totalAmount, 2, ',', ' ');

    echo "<div class='go-order' id='order-{$oid}'>";
    echo "<div class='go-order-head'>";
    echo "<div>";
    echo "<div class='go-order-title'>{$title}</div>";
    echo "<div class='go-order-meta'>";
    echo "<span class='go-status {$statusCls}'>{$statusLabel}</span>";
    if ($deadline) echo "<span>📅 {$deadline}</span>";
    echo "<span>👤 {$creator}</span>";
    echo "<span>📦 {$productCount} produit" . ($productCount > 1 ? 's' : '') . "</span>";
    if ($partCount) echo "<span>👥 {$partCount} participant" . ($partCount > 1 ? 's' : '') . "</span>";
    echo "</div>";
    if ($order['description']) echo "<p class='go-desc' style='margin-top:.5rem'>" . h($order['description']) . "</p>";
    echo "</div>"; // info block

    // Boutons de gestion
    echo "<div style='display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;flex-shrink:0'>";
    if ($canManage) {
        $nextMap   = ['open' => 'ordered', 'ordered' => 'received', 'received' => 'closed'];
        $labelMap  = ['open' => '📬 Commandé', 'ordered' => '📦 Reçu', 'received' => '✅ Clôturer'];
        if (isset($nextMap[$order['status']])) {
            $ns = $nextMap[$order['status']];
            $nl = $labelMap[$order['status']];
            echo "<form method='post' action='?action=group_order_status'>{$csrf}";
            echo "<input type='hidden' name='order_id' value='{$oid}'>";
            echo "<input type='hidden' name='status' value='{$ns}'>";
            echo "<button type='submit' class='btn btn-ghost btn-sm'>{$nl}</button></form>";
        }
        echo "<form method='post' action='?action=group_order_delete' onsubmit=\"return confirm('Supprimer cet achat groupé et toutes ses données ?')\">{$csrf}";
        echo "<input type='hidden' name='order_id' value='{$oid}'>";
        echo "<button type='submit' class='btn-icon' title='Supprimer'>🗑</button></form>";
    }
    echo "</div>"; // actions
    echo "</div>"; // .go-order-head

    echo "<div class='go-order-body'>";

    // Formulaire ajout produit (si ouvert + droits)
    if ($isOpen && $canManage) {
        echo "<form class='go-add-product-form' method='post' action='?action=group_order_product_add'>{$csrf}";
        echo "<input type='hidden' name='order_id' value='{$oid}'>";
        echo "<div class='form-group'><label>Produit *</label><input type='text' name='name' required placeholder='ex : Oranges bio'></div>";
        echo "<div class='form-group'><label>Unité</label><input type='text' name='unit' value='kg' placeholder='kg, L, unité…' style='width:80px'></div>";
        echo "<div class='form-group'><label>Prix / unité (€)</label><input type='number' name='unit_price' step='0.01' min='0' value='0' style='width:100px'></div>";
        echo "<div class='form-group'><label title='Lot minimum d\\'achat, ex : 5 pour des oranges vendues par 5 kg'>Condit. (optionnel)</label><input type='number' name='conditioning' step='0.01' min='0.01' placeholder='ex : 5' style='width:90px'></div>";
        echo "<button type='submit' class='btn btn-primary btn-sm' style='margin-top:auto'>+ Ajouter</button>";
        echo "</form>";
    }

    if (empty($order['products'])) {
        echo '<p style="color:var(--muted);font-size:.88rem;font-style:italic">Aucun produit pour l\'instant.</p>';
    } else {
        foreach ($order['products'] as $product) {
            renderGroupProduct($product, $order, $user, $csrf, $canManage, $isOpen);
        }
        if ($totalAmount > 0) {
            echo "<div class='go-total-banner'><span>Total de la commande</span><span><strong>{$totalFmt} €</strong></span></div>";
        }
    }

    // Vue dispatch (réception / clôture)
    if (in_array($order['status'], ['received', 'closed'], true) && !empty($order['requests_by_user'])) {
        echo "<div class='go-dispatch'><h3>📋 Récapitulatif par habitant</h3>";
        foreach ($order['requests_by_user'] as $uid => $reqs) {
            $uname    = h($reqs[0]['user_name']);
            $subtotal = array_sum(array_column($reqs, 'line_price'));
            $subFmt   = number_format($subtotal, 2, ',', ' ');
            echo "<div class='go-person'><div class='go-person-name'>👤 {$uname}</div>";
            foreach ($reqs as $req) {
                $rid       = (int)$req['id'];
                $pname     = h($req['product_name']);
                $unit      = h($req['unit']);
                $qty       = $req['quantity'];
                $price     = number_format($req['line_price'], 2, ',', ' ');
                $paidDone  = $req['paid']       ? 'done' : '';
                $dispDone  = $req['dispatched'] ? 'done' : '';
                echo "<div class='go-person-row'>";
                echo "<span style='flex:1'>{$pname} — {$qty} {$unit}</span>";
                echo "<span style='color:var(--muted);margin-right:.5rem'>{$price} €</span>";
                if ($canManage) {
                    $pNext = $req['paid']       ? 0 : 1;
                    $dNext = $req['dispatched'] ? 0 : 1;
                    $pLbl  = $req['paid']       ? '✓ Payé'  : '○ Payé';
                    $dLbl  = $req['dispatched'] ? '✓ Remis' : '○ Remis';
                    echo "<form method='post' action='?action=group_order_request_paid' style='display:inline'>{$csrf}";
                    echo "<input type='hidden' name='request_id' value='{$rid}'><input type='hidden' name='paid' value='{$pNext}'>";
                    echo "<button type='submit' class='go-check-btn {$paidDone}'>{$pLbl}</button></form> ";
                    echo "<form method='post' action='?action=group_order_request_dispatched' style='display:inline'>{$csrf}";
                    echo "<input type='hidden' name='request_id' value='{$rid}'><input type='hidden' name='dispatched' value='{$dNext}'>";
                    echo "<button type='submit' class='go-check-btn {$dispDone}'>{$dLbl}</button></form>";
                } else {
                    if ($req['paid'])       echo "<span class='go-check-btn done' style='cursor:default'>✓ Payé</span> ";
                    if ($req['dispatched']) echo "<span class='go-check-btn done' style='cursor:default'>✓ Remis</span>";
                }
                echo "</div>"; // .go-person-row
            }
            echo "<div class='go-person-subtotal'>Sous-total : {$subFmt} €</div>";
            echo "</div>"; // .go-person
        }
        echo "</div>"; // .go-dispatch
    }

    echo "</div>"; // .go-order-body
    echo "</div>"; // .go-order
}

function renderGroupProduct(array $product, array $order, array $user, string $csrf, bool $canManage, bool $isOpen): void
{
    $pid       = (int)$product['id'];
    $pname     = h($product['name']);
    $unit      = h($product['unit']);
    $unitPrice = number_format($product['unit_price'], 2, ',', ' ');
    $totalQty  = (float)$product['total_qty'];
    $totalFmt  = number_format($product['total_price'], 2, ',', ' ');
    $myReq     = $order['requests_by_product'][$pid][$user['id']] ?? null;
    $myQty     = $myReq ? $myReq['quantity'] : '';
    $cond      = $product['conditioning'] !== null ? (float)$product['conditioning'] : null;

    // Calcul conditionnement
    $hasCondWarning = false;
    $minToOrder     = $totalQty;
    $reliquat       = 0.0;
    $lotsNeeded     = 0;
    if ($cond !== null && $cond > 0 && $totalQty > 0) {
        $lotsNeeded     = (int)ceil($totalQty / $cond);
        $minToOrder     = $lotsNeeded * $cond;
        $reliquat       = round($minToOrder - $totalQty, 10);
        $hasCondWarning = $reliquat > 0.0001;
    }

    echo "<div class='go-product'>";
    echo "<div class='go-product-head'>";
    echo "<div>";
    echo "<span class='go-product-name'>{$pname}</span> <span class='go-product-price'>{$unitPrice} € / {$unit}</span>";
    if ($cond !== null) {
        $condFmt = rtrim(rtrim(number_format($cond, 4, ',', ' '), '0'), ',');
        echo " <span class='go-cond-info'>· conditionnement : {$condFmt} {$unit}</span>";
    }
    echo "</div>";
    if ($isOpen && $canManage) {
        echo "<form method='post' action='?action=group_order_product_delete' style='display:inline' onsubmit=\"return confirm('Supprimer ce produit et toutes les demandes associées ?')\">{$csrf}";
        echo "<input type='hidden' name='product_id' value='{$pid}'>";
        echo "<button type='submit' class='btn-icon btn-sm' title='Supprimer le produit'>✕</button></form>";
    }
    echo "</div>"; // .go-product-head

    // Formulaire ma quantité (si ouvert)
    if ($isOpen) {
        echo "<div class='go-my-qty-form'>";
        echo "<form method='post' action='?action=group_order_request_set' style='display:flex;align-items:center;gap:.5rem;flex-wrap:wrap'>{$csrf}";
        echo "<input type='hidden' name='product_id' value='{$pid}'>";
        echo "<label>Ma demande&nbsp;:</label>";
        echo "<input type='number' name='quantity' value='" . h((string)$myQty) . "' step='0.01' min='0' max='9999' placeholder='0'>";
        echo "<span class='go-my-qty-unit'>{$unit}</span>";
        echo "<button type='submit' class='btn btn-primary btn-sm'>Enregistrer</button>";
        if ($myReq) {
            echo "<button type='submit' class='btn btn-ghost btn-sm' onclick=\"this.previousElementSibling.previousElementSibling.previousElementSibling.value='0'\" title='Supprimer ma demande'>Retirer</button>";
        }
        echo "</form>";
        echo "</div>";
    }

    // Toutes les demandes
    $allReqs = $order['requests_by_product'][$pid] ?? [];
    if (!empty($allReqs)) {
        echo "<div class='go-requests-list'>";
        foreach ($allReqs as $uid => $req) {
            $isMine = (int)$uid === (int)$user['id'];
            $cls    = $isMine ? 'mine' : '';
            $label  = $isMine ? 'Moi' : h($req['user_name']);
            $qty    = $req['quantity'];
            $price  = number_format($req['line_price'], 2, ',', ' ');
            echo "<span class='go-req-chip {$cls}'>{$label} : {$qty} {$unit} ({$price} €)</span>";
        }
        echo "</div>";
    }

    // Total et avertissement conditionnement
    if ($totalQty > 0) {
        $totalQtyFmt = rtrim(rtrim(number_format($totalQty, 4, ',', ' '), '0'), ',');
        echo "<div class='go-product-total'>Total demandé : <strong>{$totalQtyFmt} {$unit}</strong> — <strong>{$totalFmt} €</strong></div>";

        if ($hasCondWarning) {
            $condFmt     = rtrim(rtrim(number_format($cond, 4, ',', ' '), '0'), ',');
            $minFmt      = rtrim(rtrim(number_format($minToOrder, 4, ',', ' '), '0'), ',');
            $reliqFmt    = rtrim(rtrim(number_format($reliquat,   4, ',', ' '), '0'), ',');
            $lotsLabel   = $lotsNeeded > 1 ? "{$lotsNeeded} lots" : "1 lot";
            $minPriceFmt = number_format($minToOrder * $product['unit_price'], 2, ',', ' ');
            echo "<div class='go-cond-warning'>";
            echo "⚠️ Le total demandé (<strong>{$totalQtyFmt} {$unit}</strong>) n'est pas un multiple du conditionnement (<strong>{$condFmt} {$unit}</strong>).<br>";
            echo "Il faudra commander <strong>{$lotsLabel} → {$minFmt} {$unit}</strong> ({$minPriceFmt} €) pour servir tout le monde.<br>";
            echo "Reliquat non attribué : <strong>{$reliqFmt} {$unit}</strong> — à répartir ou absorber.";
            echo "</div>";
        }
    }

    echo "</div>"; // .go-product
}
