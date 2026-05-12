<?php
// ═══════════════════════════════════════════════════════════
//  Module Profile — Vues
// ═══════════════════════════════════════════════════════════

function viewDashboard(array $user): void
{
    layoutOpen('Bilan', $user, 'dashboard');
    $data = getDashboardData($user['id']);
    $ok   = flash('success');
    $err  = flash('error');

    echo '<div class="page">';
    echo '<div class="page-header"><h1>📊 Bilan personnel</h1><p>Vos emprunts, prêts et activités dans le groupe.</p></div>';
    if ($err) echo '<div class="alert alert-error">'   . h($err) . '</div>';
    if ($ok)  echo '<div class="alert alert-success">' . h($ok)  . '</div>';

    // ── Objets que j'ai prêtés (actuellement empruntés)
    echo '<div class="section-box mt-2">';
    echo '<div class="section-box-header"><h2>📤 Mes objets actuellement empruntés</h2>';
    echo '<span class="text-sm text-muted">' . count($data['lent']) . ' en cours</span></div>';
    if (empty($data['lent'])) {
        echo '<div class="section-box-body"><p class="text-muted">Aucun de vos objets n\'est emprunté en ce moment.</p></div>';
    } else {
        echo '<div style="overflow-x:auto"><table class="data-table">';
        echo '<thead><tr><th>Objet</th><th>Emprunté par</th><th>Depuis le</th><th>Durée</th><th>Retour prévu</th></tr></thead><tbody>';
        foreach ($data['lent'] as $row) {
            $cat    = getLibCat($row['category']);
            $days   = (int)$row['days_out'];
            $overdue = ($row['due_date'] && $row['due_date'] < date('Y-m-d')) ? ' style="color:#c0392b;font-weight:600"' : '';
            echo '<tr>';
            echo '<td>' . $cat['emoji'] . ' ' . h($row['title']) . '</td>';
            echo '<td>' . h($row['borrower_name']) . '</td>';
            echo '<td>' . fmtDate($row['loaned_at']) . '</td>';
            echo '<td>' . ($days === 0 ? 'Auj.' : $days . ' j') . '</td>';
            echo '<td' . $overdue . '>' . ($row['due_date'] ? fmtDate($row['due_date']) : '—') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';

    // ── Objets que j'emprunte
    echo '<div class="section-box mt-2">';
    echo '<div class="section-box-header"><h2>📥 Mes emprunts en cours</h2>';
    echo '<span class="text-sm text-muted">' . count($data['borrowed']) . ' en cours</span></div>';
    if (empty($data['borrowed'])) {
        echo '<div class="section-box-body"><p class="text-muted">Vous n\'avez aucun emprunt en cours.</p></div>';
    } else {
        echo '<div style="overflow-x:auto"><table class="data-table">';
        echo '<thead><tr><th>Objet</th><th>Propriétaire</th><th>Depuis le</th><th>Durée</th><th>Retour prévu</th><th>Action</th></tr></thead><tbody>';
        foreach ($data['borrowed'] as $row) {
            $cat    = getLibCat($row['category']);
            $days   = (int)$row['days_out'];
            $overdue = ($row['due_date'] && $row['due_date'] < date('Y-m-d')) ? ' style="color:#c0392b;font-weight:600"' : '';
            echo '<tr>';
            echo '<td>' . $cat['emoji'] . ' ' . h($row['title']) . '</td>';
            echo '<td>' . h($row['owner_name'] ?? '—') . '</td>';
            echo '<td>' . fmtDate($row['loaned_at']) . '</td>';
            echo '<td>' . ($days === 0 ? 'Auj.' : $days . ' j') . '</td>';
            echo '<td' . $overdue . '>' . ($row['due_date'] ? fmtDate($row['due_date']) : '—') . '</td>';
            echo '<td><form method="post" action="?action=library_return">' . csrfField();
            echo '<input type="hidden" name="loan_id" value="' . (int)$row['loan_id'] . '">';
            echo '<button type="submit" class="btn btn-ghost btn-sm">📤 Retourner</button></form></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';

    // ── Mes activités (cartes avec intérêt marqué)
    echo '<div class="section-box mt-2">';
    echo '<div class="section-box-header"><h2>🌿 Activités qui m\'intéressent</h2>';
    echo '<span class="text-sm text-muted">' . count($data['activities']) . ' activité' . (count($data['activities']) > 1 ? 's' : '') . '</span></div>';
    if (empty($data['activities'])) {
        echo '<div class="section-box-body"><p class="text-muted">Vous n\'avez marqué d\'intérêt pour aucune activité.</p></div>';
    } else {
        echo '<div style="overflow-x:auto"><table class="data-table">';
        echo '<thead><tr><th>Activité</th><th>Tag</th><th>Statut</th><th>Date prévue</th><th>Intérêt total</th></tr></thead><tbody>';
        foreach ($data['activities'] as $row) {
            $tagLabel = TAG_META[$row['tag']] ?? ['label' => $row['tag'], 'cls' => 'tag-autre'];
            $stMeta   = STATUS_META[$row['status']] ?? STATUS_META['a_planifier'];
            echo '<tr>';
            echo '<td><strong>' . h($row['title']) . '</strong></td>';
            echo '<td><span class="tag ' . $tagLabel['cls'] . '">' . $tagLabel['label'] . '</span></td>';
            echo '<td><span class="status-badge status-' . h($row['status']) . '">' . $stMeta['label'] . '</span></td>';
            echo '<td>' . ($row['event_date'] ? fmtDate($row['event_date']) : '—') . '</td>';
            echo '<td>' . (int)$row['interest_count'] . ' ✦</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';

    // ── Mes présences (activités planifiées)
    if (!empty($data['presences'])) {
        echo '<div class="section-box mt-2">';
        echo '<div class="section-box-header"><h2>📅 Mes présences confirmées</h2>';
        echo '<span class="text-sm text-muted">' . count($data['presences']) . ' événement' . (count($data['presences']) > 1 ? 's' : '') . '</span></div>';
        echo '<div style="overflow-x:auto"><table class="data-table">';
        echo '<thead><tr><th>Activité</th><th>Date</th><th>Présence</th></tr></thead><tbody>';
        foreach ($data['presences'] as $row) {
            $attending = (int)$row['attending'];
            $pLabel    = $attending === 1 ? '<span style="color:#155724">✓ Présent·e</span>' : '<span style="color:#721c24">✗ Absent·e</span>';
            echo '<tr>';
            echo '<td>' . h($row['title']) . '</td>';
            echo '<td>' . ($row['event_date'] ? fmtDate($row['event_date']) : '—') . '</td>';
            echo '<td>' . $pLabel . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        echo '</div>';
    }

    // ── Mes cartes proposées
    echo '<div class="section-box mt-2">';
    echo '<div class="section-box-header"><h2>📌 Mes cartes proposées</h2>';
    echo '<span class="text-sm text-muted">' . count($data['my_cards']) . ' carte' . (count($data['my_cards']) > 1 ? 's' : '') . '</span></div>';
    if (empty($data['my_cards'])) {
        echo '<div class="section-box-body"><p class="text-muted">Vous n\'avez proposé aucune activité.</p></div>';
    } else {
        echo '<div style="overflow-x:auto"><table class="data-table">';
        echo '<thead><tr><th>Activité</th><th>Tag</th><th>Statut</th><th>Date prévue</th><th>Intérêt</th></tr></thead><tbody>';
        foreach ($data['my_cards'] as $row) {
            $tagLabel = TAG_META[$row['tag']] ?? ['label' => $row['tag'], 'cls' => 'tag-autre'];
            $stMeta   = STATUS_META[$row['status']] ?? STATUS_META['a_planifier'];
            echo '<tr>';
            echo '<td><strong>' . h($row['title']) . '</strong></td>';
            echo '<td><span class="tag ' . $tagLabel['cls'] . '">' . $tagLabel['label'] . '</span></td>';
            echo '<td><span class="status-badge status-' . h($row['status']) . '">' . $stMeta['label'] . '</span></td>';
            echo '<td>' . ($row['event_date'] ? fmtDate($row['event_date']) : '—') . '</td>';
            echo '<td>' . (int)$row['interest_count'] . ' ✦</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';

    // ── Mes participations aux achats groupés
    $myReqs = $data['my_order_requests'];
    echo '<div class="section-box mt-2">';
    echo '<div class="section-box-header"><h2>🛒 Mes achats groupés</h2>';
    echo '<span class="text-sm text-muted">' . count($myReqs) . ' commande' . (count($myReqs) > 1 ? 's' : '') . '</span></div>';
    if (empty($myReqs)) {
        echo '<div class="section-box-body"><p class="text-muted">Vous ne participez à aucun achat groupé.</p></div>';
    } else {
        echo '<div style="overflow-x:auto"><table class="data-table">';
        echo '<thead><tr><th>Achat</th><th>Organisateur</th><th>Date limite</th><th>Statut</th><th>Mon total</th><th>Payé / Remis</th></tr></thead><tbody>';
        foreach ($myReqs as $row) {
            $stMeta    = ORDER_STATUS_META[$row['status']] ?? ['label' => $row['status'], 'cls' => ''];
            $myTotal   = number_format($row['my_total'], 2, ',', ' ');
            $paidAll   = $row['paid_count'] >= $row['request_count'] && $row['request_count'] > 0;
            $dispAll   = $row['dispatched_count'] >= $row['request_count'] && $row['request_count'] > 0;
            $paidLabel = $paidAll ? '<span style="color:#155724">✓ Payé</span>' : '<span style="color:var(--muted)">En attente</span>';
            $dispLabel = $dispAll ? '<span style="color:#155724">✓ Remis</span>' : '<span style="color:var(--muted)">En attente</span>';
            $deadlineLate = $row['deadline'] && $row['deadline'] < date('Y-m-d') && $row['status'] === 'open'
                ? ' style="color:#c0392b;font-weight:600"' : '';
            echo '<tr>';
            echo '<td><a href="?action=group_orders#order-' . (int)$row['id'] . '" style="color:inherit"><strong>' . h($row['title']) . '</strong></a></td>';
            echo '<td>' . h($row['creator_name'] ?? '—') . '</td>';
            echo '<td' . $deadlineLate . '>' . ($row['deadline'] ? fmtDate($row['deadline']) : '—') . '</td>';
            echo '<td><span class="go-status ' . h($stMeta['cls']) . '">' . h($stMeta['label']) . '</span></td>';
            echo '<td><strong>' . $myTotal . ' €</strong></td>';
            echo '<td>' . $paidLabel . ' · ' . $dispLabel . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';

    // ── Achats groupés que j'ai créés
    $myCreated = $data['my_created_orders'];
    if (!empty($myCreated)) {
        echo '<div class="section-box mt-2">';
        echo '<div class="section-box-header"><h2>📋 Achats que j\'ai organisés</h2>';
        echo '<span class="text-sm text-muted">' . count($myCreated) . '</span></div>';
        echo '<div style="overflow-x:auto"><table class="data-table">';
        echo '<thead><tr><th>Achat</th><th>Date limite</th><th>Statut</th><th>Produits</th><th>Participants</th><th>Montant total</th></tr></thead><tbody>';
        foreach ($myCreated as $row) {
            $stMeta  = ORDER_STATUS_META[$row['status']] ?? ['label' => $row['status'], 'cls' => ''];
            $total   = number_format($row['total_amount'], 2, ',', ' ');
            $deadlineLate = $row['deadline'] && $row['deadline'] < date('Y-m-d') && $row['status'] === 'open'
                ? ' style="color:#c0392b;font-weight:600"' : '';
            echo '<tr>';
            echo '<td><a href="?action=group_orders#order-' . (int)$row['id'] . '" style="color:inherit"><strong>' . h($row['title']) . '</strong></a></td>';
            echo '<td' . $deadlineLate . '>' . ($row['deadline'] ? fmtDate($row['deadline']) : '—') . '</td>';
            echo '<td><span class="go-status ' . h($stMeta['cls']) . '">' . h($stMeta['label']) . '</span></td>';
            echo '<td>' . (int)$row['product_count'] . '</td>';
            echo '<td>' . (int)$row['participant_count'] . '</td>';
            echo '<td><strong>' . $total . ' €</strong></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        echo '</div>';
    }

    echo '</div>';
    layoutClose();
}

function viewMyData(array $user): void
{
    layoutOpen('Mes données', $user, 'my_data');
    $data = getUserData($user['id']);
    $err  = flash('error');
    $ok   = flash('success');

    echo '<div class="page">';
    echo '<div class="page-header"><h1>👤 Mon compte</h1><p>Gérez vos informations et vos données personnelles.</p></div>';
    if ($err) echo '<div class="alert alert-error">'   . h($err) . '</div>';
    if ($ok)  echo '<div class="alert alert-success">' . h($ok)  . '</div>';

    // Profil
    echo '<div class="section-box">';
    echo '<div class="section-box-header"><h2>Mon profil</h2></div>';
    echo '<div class="section-box-body">';
    echo '<table class="data-table">';
    echo '<tr><th>Prénom / pseudo</th><td>' . h($user['display_name']) . '</td></tr>';
    echo '<tr><th>Foyer</th><td>' . ($user['household'] ? h($user['household']) : '<em class="text-muted">non renseigné</em>') . '</td></tr>';
    echo '<tr><th>Rôle</th><td><span class="role-badge role-' . h($user['role']) . '">' . h($user['role']) . '</span></td></tr>';
    echo '<tr><th>Inscrit·e le</th><td>' . fmtDate($user['created_at']) . '</td></tr>';
    echo '<tr><th>Dernière connexion</th><td>' . ($user['last_login'] ? fmtDate($user['last_login']) : '—') . '</td></tr>';
    echo '<tr><th>Consentement RGPD</th><td>' . ($user['gdpr_consent_at'] ? fmtDate($user['gdpr_consent_at']) : '—') . '</td></tr>';
    echo '</table></div></div>';

    // Changer mot de passe
    echo '<div class="section-box">';
    echo '<div class="section-box-header"><h2>Changer de mot de passe</h2></div>';
    echo '<div class="section-box-body">';
    echo '<form method="post" action="?action=change_password" style="display:flex;flex-direction:column;gap:1rem;max-width:400px">';
    echo csrfField();
    echo '<div class="form-group"><label>Mot de passe actuel</label><input type="password" name="old_password" required></div>';
    echo '<div class="form-group"><label>Nouveau mot de passe</label><input type="password" name="new_password" required minlength="8"></div>';
    echo '<div><button type="submit" class="btn btn-primary btn-sm">Modifier</button></div>';
    echo '</form></div></div>';

    // Données stockées
    echo '<div class="section-box">';
    echo '<div class="section-box-header"><h2>Données stockées à votre sujet</h2>';
    echo '<span class="text-sm text-muted">Droit d\'accès — art. 15 RGPD</span></div>';
    echo '<div class="section-box-body">';

    echo '<p class="text-sm text-muted mt-1">Cartes créées : <strong>' . count($data['cards']) . '</strong></p>';
    echo '<p class="text-sm text-muted mt-1">Intérêts exprimés : <strong>' . count($data['interests']) . '</strong></p>';
    echo '<p class="text-sm text-muted mt-1">Votes de dates : <strong>' . count($data['date_poll_votes']) . '</strong></p>';
    echo '<p class="text-sm text-muted mt-1">Confirmations de présence : <strong>' . count($data['presences']) . '</strong></p>';
    echo '<p class="text-sm text-muted mt-1">Emprunts : <strong>' . count($data['loans']) . '</strong></p>';
    echo '<p class="text-sm text-muted mt-1" style="margin-top:.75rem">Aucune donnée n\'est partagée avec des tiers. Les données sont stockées sur le serveur de ' . h(RGPD_RESPONSABLE) . '.</p>';
    echo '</div></div>';

    // Suppression de compte
    echo '<div class="danger-zone">';
    echo '<h3>⚠️ Supprimer mon compte</h3>';
    echo '<p class="text-sm" style="margin-bottom:1rem;color:#5a1a1a">Vos cartes et contributions resteront visibles mais anonymisées. Cette action est irréversible.</p>';
    echo '<form method="post" action="?action=delete_account">';
    echo csrfField();
    echo '<div class="form-group" style="max-width:360px">';
    echo '<label>Tapez votre prénom « ' . h($user['display_name']) . ' » pour confirmer</label>';
    echo '<input type="text" name="confirm" required autocomplete="off" placeholder="' . h($user['display_name']) . '">';
    echo '</div><div style="margin-top:.75rem">';
    echo '<button type="submit" class="btn btn-danger btn-sm" onclick="return confirm(\'Supprimer définitivement votre compte ?\')">Supprimer mon compte</button>';
    echo '</div></form></div>';

    echo '</div>';
    layoutClose();
}
