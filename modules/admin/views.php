<?php
// ═══════════════════════════════════════════════════════════
//  Module Admin — Vue
// ═══════════════════════════════════════════════════════════

function viewAdmin(array $user): void
{
    $user = requireAdmin();
    cleanExpiredResetTokens();
    layoutOpen('Administration', $user, 'admin');
    $users        = getAllUsers();
    $resetRequests = getPendingResetRequests();
    $err   = flash('error');
    $ok    = flash('success');

    // ── Lien de reset généré à afficher une seule fois
    $generatedUrl = null;
    if ($ok && str_starts_with($ok, 'reset_url:')) {
        $generatedUrl = substr($ok, 10);
        $ok = null;
    }

    echo '<div class="page">';
    echo '<div class="page-header"><h1>⚙️ Administration</h1><p>Gestion des comptes et modération.</p></div>';
    if ($err) echo '<div class="alert alert-error">'   . h($err) . '</div>';
    if ($ok)  echo '<div class="alert alert-success">' . h($ok)  . '</div>';
    if ($generatedUrl) {
        echo '<div class="alert alert-success" style="word-break:break-all">';
        echo '<strong>Lien de réinitialisation généré (valable 1 h) :</strong><br>';
        echo '<code style="font-size:.85rem;user-select:all">' . h($generatedUrl) . '</code><br>';
        echo '<button class="btn btn-ghost btn-sm" style="margin-top:.4rem" onclick="navigator.clipboard.writeText(' . json_encode($generatedUrl) . ').then(()=>{this.textContent=\'✅ Copié !\';setTimeout(()=>this.textContent=\'📋 Copier\',2000)})">📋 Copier</button>';
        echo '</div>';
    }

    // ── Demandes de réinitialisation en attente (créées par les utilisateurs)
    if ($resetRequests) {
        $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
                 . strtok($_SERVER['REQUEST_URI'], '?');
        echo '<div class="section-box" style="margin-bottom:1.5rem;border-left:3px solid #e07b39">';
        echo '<div class="section-box-header"><h2>🔑 Demandes de réinitialisation (' . count($resetRequests) . ')</h2>';
        echo '<span class="text-sm text-muted">Copiez et transmettez le lien à l\'utilisateur concerné — valable 1 heure</span></div>';
        echo '<div style="overflow-x:auto"><table class="data-table">';
        echo '<thead><tr><th>Utilisateur</th><th>Demandé le</th><th>Lien (à copier)</th></tr></thead><tbody>';
        foreach ($resetRequests as $req) {
            $resetUrl = $baseUrl . '?action=reset_password&token=' . rawurlencode($req['token']);
            echo '<tr><td><strong>' . h($req['display_name']) . '</strong></td>';
            echo '<td>' . fmtDate($req['created_at']) . '</td>';
            echo '<td style="max-width:320px">';
            echo '<code style="font-size:.75rem;word-break:break-all;user-select:all">' . h($resetUrl) . '</code><br>';
            echo '<button class="btn btn-ghost btn-sm" style="margin-top:.3rem" onclick="navigator.clipboard.writeText(' . json_encode($resetUrl) . ').then(()=>{this.textContent=\'✅ Copié !\';setTimeout(()=>this.textContent=\'📋 Copier\',2000)})">📋 Copier</button>';
            echo '</td></tr>';
        }
        echo '</tbody></table></div></div>';
    }

    // ── Identité de l'application
    echo '<div class="section-box" id="identity" style="margin-bottom:1.5rem">';
    echo '<div class="section-box-header"><h2>🏷️ Identité de l\'application</h2>';
    echo '<span class="text-sm text-muted">Nom et sous-titre affichés sur la page de connexion et dans l\'onglet navigateur</span></div>';
    echo '<div style="padding:1rem">';
    echo '<form method="post" action="?action=admin_settings" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">';
    echo csrfField();
    echo '<div class="form-group" style="margin-bottom:0;flex:1;min-width:180px"><label style="font-size:.78rem;font-weight:600">Nom de l\'application *</label>';
    echo '<input type="text" name="app_name" required maxlength="80" value="' . h(getSetting('app_name', APP_NAME)) . '" placeholder="ex : Sharehood" style="font-size:.9rem;padding:.45rem .7rem;border:1px solid var(--border);border-radius:4px;background:var(--bg);width:100%"></div>';
    echo '<div class="form-group" style="margin-bottom:0;flex:2;min-width:220px"><label style="font-size:.78rem;font-weight:600">Sous-titre / nom du groupe</label>';
    echo '<input type="text" name="app_subtitle" maxlength="120" value="' . h(getSetting('app_subtitle', APP_SUBTITLE)) . '" placeholder="ex : L\'Étoile de Terre" style="font-size:.9rem;padding:.45rem .7rem;border:1px solid var(--border);border-radius:4px;background:var(--bg);width:100%"></div>';
    echo '<button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end;padding:.5rem 1rem;white-space:nowrap">Enregistrer</button>';
    echo '</form></div></div>';

    echo '<div class="section-box">';
    echo '<div class="section-box-header"><h2>Utilisateurs (' . count($users) . ')</h2></div>';
    echo '<div style="overflow-x:auto"><table class="data-table">';
    echo '<thead><tr><th>Prénom / pseudo</th><th>Foyer</th><th>Rôle</th><th>Inscrit·e</th><th>Dernière connexion</th><th>Statut</th><th>Actions</th></tr></thead>';
    echo '<tbody>';

    foreach ($users as $u) {
        $isSelf  = $u['id'] == $user['id'];
        $active  = $u['is_active'] ? '✅ Actif' : '🔒 Désactivé';
        echo '<tr>';
        echo '<td><strong>' . h($u['display_name']) . '</strong></td>';
        echo '<td>' . ($u['household'] ? h($u['household']) : '<em class="text-muted">—</em>') . '</td>';
        echo '<td>';

        if (!$isSelf) {
            echo '<form method="post" action="?action=admin_set_role" style="display:inline">';
            echo csrfField();
            echo '<input type="hidden" name="user_id" value="' . (int)$u['id'] . '">';
            echo '<select name="role" onchange="this.form.submit()" style="font-size:.8rem;padding:.2rem .4rem;border:1px solid var(--border);border-radius:4px;background:var(--bg)">';
            foreach (['admin', 'member', 'external'] as $r) {
                $sel = $u['role'] === $r ? 'selected' : '';
                echo '<option value="' . $r . '" ' . $sel . '>' . $r . '</option>';
            }
            echo '</select></form>';
        } else {
            echo '<span class="role-badge role-' . h($u['role']) . '">' . h($u['role']) . '</span>';
        }
        echo '</td>';
        echo '<td>' . fmtDate($u['created_at']) . '</td>';
        echo '<td>' . ($u['last_login'] ? fmtDate($u['last_login']) : '—') . '</td>';
        echo '<td>' . $active . '</td>';
        echo '<td>';

        if (!$isSelf) {
            echo '<div style="display:flex;gap:.3rem;flex-wrap:wrap;align-items:flex-start">';
            // Toggle actif
            echo '<form method="post" action="?action=admin_toggle_user" style="display:inline">';
            echo csrfField();
            echo '<input type="hidden" name="user_id" value="' . (int)$u['id'] . '">';
            $toggleLabel = $u['is_active'] ? '🔒' : '✅';
            $toggleTitle = $u['is_active'] ? 'Désactiver' : 'Réactiver';
            echo '<button type="submit" class="btn btn-ghost btn-sm" title="' . $toggleTitle . '">' . $toggleLabel . '</button>';
            echo '</form>';
            // Supprimer
            echo '<form method="post" action="?action=admin_delete_user" style="display:inline">';
            echo csrfField();
            echo '<input type="hidden" name="user_id" value="' . (int)$u['id'] . '">';
            echo '<button type="submit" class="btn btn-ghost btn-sm" style="color:#c0392b;border-color:#f5b7b1" ';
            echo 'title="Supprimer" onclick="return confirm(\'Supprimer ce compte ? Le contenu sera anonymisé.\')">✕</button>';
            echo '</form>';
            // Réinitialiser mot de passe
            $uid = (int)$u['id'];
            echo '<details style="position:relative">';
            echo '<summary class="btn btn-ghost btn-sm" title="Réinitialiser le mot de passe" style="cursor:pointer;list-style:none">🔑</summary>';
            echo '<div style="position:absolute;right:0;top:2rem;z-index:10;background:var(--card-bg);border:1px solid var(--border);border-radius:6px;padding:.75rem;box-shadow:0 4px 12px rgba(0,0,0,.12);min-width:230px;display:flex;flex-direction:column;gap:.6rem">';
            // Option A : définir un nouveau mot de passe directement
            echo '<form method="post" action="?action=admin_reset_password">';
            echo csrfField();
            echo '<input type="hidden" name="user_id" value="' . $uid . '">';
            echo '<div class="form-group" style="margin-bottom:.4rem"><label style="font-size:.78rem;font-weight:600">Définir un mot de passe</label>';
            echo '<input type="password" name="new_password" required minlength="8" placeholder="8 car. min." style="font-size:.82rem;padding:.3rem .5rem"></div>';
            echo '<button type="submit" class="btn btn-primary btn-sm" style="width:100%">Appliquer</button>';
            echo '</form>';
            // Option B : générer un lien de reset
            echo '<hr style="border:0;border-top:1px solid var(--border)">';
            echo '<form method="post" action="?action=admin_generate_reset">';
            echo csrfField();
            echo '<input type="hidden" name="user_id" value="' . $uid . '">';
            echo '<button type="submit" class="btn btn-ghost btn-sm" style="width:100%">🔗 Générer un lien (1 h)</button>';
            echo '</form>';
            echo '</div></details>';
            echo '</div>';
        } else {
            echo '<em class="text-muted text-sm">Vous</em>';
        }
        echo '</td></tr>';
    }

    echo '</tbody></table></div></div>';

    // ── Catégories de la prêt-o-thèque
    $libCats = getDB()->query('SELECT id, slug, emoji, label, sort_order FROM lib_categories ORDER BY sort_order ASC, label ASC')->fetchAll();
    echo '<div class="section-box" id="lib-cats" style="margin-top:1.5rem">';
    echo '<div class="section-box-header"><h2>📚 Catégories prêt-o-thèque (' . count($libCats) . ')</h2>';
    echo '<span class="text-sm text-muted">Gérez les catégories disponibles dans la prêt-o-thèque</span></div>';
    echo '<div style="overflow-x:auto"><table class="data-table">';
    echo '<thead><tr><th>Emoji</th><th>Libellé</th><th>Slug</th><th>Objets</th><th>Actions</th></tr></thead><tbody>';
    foreach ($libCats as $lc) {
        $db       = getDB();
        $countStmt = $db->prepare('SELECT COUNT(*) FROM library_items WHERE category = ?');
        $countStmt->execute([$lc['slug']]);
        $nbItems = (int) $countStmt->fetchColumn();
        $isProtected = $lc['slug'] === 'autre';
        echo '<tr>';
        echo '<td style="font-size:1.3rem;text-align:center">' . h($lc['emoji']) . '</td>';
        echo '<td><strong>' . h($lc['label']) . '</strong></td>';
        echo '<td><code style="font-size:.82rem">' . h($lc['slug']) . '</code></td>';
        echo '<td>' . $nbItems . '</td>';
        echo '<td><div style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:flex-start">';
        // Formulaire d'édition inline
        echo '<details style="position:relative">';
        echo '<summary class="btn btn-ghost btn-sm" style="cursor:pointer;list-style:none">✏️</summary>';
        echo '<div style="position:absolute;left:0;top:2rem;z-index:10;background:var(--card-bg);border:1px solid var(--border);border-radius:6px;padding:.75rem;box-shadow:0 4px 12px rgba(0,0,0,.12);min-width:220px;display:flex;flex-direction:column;gap:.5rem">';
        echo '<form method="post" action="?action=admin_lib_cat_edit" style="display:flex;flex-direction:column;gap:.5rem">';
        echo csrfField();
        echo '<input type="hidden" name="cat_id" value="' . (int)$lc['id'] . '">';
        echo '<div class="form-group" style="margin-bottom:0"><label style="font-size:.78rem;font-weight:600">Emoji</label>';
        echo '<input type="text" name="emoji" value="' . h($lc['emoji']) . '" maxlength="8" style="font-size:.88rem;padding:.3rem .5rem;width:80px"></div>';
        echo '<div class="form-group" style="margin-bottom:0"><label style="font-size:.78rem;font-weight:600">Libellé</label>';
        echo '<input type="text" name="label" value="' . h($lc['label']) . '" required maxlength="50" style="font-size:.88rem;padding:.3rem .5rem"></div>';
        echo '<button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>';
        echo '</form></div></details>';
        // Supprimer
        if (!$isProtected) {
            echo '<form method="post" action="?action=admin_lib_cat_delete" style="display:inline">';
            echo csrfField();
            echo '<input type="hidden" name="cat_id" value="' . (int)$lc['id'] . '">';
            $confirmMsg = $nbItems > 0
                ? "Cette catégorie contient {$nbItems} objet(s). Impossible de la supprimer."
                : "Supprimer la catégorie « {$lc['label']} » ?";
            echo '<button type="submit" class="btn btn-ghost btn-sm" style="color:#c0392b;border-color:#f5b7b1"';
            if ($nbItems > 0) echo ' disabled title="' . h($confirmMsg) . '"';
            else              echo ' onclick="return confirm(\'' . h($confirmMsg) . '\')" title="Supprimer"';
            echo '>✕</button>';
            echo '</form>';
        } else {
            echo '<span class="text-sm text-muted" title="Catégorie par défaut, non supprimable">🔒</span>';
        }
        echo '</div></td></tr>';
    }
    echo '</tbody></table></div>';
    // Formulaire d'ajout
    echo '<div style="padding:1rem;border-top:1px solid var(--border)">';
    echo '<h3 style="font-size:.9rem;font-weight:600;margin-bottom:.75rem">Ajouter une catégorie</h3>';
    echo '<form method="post" action="?action=admin_lib_cat_add" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end">';
    echo csrfField();
    echo '<div class="form-group" style="margin-bottom:0"><label style="font-size:.78rem;font-weight:600">Emoji</label>';
    echo '<input type="text" name="emoji" placeholder="📦" maxlength="8" style="width:70px;font-size:.88rem;padding:.4rem .6rem;border:1px solid var(--border);border-radius:4px;background:var(--bg)"></div>';
    echo '<div class="form-group" style="margin-bottom:0"><label style="font-size:.78rem;font-weight:600">Libellé *</label>';
    echo '<input type="text" name="label" required maxlength="50" placeholder="ex : Vêtements" style="width:160px;font-size:.88rem;padding:.4rem .6rem;border:1px solid var(--border);border-radius:4px;background:var(--bg)"></div>';
    echo '<div class="form-group" style="margin-bottom:0"><label style="font-size:.78rem;font-weight:600">Slug * <span style="font-weight:400;text-transform:none">(a-z, 0-9, _)</span></label>';
    echo '<input type="text" name="slug" required maxlength="30" placeholder="ex : vetements" pattern="[a-z0-9_]+" style="width:140px;font-size:.88rem;padding:.4rem .6rem;border:1px solid var(--border);border-radius:4px;background:var(--bg)"></div>';
    echo '<button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end;padding:.45rem .9rem">+ Ajouter</button>';
    echo '</form></div>';
    echo '</div>';

    // Info RGPD
    echo '<div class="section-box" style="margin-top:1.5rem">';
    echo '<div class="section-box-header"><h2>Registre de traitement</h2></div>';
    echo '<div class="section-box-body">';
    echo '<table class="data-table">';
    echo '<tr><th>Responsable</th><td>' . h(RGPD_RESPONSABLE) . '</td></tr>';
    echo '<tr><th>Contact</th><td>' . h(RGPD_CONTACT) . '</td></tr>';
    echo '<tr><th>Finalité</th><td>Organisation d\'activités de convivialité pour les membres de l\'habitat</td></tr>';
    echo '<tr><th>Base légale</th><td>Consentement (art. 6.1.a RGPD)</td></tr>';
    echo '<tr><th>Données collectées</th><td>Prénom ou pseudonyme, nom de foyer (facultatif), mot de passe haché</td></tr>';
    echo '<tr><th>Durée de conservation</th><td>Jusqu\'à suppression du compte par l\'utilisateur ou l\'administrateur</td></tr>';
    echo '<tr><th>Hébergement</th><td>Serveur privé — aucun transfert hors UE</td></tr>';
    echo '</table></div></div>';

    echo '</div>';
    layoutClose();
}
