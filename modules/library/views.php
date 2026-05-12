<?php
// ═══════════════════════════════════════════════════════════
//  Module Library — Vues
// ═══════════════════════════════════════════════════════════

function viewLibrary(array $user): void
{
    layoutOpen('Prêt-o-thèque', $user, 'library');
    $items = getLibraryItems();
    $err   = flash('error');
    $ok    = flash('success');

    echo '<div class="page">';
    echo '<div class="page-header flex-between">';
    echo '<div><h1>📚 Prêt-o-thèque</h1><p>Livres, outils et jeux à emprunter dans le groupe.</p></div>';
    echo '<button class="btn btn-primary" onclick="document.getElementById(\'lib-modal\').classList.add(\'open\')">+ Ajouter un objet</button>';
    echo '</div>';

    if ($err) echo '<div class="alert alert-error">'   . h($err) . '</div>';
    if ($ok)  echo '<div class="alert alert-success">' . h($ok)  . '</div>';

    // Grouper par catégorie + pré-charger les stats (pour les onglets)
    $bycat = [];
    foreach ($items as $it) {
        $bycat[$it['category']][] = $it;
    }
    $activeLoans = getActiveLoans();
    $topItems    = getTopItems(10);
    $topFiltered = array_filter($topItems, fn($i) => (int)$i['loan_count'] > 0);

    // Onglets de navigation
    echo '<div class="lib-tabs">';
    echo '<button class="lib-tab active" onclick="switchLibTab(\'\', this)">Tout';
    if (count($items) > 0) {
        echo ' <span style="opacity:.65">(' . count($items) . ')</span>';
    }
    echo '</button>';
    foreach (getLibCats() as $k => $m) {
        $cnt = count($bycat[$k] ?? []);
        if ($cnt === 0) continue;
        echo '<button class="lib-tab" onclick="switchLibTab(\'' . h($k) . '\', this)">'
            . $m['emoji'] . ' ' . $m['label']
            . ' <span style="opacity:.65">(' . $cnt . ')</span></button>';
    }
    if (!empty($activeLoans)) {
        echo '<button class="lib-tab" onclick="switchLibTab(\'__loans\', this)">📋 Emprunts'
            . ' <span style="opacity:.65">(' . count($activeLoans) . ')</span></button>';
    }
    if (!empty($topFiltered)) {
        echo '<button class="lib-tab" onclick="switchLibTab(\'__top\', this)">🏆 Top</button>';
    }
    echo '</div>';

    // Barre de filtres texte (masquée sur onglets Emprunts / Top)
    echo '<div class="filter-bar" id="lib-filter-bar">';
    echo '<input type="search" id="lib-search" placeholder="🔍 Rechercher titre, auteur, description…" oninput="filterLib()">';
    echo '<select id="lib-cat" onchange="filterLib()"><option value="">Toutes catégories</option>';
    foreach (getLibCats() as $k => $m) {
        echo '<option value="' . h($k) . '">' . $m['emoji'] . ' ' . $m['label'] . '</option>';
    }
    echo '</select>';
    echo '<select id="lib-status" onchange="filterLib()">';
    echo '<option value="">Tous statuts</option>';
    echo '<option value="avail">✓ Disponibles</option>';
    echo '<option value="taken">⏳ Empruntés / indisponibles</option>';
    echo '</select>';
    echo '<select id="lib-cond" onchange="filterLib()">';
    echo '<option value="">Toutes conditions</option>';
    echo '<option value="ok">OK</option>';
    echo '<option value="lost">❌ Perdus</option>';
    echo '<option value="broken">🔧 Cassés</option>';
    echo '</select>';
    echo '<button type="button" class="btn btn-ghost btn-sm" onclick="resetLib()" title="Réinitialiser">✕</button>';
    echo '</div>';
    echo '<div id="lib-empty" style="display:none;text-align:center;padding:2rem 0;color:var(--muted)">Aucun objet ne correspond aux filtres.</div>';

    if (empty($items)) {
        echo '<p class="text-muted" style="text-align:center;padding:3rem 0">Aucun objet pour l\'instant. Soyez le premier à proposer quelque chose !</p>';
    }

    foreach (getLibCats() as $cat => $meta) {
        if (empty($bycat[$cat])) {
            continue;
        }
        echo '<div class="section-box mt-2 lib-section" data-cat="' . h($cat) . '">';
        echo '<div class="section-box-header"><h2>' . $meta['emoji'] . ' ' . $meta['label'] . '</h2></div>';
        echo '<div class="section-box-body"><div class="lib-grid">';
        foreach ($bycat[$cat] as $it) {
            renderLibItem($it, $user);
        }
        echo '</div></div></div>';
    }

    // Modal ajout
    echo '<div class="modal-overlay" id="lib-modal">';
    echo '<div class="modal">';
    echo '<h3>Ajouter un objet</h3>';
    echo '<form method="post" action="?action=library_add">';
    echo csrfField();
    echo '<div style="display:flex;flex-direction:column;gap:1rem">';
    echo '<div class="form-group"><label>Catégorie</label><select name="category" id="add-cat" onchange="updateLibFields(this.value,\'add\')">';
    foreach (getLibCats() as $k => $m) {
        echo '<option value="' . h($k) . '">' . $m['emoji'] . ' ' . $m['label'] . '</option>';
    }
    echo '</select></div>';
    echo '<div class="form-group"><label>Titre *</label><input type="text" name="title" required></div>';
    echo '<div class="form-group"><label>Auteur / marque / info</label><input type="text" name="subtitle" placeholder="ex : Robin Hobb, DeWalt, Wingspan…"></div>';
    echo '<div class="form-group"><label>Description</label><textarea name="description" placeholder="État, édition, remarque…"></textarea></div>';
    echo '<div class="form-group"><label>Lien (URL)</label><input type="url" name="url" placeholder="https://…"></div>';
    // Champs jeux
    echo '<div id="add-game-fields" style="display:none;flex-direction:column;gap:1rem">';
    echo '<div class="grid-2">';
    echo '<div class="form-group"><label>Durée d\'une partie</label><input type="text" name="game_duration" placeholder="ex : 30–60 min"></div>';
    echo '<div class="form-group"><label>Âge minimum</label><input type="number" name="age_min" min="0" max="99" placeholder="ex : 8"></div>';
    echo '</div>';
    echo '<div class="grid-2">';
    echo '<div class="form-group"><label>Joueurs min</label><input type="number" name="player_min" min="1" max="99" placeholder="ex : 2"></div>';
    echo '<div class="form-group"><label>Joueurs max</label><input type="number" name="player_max" min="1" max="99" placeholder="ex : 6"></div>';
    echo '</div>';
    echo '</div>';
    // Champs livres
    echo '<div id="add-book-fields" style="display:none;flex-direction:column;gap:1rem">';
    echo '<div class="grid-2">';
    echo '<div class="form-group"><label>Genre / catégorie</label><input type="text" name="book_genre" placeholder="ex : Roman, BD, Essai…"></div>';
    echo '<div class="form-group"><label>Âge cible</label><input type="number" name="age_min" min="0" max="99" placeholder="ex : 12"></div>';
    echo '</div>';
    echo '</div>';
    echo '<div class="modal-actions"><button type="button" class="btn btn-ghost" onclick="document.getElementById(\'lib-modal\').classList.remove(\'open\')">Annuler</button><button type="submit" class="btn btn-primary">Ajouter</button></div>';
    echo '</div></form></div></div>';

    // Modal d'édition (unique, peuplé par JS)
    echo '<div class="modal-overlay" id="lib-edit-modal">';
    echo '<div class="modal">';
    echo '<h3>Modifier la fiche</h3>';
    echo '<form method="post" action="?action=library_edit">';
    echo csrfField();
    echo '<input type="hidden" name="item_id" id="edit-item-id">';
    echo '<div style="display:flex;flex-direction:column;gap:1rem">';
    echo '<div class="form-group"><label>Catégorie</label><select name="category" id="edit-cat" onchange="updateLibFields(this.value,\'edit\')">';
    foreach (getLibCats() as $k => $m) {
        echo '<option value="' . h($k) . '">' . $m['emoji'] . ' ' . $m['label'] . '</option>';
    }
    echo '</select></div>';
    echo '<div class="form-group"><label>Titre *</label><input type="text" name="title" id="edit-title" required></div>';
    echo '<div class="form-group"><label>Auteur / marque / info</label><input type="text" name="subtitle" id="edit-subtitle" placeholder="ex : Robin Hobb, DeWalt, Wingspan…"></div>';
    echo '<div class="form-group"><label>Description</label><textarea name="description" id="edit-description" placeholder="État, édition, remarque…"></textarea></div>';
    echo '<div class="form-group"><label>Lien (URL)</label><input type="url" name="url" id="edit-url" placeholder="https://…"></div>';
    // Champs jeux
    echo '<div id="edit-game-fields" style="display:none;flex-direction:column;gap:1rem">';
    echo '<div class="grid-2">';
    echo '<div class="form-group"><label>Durée d\'une partie</label><input type="text" name="game_duration" id="edit-game-duration" placeholder="ex : 30–60 min"></div>';
    echo '<div class="form-group"><label>Âge minimum</label><input type="number" name="age_min" id="edit-age-min-game" min="0" max="99" placeholder="ex : 8"></div>';
    echo '</div>';
    echo '<div class="grid-2">';
    echo '<div class="form-group"><label>Joueurs min</label><input type="number" name="player_min" id="edit-player-min" min="1" max="99" placeholder="ex : 2"></div>';
    echo '<div class="form-group"><label>Joueurs max</label><input type="number" name="player_max" id="edit-player-max" min="1" max="99" placeholder="ex : 6"></div>';
    echo '</div>';
    echo '</div>';
    // Champs livres
    echo '<div id="edit-book-fields" style="display:none;flex-direction:column;gap:1rem">';
    echo '<div class="grid-2">';
    echo '<div class="form-group"><label>Genre / catégorie</label><input type="text" name="book_genre" id="edit-book-genre" placeholder="ex : Roman, BD, Essai…"></div>';
    echo '<div class="form-group"><label>Âge cible</label><input type="number" name="age_min" id="edit-age-min-book" min="0" max="99" placeholder="ex : 12"></div>';
    echo '</div>';
    echo '</div>';
    echo '<div class="modal-actions"><button type="button" class="btn btn-ghost" onclick="document.getElementById(\'lib-edit-modal\').classList.remove(\'open\')">Annuler</button><button type="submit" class="btn btn-primary">Enregistrer</button></div>';
    echo '</div></form></div></div>';

    // Journal des emprunts en cours (onglet dédié, caché par défaut)
    if (!empty($activeLoans)) {
        echo '<div class="section-box mt-2" id="lib-panel-loans" style="display:none">';
        echo '<div class="section-box-header"><h2>📋 Journal des emprunts en cours</h2>';
        echo '<span class="text-sm text-muted">' . count($activeLoans) . ' emprunt' . (count($activeLoans) > 1 ? 's' : '') . '</span></div>';
        echo '<div style="overflow-x:auto"><table class="data-table">';
        echo '<thead><tr><th>Objet</th><th>Emprunté par</th><th>Depuis le</th><th>Durée</th><th>Retour prévu</th>';
        if ($user['role'] === 'admin') {
            echo '<th>Action</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($activeLoans as $loan) {
            $lcat    = getLibCat($loan['category']);
            $days    = (int) $loan['days_out'];
            $dLabel  = $days === 0 ? "Auj." : $days . ' j';
            $overdue = ($loan['due_date'] && $loan['due_date'] < date('Y-m-d')) ? ' style="color:#c0392b;font-weight:600"' : '';
            echo '<tr>';
            echo '<td>' . $lcat['emoji'] . ' ' . h($loan['item_title']) . '</td>';
            echo '<td>' . h($loan['borrower_name']) . '</td>';
            echo '<td>' . fmtDate($loan['loaned_at']) . '</td>';
            echo '<td' . $overdue . '>' . $dLabel . '</td>';
            echo '<td>' . ($loan['due_date'] ? fmtDate($loan['due_date']) : '—') . '</td>';
            if ($user['role'] === 'admin') {
                echo '<td><form method="post" action="?action=library_return">';
                echo csrfField();
                echo '<input type="hidden" name="loan_id" value="' . (int)$loan['id'] . '">';
                echo '<button type="submit" class="btn btn-ghost btn-sm">↩ Retour</button>';
                echo '</form></td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div></div>';
    }

    // Top des objets empruntés (onglet dédié, caché par défaut)
    if (!empty($topFiltered)) {
        echo '<div class="section-box mt-2" id="lib-panel-top" style="display:none">';
        echo '<div class="section-box-header"><h2>🏆 Top des objets empruntés</h2></div>';
        echo '<div style="overflow-x:auto"><table class="data-table">';
        echo '<thead><tr><th>#</th><th>Objet</th><th>Nb emprunts</th><th>Durée cumulée</th><th>État</th></tr></thead><tbody>';
        $rank = 1;
        foreach ($topFiltered as $top) {
            $tcat  = getLibCat($top['category']);
            $tcond = CONDITION_META[$top['condition'] ?? 'ok'] ?? CONDITION_META['ok'];
            $days  = (int) $top['total_days'];
            echo '<tr>';
            echo '<td><strong>' . $rank++ . '</strong></td>';
            echo '<td>' . $tcat['emoji'] . ' ' . h($top['title']) . '</td>';
            echo '<td>' . $top['loan_count'] . ' emprunt' . ($top['loan_count'] > 1 ? 's' : '') . '</td>';
            echo '<td>' . $days . ' jour' . ($days > 1 ? 's' : '') . '</td>';
            echo '<td><span class="cond-badge ' . $tcond['cls'] . '">' . $tcond['label'] . '</span></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div></div>';
    }

    echo <<<'JS'
<script>
/* ── Navigation bibliothèque ── */
let activeLibTab = '';

function switchLibTab(tab, btn) {
  activeLibTab = tab;
  document.querySelectorAll('.lib-tab').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  const isLoans = tab === '__loans';
  const isTop   = tab === '__top';
  const isCat   = !isLoans && !isTop;
  // Panneaux gestion
  const pLoans = document.getElementById('lib-panel-loans');
  const pTop   = document.getElementById('lib-panel-top');
  if (pLoans) pLoans.style.display = isLoans ? '' : 'none';
  if (pTop)   pTop.style.display   = isTop   ? '' : 'none';
  // Barre de filtre uniquement sur le catalogue
  document.getElementById('lib-filter-bar').style.display = isCat ? '' : 'none';
  document.getElementById('lib-empty').style.display = 'none';
  if (isCat) {
    document.getElementById('lib-cat').value = (tab && !tab.startsWith('__')) ? tab : '';
    filterLib();
  } else {
    document.querySelectorAll('.lib-section').forEach(s => s.style.display = 'none');
  }
}

function filterLib() {
  const q      = (document.getElementById('lib-search').value  || '').toLowerCase();
  const cat    = document.getElementById('lib-cat').value;
  const status = document.getElementById('lib-status').value;
  const cond   = document.getElementById('lib-cond').value;
  let anyVisible = false;
  document.querySelectorAll('.lib-card').forEach(card => {
    const title = card.dataset.title || '';
    const sub   = card.dataset.sub   || '';
    const desc  = card.dataset.desc  || '';
    const ok = (!q      || title.includes(q) || sub.includes(q) || desc.includes(q))
            && (!cat    || card.dataset.cat    === cat)
            && (!status || card.dataset.status === status)
            && (!cond   || card.dataset.cond   === cond);
    card.style.display = ok ? '' : 'none';
    if (ok) anyVisible = true;
  });
  // Masquer les sections vides (en respectant l'onglet actif)
  document.querySelectorAll('.lib-section').forEach(sec => {
    const tabOk      = !activeLibTab || sec.dataset.cat === activeLibTab;
    const hasVisible = [...sec.querySelectorAll('.lib-card')].some(c => c.style.display !== 'none');
    sec.style.display = (tabOk && hasVisible) ? '' : 'none';
  });
  document.getElementById('lib-empty').style.display = anyVisible ? 'none' : 'block';
}

function resetLib() {
  document.getElementById('lib-search').value = '';
  document.getElementById('lib-cat').value    = '';
  document.getElementById('lib-status').value = '';
  document.getElementById('lib-cond').value   = '';
  filterLib();
}

document.getElementById('lib-modal').addEventListener('click', function(e){
  if(e.target===this)this.classList.remove('open');
});
document.getElementById('lib-edit-modal').addEventListener('click', function(e){
  if(e.target===this)this.classList.remove('open');
});

function updateLibFields(cat, prefix) {
  const gameFields = document.getElementById(prefix + '-game-fields');
  const bookFields = document.getElementById(prefix + '-book-fields');
  const isGame = cat === 'jeu';
  const isBook = cat === 'livre';
  if (gameFields) {
    gameFields.style.display = isGame ? 'flex' : 'none';
    gameFields.querySelectorAll('input,select,textarea').forEach(el => el.disabled = !isGame);
  }
  if (bookFields) {
    bookFields.style.display = isBook ? 'flex' : 'none';
    bookFields.querySelectorAll('input,select,textarea').forEach(el => el.disabled = !isBook);
  }
}
// initialiser l'état des champs au chargement
document.addEventListener('DOMContentLoaded', function() {
  const addCat = document.getElementById('add-cat');
  if (addCat) updateLibFields(addCat.value, 'add');
});

function openLibEdit(btn) {
  const card = btn.closest('.lib-card');
  const d = JSON.parse(card.dataset.item);
  document.getElementById('edit-item-id').value        = d.id;
  document.getElementById('edit-cat').value            = d.category;
  document.getElementById('edit-title').value          = d.title;
  document.getElementById('edit-subtitle').value       = d.subtitle;
  document.getElementById('edit-description').value    = d.description;
  document.getElementById('edit-url').value            = d.url;
  document.getElementById('edit-game-duration').value  = d.game_duration;
  document.getElementById('edit-age-min-game').value   = (d.category === 'jeu'   && d.age_min) ? d.age_min : '';
  document.getElementById('edit-age-min-book').value   = (d.category === 'livre' && d.age_min) ? d.age_min : '';
  document.getElementById('edit-player-min').value     = d.player_min;
  document.getElementById('edit-player-max').value     = d.player_max;
  document.getElementById('edit-book-genre').value     = d.book_genre;
  updateLibFields(d.category, 'edit');
  document.getElementById('lib-edit-modal').classList.add('open');
}
</script>
JS;

    echo '</div>';
    layoutClose();
}

function renderLibItem(array $it, array $user): void
{
    $cat       = getLibCat($it['category']);
    $condition = $it['condition'] ?? 'ok';
    $cond      = CONDITION_META[$condition] ?? CONDITION_META['ok'];
    $avail     = (bool) $it['available'];
    $canAct    = $it['owner_id'] == $user['id'] || $user['role'] === 'admin';
    $totalDays = (int)($it['total_days'] ?? 0);

    if ($condition === 'lost') {
        $statusCls   = 'taken';
        $statusLabel = '❌ Perdu';
    } elseif ($condition === 'broken') {
        $statusCls   = 'taken';
        $statusLabel = '🔧 Cassé / hors service';
    } elseif (!$avail) {
        $since       = $it['loaned_at'] ? ' depuis le ' . fmtDate($it['loaned_at']) : '';
        $statusCls   = 'taken';
        $statusLabel = '⏳ Emprunté par ' . h($it['borrower_name'] ?? '…') . $since;
    } else {
        $statusCls   = 'avail';
        $statusLabel = '✓ Disponible';
    }

    $dataStatus = ($avail && $condition === 'ok') ? 'avail' : 'taken';
    $itemJson   = htmlspecialchars(json_encode([
        'id'            => (int)$it['id'],
        'category'      => $it['category'],
        'title'         => $it['title'],
        'subtitle'      => $it['subtitle']      ?? '',
        'description'   => $it['description']   ?? '',
        'url'           => $it['url']           ?? '',
        'game_duration' => $it['game_duration'] ?? '',
        'age_min'       => $it['age_min']       ?? '',
        'player_min'    => $it['player_min']    ?? '',
        'player_max'    => $it['player_max']    ?? '',
        'book_genre'    => $it['book_genre']    ?? '',
    ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    echo '<div class="lib-card"'
        . ' data-title="' . h(mb_strtolower($it['title']))           . '"'
        . ' data-sub="'   . h(mb_strtolower($it['subtitle']   ?? '')) . '"'
        . ' data-desc="'  . h(mb_strtolower($it['description'] ?? '')) . '"'
        . ' data-cat="'   . h($it['category'])  . '"'
        . ' data-status="'. $dataStatus          . '"'
        . ' data-cond="'  . h($condition)        . '"'
        . ' data-item="'  . $itemJson            . '"'
        . '>';
    echo '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:.3rem;margin-bottom:.25rem">';
    echo '<div class="lib-card-cat">' . $cat['emoji'] . ' ' . $cat['label'] . '</div>';
    if ($condition !== 'ok') {
        echo '<span class="cond-badge ' . $cond['cls'] . '">' . $cond['label'] . '</span>';
    }
    echo '</div>';
    echo '<div class="lib-card-title">' . h($it['title']) . '</div>';
    if ($it['subtitle']) {
        echo '<div class="lib-card-sub">' . h($it['subtitle']) . '</div>';
    }
    if ($it['description']) {
        echo '<div class="lib-card-desc">' . h($it['description']) . '</div>';
    }
    echo '<span class="lib-status ' . $statusCls . '">' . $statusLabel . '</span>';
    if ($it['owner_name']) {
        echo '<div class="text-sm text-muted mt-1">🏠 Propriétaire : ' . h($it['owner_name']) . '</div>';
    }
    if ($totalDays > 0) {
        echo '<div class="text-sm text-muted mt-1">⏱ ' . $totalDays . ' jour' . ($totalDays > 1 ? 's' : '') . ' d\'emprunt cumulé' . ($totalDays > 1 ? 's' : '') . '</div>';
    }

    // Métadonnées enrichies
    $metaLines = [];
    if (!empty($it['url'])) {
        $safeUrl   = htmlspecialchars($it['url'], ENT_QUOTES, 'UTF-8');
        $metaLines[] = '🔗 <a href="' . $safeUrl . '" target="_blank" rel="noopener noreferrer" style="color:var(--col-1)">Voir la fiche</a>';
    }
    if (!empty($it['game_duration'])) {
        $metaLines[] = '⏰ ' . h($it['game_duration']);
    }
    $ageMin = isset($it['age_min']) && $it['age_min'] !== null && $it['age_min'] !== '' ? (int)$it['age_min'] : null;
    if ($ageMin !== null) {
        $metaLines[] = '🎂 Dès ' . $ageMin . ' ans';
    }
    $pMin = isset($it['player_min']) && $it['player_min'] !== null && $it['player_min'] !== '' ? (int)$it['player_min'] : null;
    $pMax = isset($it['player_max']) && $it['player_max'] !== null && $it['player_max'] !== '' ? (int)$it['player_max'] : null;
    if ($pMin !== null) {
        $pRange      = $pMin . ($pMax !== null && $pMax !== $pMin ? '–' . $pMax : '+');
        $metaLines[] = '👥 ' . $pRange . ' joueur' . ($pMin > 1 || $pMax > 1 ? 's' : '');
    }
    if (!empty($it['book_genre'])) {
        $metaLines[] = '📖 ' . h($it['book_genre']);
    }
    foreach ($metaLines as $ml) {
        echo '<div class="text-sm text-muted mt-1">' . $ml . '</div>';
    }

    echo '<div class="lib-card-actions">';
    if ($canAct) {
        echo '<button type="button" class="btn btn-ghost btn-sm" onclick="openLibEdit(this)" title="Modifier la fiche">✏️</button>';
    }

    if ($avail && $it['owner_id'] != $user['id'] && $condition === 'ok') {
        echo '<form method="post" action="?action=library_borrow">';
        echo csrfField();
        echo '<input type="hidden" name="item_id" value="' . (int)$it['id'] . '">';
        echo '<input type="date" name="due_date" title="Date de retour prévue" style="font-size:.78rem;padding:.25rem .5rem;border:1px solid var(--border);border-radius:5px;background:var(--bg)">';
        echo '<button type="submit" class="btn btn-primary btn-sm" style="margin-top:.3rem">📥 Emprunter</button>';
        echo '</form>';
    }

    if (!$avail && $it['borrower_id'] == $user['id'] && $condition === 'ok') {
        echo '<form method="post" action="?action=library_return">';
        echo csrfField();
        echo '<input type="hidden" name="loan_id" value="' . (int)$it['loan_id'] . '">';
        echo '<button type="submit" class="btn btn-ghost btn-sm">📤 Retourner</button>';
        echo '</form>';
    }

    if ($canAct) {
        if (!$avail && $condition === 'ok') {
            echo '<form method="post" action="?action=library_return">';
            echo csrfField();
            echo '<input type="hidden" name="loan_id" value="' . (int)$it['loan_id'] . '">';
            echo '<button type="submit" class="btn btn-ghost btn-sm">↩ Retour forcé</button>';
            echo '</form>';
        }
        if ($condition === 'ok') {
            echo '<form method="post" action="?action=library_condition" style="display:inline">';
            echo csrfField();
            echo '<input type="hidden" name="item_id" value="' . (int)$it['id'] . '">';
            echo '<input type="hidden" name="condition" value="lost">';
            echo '<button type="submit" class="btn btn-ghost btn-sm" style="color:#9c3a3a;border-color:#f5b7b1" onclick="return confirm(\'Marquer comme perdu ?\')">❌ Perdu</button>';
            echo '</form>';
            echo '<form method="post" action="?action=library_condition" style="display:inline">';
            echo csrfField();
            echo '<input type="hidden" name="item_id" value="' . (int)$it['id'] . '">';
            echo '<input type="hidden" name="condition" value="broken">';
            echo '<button type="submit" class="btn btn-ghost btn-sm" style="color:#7d5800;border-color:#ffe69c" onclick="return confirm(\'Marquer comme cassé ?\')">🔧 Cassé</button>';
            echo '</form>';
        } else {
            echo '<form method="post" action="?action=library_condition" style="display:inline">';
            echo csrfField();
            echo '<input type="hidden" name="item_id" value="' . (int)$it['id'] . '">';
            echo '<input type="hidden" name="condition" value="ok">';
            echo '<button type="submit" class="btn btn-ghost btn-sm">↺ Réactiver</button>';
            echo '</form>';
        }
        echo '<form method="post" action="?action=library_delete" style="margin-left:auto">';
        echo csrfField();
        echo '<input type="hidden" name="item_id" value="' . (int)$it['id'] . '">';
        echo '<button type="submit" class="btn btn-ghost btn-sm" style="color:#c0392b;border-color:#f5b7b1" onclick="return confirm(\'Supprimer cet objet ?\')">✕</button>';
        echo '</form>';
    }

    echo '</div></div>';
}
