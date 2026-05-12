<?php
// ═══════════════════════════════════════════════════════════
//  Module Board — Vues
// ═══════════════════════════════════════════════════════════

function viewBoard(array $user): void
{
    layoutOpen('Tableau', $user, 'board');
    $cards  = getCards();
    $err    = flash('error');
    $ok     = flash('success');

    echo '<div class="rule-banner">« Personne n\'est obligé de rien, mais tout le monde peut proposer. »</div>';
    echo '<div class="page">';

    if ($err) echo '<div class="alert alert-error">'   . h($err) . '</div>';
    if ($ok)  echo '<div class="alert alert-success">' . h($ok)  . '</div>';

    // Événements pour le calendrier (col 1, planifiée)
    $calEvents = [];
    foreach ($cards[1] ?? [] as $c) {
        if (($c['status'] ?? '') === 'planifiee' && !empty($c['event_date'])) {
            $calEvents[] = ['id' => (int)$c['id'], 'date' => $c['event_date'], 'title' => $c['title'], 'type' => 'card'];
        }
    }
    // Achats groupés ouverts avec date limite
    foreach (getGroupOrders() as $go) {
        if (!empty($go['deadline']) && $go['status'] !== 'closed') {
            $calEvents[] = [
                'id'    => (int)$go['id'],
                'date'  => $go['deadline'],
                'title' => '🛒 ' . $go['title'],
                'type'  => 'order',
            ];
        }
    }

    // Barre de filtres
    echo '<div class="filter-bar">';
    echo '<input type="search" id="board-search" placeholder="🔍 Rechercher…" oninput="filterBoard()">';
    echo '<select id="board-tag" onchange="filterBoard()"><option value="">Toutes catégories</option>';
    foreach (TAG_META as $k => $t) {
        echo '<option value="' . h($k) . '">' . $t['emoji'] . ' ' . $t['label'] . '</option>';
    }
    echo '</select>';
    echo '<select id="board-aud" onchange="filterBoard()"><option value="">Tous publics</option>';
    foreach (AUDIENCE_META as $k => $v) {
        echo '<option value="' . h($k) . '">' . $v . '</option>';
    }
    echo '</select>';
    echo '<button type="button" class="btn btn-ghost btn-sm" onclick="resetBoard()" title="Réinitialiser les filtres">✕</button>';
    echo '<span class="spacer"></span>';
    echo '<button type="button" class="btn btn-ghost btn-sm" id="cal-btn" onclick="toggleCalendar()">📅 Calendrier</button>';
    echo '</div>';

    // Calendrier (caché par défaut)
    echo '<div id="cal-wrap" class="cal-wrap" style="display:none">';
    echo '<div class="cal-nav">';
    echo '<button type="button" class="btn btn-ghost btn-sm" onclick="calNav(-1)">◀</button>';
    echo '<h3 id="cal-title"></h3>';
    echo '<button type="button" class="btn btn-ghost btn-sm" onclick="calNav(1)">▶</button>';
    echo '</div>';
    echo '<div class="cal-grid" id="cal-grid"></div>';
    echo '</div>';

    echo '<div class="board">';

    $allComments = getAllComments();
    foreach (COL_META as $colId => $col) {
        $colCards = $cards[$colId] ?? [];
        $cls      = 'col-' . $colId;
        $count    = count($colCards);
        echo <<<HTML
<div class="column {$cls}">
  <div class="col-header">
    <h2>{$col['icon']} {$col['title']} <span class="badge">{$count}</span></h2>
  </div>
  <p class="col-desc">{$col['desc']}</p>
  <div class="cards-list">
HTML;
        foreach ($colCards as $card) {
            renderCard($card, $user, $colId, $allComments[(int)$card['id']] ?? []);
        }
        echo '</div>';

        // Bouton ajout
        echo '<div class="col-footer">';
        echo '<button class="btn btn-dashed" onclick="openAddModal(' . $colId . ')">+ Ajouter</button>';
        echo '</div></div>';
    }

    echo '</div>'; // .board

    // Modal ajout de carte
    renderCardModal();

    echo '</div>'; // .page

    // Données événements pour le calendrier JS
    $evJson = json_encode($calEvents, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
    echo '<script>const CAL_EVENTS=' . $evJson . ';</script>';

    echo <<<'JS'
<script>
/* ── Utilitaires ── */
function toggleEl(id, btn) {
  const el = document.getElementById(id);
  if (!el) return;
  const isHidden = el.style.display === 'none' || el.style.display === '';
  el.style.display = isHidden ? 'block' : 'none';
  if (btn) btn.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
}

/* ── Filtres tableau ── */
let calFilterActive = false;

function filterBoard() {
  const q   = (document.getElementById('board-search').value || '').toLowerCase();
  const tag = document.getElementById('board-tag').value;
  const aud = document.getElementById('board-aud').value;
  const calIds = calFilterActive ? new Set(CAL_EVENTS.map(e => String(e.id))) : null;
  document.querySelectorAll('.card').forEach(card => {
    const title = (card.dataset.title || '').toLowerCase();
    const body  = (card.dataset.body  || '').toLowerCase();
    const ok = (!q      || title.includes(q) || body.includes(q))
            && (!tag    || card.dataset.tag      === tag)
            && (!aud    || card.dataset.audience === aud)
            && (!calIds || calIds.has(card.dataset.id));
    card.style.display = ok ? '' : 'none';
  });
}
function resetBoard() {
  document.getElementById('board-search').value = '';
  document.getElementById('board-tag').value    = '';
  document.getElementById('board-aud').value    = '';
  filterBoard();
}

/* ── Calendrier ── */
const MONTHS_FR = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
const DAYS_FR   = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];
let calY = new Date().getFullYear(), calM = new Date().getMonth();

function toggleCalendar() {
  const w     = document.getElementById('cal-wrap');
  const shown = w.style.display !== 'none';
  w.style.display = shown ? 'none' : 'block';
  document.getElementById('cal-btn').classList.toggle('active', !shown);
  calFilterActive = !shown;
  // Quand le calendrier s'ouvre : filtrer le board sur les seules cartes planifiées
  if (!shown) {
    renderCal();
    filterBoard(); // masque tout sauf les événements du calendrier
  } else {
    // Quand il se ferme : lever le filtre et retirer les highlights
    document.querySelectorAll('.card.cal-focus').forEach(c => c.classList.remove('cal-focus'));
    filterBoard(); // re-applique seulement la barre de filtre texte/tag/public
  }
}
function calNav(dir) {
  calM += dir;
  if (calM < 0)  { calM = 11; calY--; }
  if (calM > 11) { calM = 0;  calY++; }
  renderCal();
}
function calClickEvent(id, type) {
  if (type === 'order') {
    window.location.href = '?action=group_orders#order-' + id;
    return;
  }
  document.querySelectorAll('.card.cal-focus').forEach(c => c.classList.remove('cal-focus'));
  const card = document.querySelector('.card[data-id="' + id + '"]');
  if (!card) return;
  card.classList.add('cal-focus');
  card.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
function renderCal() {
  const today  = new Date().toISOString().slice(0, 10);
  const first  = new Date(calY, calM, 1).getDay();
  const offset = (first + 6) % 7;
  const total  = new Date(calY, calM + 1, 0).getDate();
  document.getElementById('cal-title').textContent = MONTHS_FR[calM] + ' ' + calY;
  const grid = document.getElementById('cal-grid');
  grid.innerHTML = DAYS_FR.map(d => '<div class="cal-head">' + d + '</div>').join('');
  for (let i = 0; i < offset; i++) {
    grid.insertAdjacentHTML('beforeend', '<div class="cal-cell other"></div>');
  }
  for (let d = 1; d <= total; d++) {
    const ds  = calY + '-' + String(calM + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
    const evs = CAL_EVENTS.filter(e => e.date === ds);
    let html  = '<div class="cal-num">' + d + '</div>';
    evs.forEach(e => {
      const t   = e.title.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
      const cls = e.type === 'order' ? 'cal-ev cal-ev-order' : 'cal-ev';
      html += '<div class="' + cls + '" onclick="calClickEvent(' + e.id + ',\'' + e.type + '\')" title="' + t + '">' + t + '</div>';
    });
    grid.insertAdjacentHTML('beforeend',
      '<div class="cal-cell' + (ds === today ? ' today' : '') + '">' + html + '</div>');
  }
}

/* ── Modal ajout de carte ── */
function openAddModal(col) {
  document.getElementById('modal-col').value = col;
  const labels = ['Nouvelle idée', 'Planifier une activité', 'Archiver un souvenir'];
  document.getElementById('modal-title').textContent = labels[col];
  const dateGrp = document.getElementById('date-group');
  dateGrp.style.display = col === 1 ? 'flex' : 'none';
  document.getElementById('add-modal').classList.add('open');
  document.getElementById('modal-title-field').focus();
}
function closeModal() {
  document.getElementById('add-modal').classList.remove('open');
}
document.getElementById('add-modal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>
JS;

    layoutClose();
}

function renderCard(array $card, array $user, int $colId, array $comments = []): void
{
    $cardId     = (int) $card['id'];
    $tag        = TAG_META[$card['tag']] ?? TAG_META['autre'];
    $status     = $card['status'] ?? 'idea';
    $canAct     = ($card['author_id'] == $user['id']) || ($user['role'] === 'admin');
    $authorName = $card['author_name'] ? h($card['author_name']) : 'anonyme';
    $audience   = AUDIENCE_META[$card['audience']] ?? '';

    $interests  = getCardInterests($cardId);
    $myInterest = false;
    $intNames   = [];
    foreach ($interests as $i) {
        $intNames[] = h($i['display_name']);
        if ($i['user_id'] == $user['id']) {
            $myInterest = true;
        }
    }
    $intCount = count($interests);

    echo '<div class="card" data-id="' . $cardId . '" data-title="' . h(mb_strtolower($card['title'])) . '" data-body="' . h(mb_strtolower($card['body'] ?? '')) . '" data-tag="' . h($card['tag']) . '" data-audience="' . h($card['audience'] ?? '') . '">';

    // En-tête : tag + statut + public · bouton supprimer (à droite)
    echo '<div class="card-header">';
    echo '<div class="card-chips">';
    echo '<span class="tag ' . $tag['cls'] . '">' . $tag['emoji'] . ' ' . h($tag['label']) . '</span>';
    if ($colId === 1) {
        $sm = STATUS_META[$status] ?? STATUS_META['a_planifier'];
        echo '<span class="status-badge status-' . h($status) . '">' . $sm['label'] . '</span>';
    }
    if ($audience) {
        echo '<span class="audience-chip">' . $audience . '</span>';
    }
    echo '</div>';
    if ($canAct) {
        echo '<form method="post" action="?action=card_delete" style="display:inline">';
        echo csrfField();
        echo '<input type="hidden" name="card_id" value="' . $cardId . '">';
        echo '<button type="submit" class="btn-icon" title="Supprimer la carte" aria-label="Supprimer la carte" onclick="return confirm(\'Supprimer cette carte ?\')">✕</button>';
        echo '</form>';
    }
    echo '</div>'; // .card-header

    // Titre et description
    echo '<h3 class="card-title">' . h($card['title']) . '</h3>';
    if ($card['body']) {
        echo '<p class="card-body">' . nl2br(h($card['body'])) . '</p>';
    }

    // Méta : auteur · date (planifiée)
    $dateStr = ($card['event_date'] && $status === 'planifiee') ? fmtDate($card['event_date']) : '';
    echo '<div class="card-meta">';
    echo '<span class="card-author">— ' . $authorName . '</span>';
    if ($dateStr) {
        echo '<time class="card-date" datetime="' . h($card['event_date'] ?? '') . '">📅 ' . $dateStr . '</time>';
    }
    echo '</div>';

    // Pied : intérêt (gauche) · bouton avancer (droite)
    echo '<div class="card-footer">';

    if ($colId === 0) {
        // Bouton interactif d'intérêt
        $btnLabel  = $myInterest
            ? ($intCount > 1 ? '✋ Toi + ' . ($intCount - 1) . ' autre' . ($intCount > 2 ? 's' : '') : '✋ Tu es partant·e')
            : ($intCount > 0 ? '✋ ' . $intCount . ' partant' . ($intCount > 1 ? 's' : '') : '✋ Je suis partant·e');
        $intActive = $myInterest ? 'active' : '';
        $intTip    = $intNames   ? 'title="' . implode(', ', $intNames) . '"' : '';
        echo '<form method="post" action="?action=interest_toggle" style="display:inline">';
        echo csrfField();
        echo '<input type="hidden" name="card_id" value="' . $cardId . '">';
        echo '<button type="submit" class="interest-btn ' . $intActive . '" ' . $intTip . ' aria-pressed="' . ($myInterest ? 'true' : 'false') . '">' . $btnLabel . '</button>';
        echo '</form>';
    } else {
        // Lecture seule (cols 1 & 2)
        if ($intCount > 0) {
            $tip = 'title="Intéressé·es : ' . implode(', ', $intNames) . '"';
            $txt = $myInterest
                ? ($intCount > 1 ? '✋ Toi + ' . ($intCount - 1) . ' intéressé' . ($intCount > 2 ? 's' : '') : '✋ Tu étais intéressé·e')
                : '✋ ' . $intCount . ' intéressé' . ($intCount > 1 ? 's' : '');
            echo '<span class="voters-row" ' . $tip . '>' . $txt . '</span>';
        } else {
            echo '<span></span>';
        }
    }

    // Bouton avancer (Planifier / Archiver)
    if ($canAct && $colId < 2) {
        $nextCol   = $colId + 1;
        $nextLabel = $nextCol === 1 ? '📅 Planifier' : '✅ Archiver';
        $ariaLbl   = $nextCol === 1 ? 'Passer en planification' : 'Archiver cet événement';
        echo '<form method="post" action="?action=card_move" style="display:inline">';
        echo csrfField();
        echo '<input type="hidden" name="card_id" value="' . $cardId . '">';
        echo '<input type="hidden" name="to_col"  value="' . $nextCol . '">';
        echo '<button type="submit" class="btn btn-ghost btn-sm" aria-label="' . $ariaLbl . '">' . $nextLabel . '</button>';
        echo '</form>';
    }

    echo '</div>'; // .card-footer

    // Section planification (col 1 uniquement)
    if ($colId === 1) {
        renderPlanningSection($card, $user, $cardId, $status, $canAct);
    }

    // Section commentaires
    $commentCount = count($comments);
    $hasCls       = $commentCount > 0 ? ' has' : '';
    $cSectionId   = 'cmts-' . $cardId;
    $cmtLabel     = $commentCount > 0 ? 'Commentaires (' . $commentCount . ')' : 'Commenter';
    echo '<hr class="card-divider" aria-hidden="true">';
    echo '<button type="button" class="comment-toggle' . $hasCls . '" ';
    echo 'onclick="toggleEl(\'' . $cSectionId . '\', this)" aria-expanded="false" aria-controls="' . $cSectionId . '">💬 ' . $cmtLabel . '</button>';

    echo '<div id="' . $cSectionId . '" class="comment-section" style="display:none" role="region" aria-label="Commentaires">';
    foreach ($comments as $cmt) {
        $canDelCmt = ($cmt['user_id'] == $user['id']) || ($user['role'] === 'admin');
        echo '<div class="comment-item">';
        echo '<div class="comment-meta">';
        echo '<strong>' . h($cmt['author_name']) . '</strong>';
        echo '<span>' . fmtDate($cmt['created_at']) . '</span>';
        if ($canDelCmt) {
            echo '<form method="post" action="?action=comment_delete" style="display:inline;margin-left:auto">';
            echo csrfField();
            echo '<input type="hidden" name="comment_id" value="' . (int)$cmt['id'] . '">';
            echo '<button type="submit" class="poll-del-btn" aria-label="Supprimer ce commentaire" title="Supprimer">✕</button>';
            echo '</form>';
        }
        echo '</div>';
        echo '<div class="comment-body">' . nl2br(h($cmt['body'])) . '</div>';
        echo '</div>';
    }
    echo '<form method="post" action="?action=comment_add" style="margin-top:.5rem;display:flex;gap:.3rem;flex-wrap:wrap">';
    echo csrfField();
    echo '<input type="hidden" name="card_id" value="' . $cardId . '">';
    echo '<label for="cmt-body-' . $cardId . '" class="sr-only">Votre commentaire</label>';
    echo '<textarea id="cmt-body-' . $cardId . '" name="body" rows="2" placeholder="Votre commentaire…" style="flex:1;min-width:120px;font-size:.82rem;padding:.4rem .55rem;border:1px solid var(--border);border-radius:5px;background:var(--bg);resize:vertical;font-family:\'DM Sans\',sans-serif"></textarea>';
    echo '<button type="submit" class="btn btn-ghost btn-sm" style="align-self:flex-end">Envoyer</button>';
    echo '</form>';
    echo '</div>'; // .comment-section

    echo '</div>'; // .card
}

function renderPlanningSection(array $card, array $user, int $cardId, string $status, bool $canAct): void
{
    // Annulée / Reportée : seul l'admin voit le bouton réactiver
    if ($status === 'annulee' || $status === 'reportee') {
        if ($canAct) {
            $lbl = $status === 'annulee' ? '❌ Annulée' : '⏸ Reportée';
            echo '<div class="poll-section">';
            echo '<p class="text-sm text-muted" style="margin-bottom:.4rem">' . $lbl . '</p>';
            echo '<form method="post" action="?action=card_status_update">';
            echo csrfField();
            echo '<input type="hidden" name="card_id" value="' . $cardId . '">';
            echo '<input type="hidden" name="status"  value="a_planifier">';
            echo '<button type="submit" class="btn btn-ghost btn-sm">↺ Réactiver</button>';
            echo '</form>';
            echo '</div>';
        }
        return;
    }

    if ($status === 'planifiee') {
        renderPresenceSection($card, $user, $cardId, $canAct);
        return;
    }

    // État a_planifier : sondage de dates
    $polls = getDatePolls($cardId);

    echo '<div class="poll-section">';
    echo '<span class="section-label">Quand ?</span>';

    if (empty($polls)) {
        echo '<p class="text-sm text-muted" style="margin-bottom:.35rem">Aucune date proposée pour l\'instant.</p>';
    }

    foreach ($polls as $poll) {
        $pollId    = (int) $poll['id'];
        $votes     = $poll['votes'];
        $voteCount = count($votes);
        $myVote    = false;
        $voteNames = [];
        foreach ($votes as $v) {
            $voteNames[] = h($v['display_name']);
            if ($v['user_id'] == $user['id']) {
                $myVote = true;
            }
        }
        $canDelPoll = ($poll['created_by'] == $user['id'])
                   || ($card['author_id']  == $user['id'])
                   || ($user['role'] === 'admin');

        echo '<div class="poll-option">';
        echo '<span class="poll-date-label">' . fmtDate($poll['proposed_date']) . '</span>';

        $vcTip = $voteNames ? 'title="Votes : ' . implode(', ', $voteNames) . '"' : '';
        echo '<span class="poll-vote-count" ' . $vcTip . '>' . $voteCount . ' vote' . ($voteCount > 1 ? 's' : '') . '</span>';

        $voteActive = $myVote ? 'voted' : '';
        $voteLabel  = $myVote ? '✓ Voté' : 'Voter';
        echo '<form method="post" action="?action=date_poll_vote" style="display:inline">';
        echo csrfField();
        echo '<input type="hidden" name="poll_id" value="' . $pollId . '">';
        echo '<button type="submit" class="poll-vote-btn ' . $voteActive . '" aria-pressed="' . ($myVote ? 'true' : 'false') . '">' . $voteLabel . '</button>';
        echo '</form>';

        if ($canDelPoll) {
            echo '<form method="post" action="?action=date_poll_delete" style="display:inline">';
            echo csrfField();
            echo '<input type="hidden" name="poll_id" value="' . $pollId . '">';
            echo '<button type="submit" class="poll-del-btn" aria-label="Supprimer cette date" title="Retirer cette date">✕</button>';
            echo '</form>';
        }

        echo '</div>'; // .poll-option
    }

    // Proposer une date (tous les membres)
    echo '<div class="poll-add-row">';
    echo '<form method="post" action="?action=date_poll_add" style="display:flex;gap:.3rem;flex-wrap:wrap;align-items:center">';
    echo csrfField();
    echo '<input type="hidden" name="card_id" value="' . $cardId . '">';
    echo '<label for="date-prop-' . $cardId . '" class="sr-only">Proposer une date</label>';
    echo '<input type="date" id="date-prop-' . $cardId . '" name="proposed_date" required style="font-size:.82rem;padding:.28rem .5rem;border:1px solid var(--border);border-radius:5px;background:var(--bg)">';
    echo '<button type="submit" class="btn btn-ghost btn-sm">+ Proposer</button>';
    echo '</form>';
    echo '</div>';

    // Admin / auteur : confirmer la date et gérer le statut (dans <details>)
    if ($canAct) {
        $bestDate = '';
        if (!empty($polls)) {
            $sorted = $polls;
            usort($sorted, fn($a, $b) => count($b['votes']) - count($a['votes']));
            $bestDate = $sorted[0]['proposed_date'];
        }

        echo '<details class="card-manage">';
        echo '<summary>Gérer l\'événement</summary>';
        echo '<div class="card-manage-body">';

        echo '<form method="post" action="?action=card_confirm_date" style="display:flex;gap:.3rem;flex-wrap:wrap;align-items:center">';
        echo csrfField();
        echo '<input type="hidden" name="card_id" value="' . $cardId . '">';
        echo '<label for="evt-date-' . $cardId . '" class="sr-only">Date de l\'événement</label>';
        echo '<input type="date" id="evt-date-' . $cardId . '" name="event_date" required value="' . h($bestDate) . '" style="font-size:.82rem;padding:.28rem .5rem;border:1px solid var(--border);border-radius:5px;background:var(--bg)">';
        echo '<button type="submit" class="btn btn-primary btn-sm">✓ Confirmer la date</button>';
        echo '</form>';

        echo '<form method="post" action="?action=card_status_update" style="display:inline">';
        echo csrfField();
        echo '<input type="hidden" name="card_id" value="' . $cardId . '">';
        echo '<input type="hidden" name="status"  value="reportee">';
        echo '<button type="submit" class="btn btn-ghost btn-sm">⏸ Reporter</button>';
        echo '</form>';

        echo '<form method="post" action="?action=card_status_update" style="display:inline">';
        echo csrfField();
        echo '<input type="hidden" name="card_id" value="' . $cardId . '">';
        echo '<input type="hidden" name="status"  value="annulee">';
        echo '<button type="submit" class="btn btn-ghost btn-sm" style="color:#c0392b;border-color:#f5b7b1" onclick="return confirm(\'Annuler définitivement cet événement ?\')">✕ Annuler</button>';
        echo '</form>';

        echo '</div>';
        echo '</details>';
    }

    echo '</div>'; // .poll-section
}

function renderPresenceSection(array $card, array $user, int $cardId, bool $canAct): void
{
    $presences   = getPresences($cardId);
    $attending   = [];
    $declining   = [];
    $myAttending = null;

    foreach ($presences as $p) {
        if ((int) $p['attending'] === 1) {
            $attending[] = h($p['display_name']);
        } else {
            $declining[] = h($p['display_name']);
        }
        if ($p['user_id'] == $user['id']) {
            $myAttending = (int) $p['attending'];
        }
    }

    echo '<div class="presence-section">';
    echo '<span class="section-label">Votre présence</span>';

    echo '<div class="presence-btns">';

    $willClass = $myAttending === 1 ? ' will-be' : '';
    echo '<form method="post" action="?action=presence_toggle" style="display:inline">';
    echo csrfField();
    echo '<input type="hidden" name="card_id"  value="' . $cardId . '">';
    echo '<input type="hidden" name="attending" value="1">';
    echo '<button type="submit" class="presence-btn' . $willClass . '" aria-pressed="' . ($myAttending === 1 ? 'true' : 'false') . '">✅ Je serai là</button>';
    echo '</form>';

    $wontClass = $myAttending === 0 ? ' wont-be' : '';
    echo '<form method="post" action="?action=presence_toggle" style="display:inline">';
    echo csrfField();
    echo '<input type="hidden" name="card_id"  value="' . $cardId . '">';
    echo '<input type="hidden" name="attending" value="0">';
    echo '<button type="submit" class="presence-btn' . $wontClass . '" aria-pressed="' . ($myAttending === 0 ? 'true' : 'false') . '">😕 Je ne pourrai pas</button>';
    echo '</form>';

    echo '</div>'; // .presence-btns

    // Récapitulatif des présences
    if (!empty($attending)) {
        $tip = 'title="' . implode(', ', $attending) . '"';
        echo '<div class="presence-list" ' . $tip . ' aria-label="Présent·es">';
        echo '✅ ' . implode(', ', $attending);
        echo '</div>';
    }
    if (!empty($declining)) {
        $tip = 'title="' . implode(', ', $declining) . '"';
        echo '<div class="presence-list" ' . $tip . ' aria-label="Absent·es">';
        echo '😕 ' . implode(', ', $declining);
        echo '</div>';
    }
    if (empty($presences)) {
        echo '<p class="text-sm text-muted" style="margin-top:.2rem">Personne n\'a encore répondu.</p>';
    }

    // Gérer la date et le statut (admin/auteur uniquement, dans <details>)
    if ($canAct) {
        echo '<details class="card-manage">';
        echo '<summary>Gérer l\'événement</summary>';
        echo '<div class="card-manage-body">';

        echo '<form method="post" action="?action=card_confirm_date" style="display:flex;gap:.3rem;flex-wrap:wrap;align-items:center">';
        echo csrfField();
        echo '<input type="hidden" name="card_id" value="' . $cardId . '">';
        echo '<label for="evt-mod-' . $cardId . '" class="sr-only">Modifier la date</label>';
        echo '<input type="date" id="evt-mod-' . $cardId . '" name="event_date" value="' . h($card['event_date'] ?? '') . '" style="font-size:.82rem;padding:.28rem .5rem;border:1px solid var(--border);border-radius:5px;background:var(--bg)">';
        echo '<button type="submit" class="btn btn-ghost btn-sm">✎ Modifier la date</button>';
        echo '</form>';

        echo '<form method="post" action="?action=card_status_update" style="display:inline">';
        echo csrfField();
        echo '<input type="hidden" name="card_id" value="' . $cardId . '">';
        echo '<input type="hidden" name="status"  value="reportee">';
        echo '<button type="submit" class="btn btn-ghost btn-sm">⏸ Reporter</button>';
        echo '</form>';

        echo '<form method="post" action="?action=card_status_update" style="display:inline">';
        echo csrfField();
        echo '<input type="hidden" name="card_id" value="' . $cardId . '">';
        echo '<input type="hidden" name="status"  value="annulee">';
        echo '<button type="submit" class="btn btn-ghost btn-sm" style="color:#c0392b;border-color:#f5b7b1" onclick="return confirm(\'Annuler cet événement ?\')">✕ Annuler</button>';
        echo '</form>';

        echo '</div>';
        echo '</details>';
    }

    echo '</div>'; // .presence-section
}

function renderCardModal(): void
{
    $tagOptions = '';
    foreach (TAG_META as $k => $t) {
        $tagOptions .= '<option value="' . h($k) . '">' . $t['emoji'] . ' ' . $t['label'] . '</option>';
    }

    echo '<div class="modal-overlay" id="add-modal">';
    echo '<div class="modal">';
    echo '<h3 id="modal-title">Nouvelle idée</h3>';
    echo '<form method="post" action="?action=card_add">';
    echo csrfField();
    echo '<input type="hidden" name="column_id" id="modal-col" value="0">';
    echo '<div style="display:flex;flex-direction:column;gap:1rem">';
    echo '<div class="form-group"><label>Catégorie</label><select name="tag">' . $tagOptions . '</select></div>';
    echo '<div class="form-group"><label>Titre *</label><input type="text" name="title" id="modal-title-field" required placeholder="ex : Initiation à la taille de pierre"></div>';
    echo '<div class="form-group"><label>Description</label><textarea name="body" placeholder="Quelques mots pour donner envie…"></textarea></div>';
    echo '<div class="form-group"><label>Public</label><select name="audience">';
    foreach (AUDIENCE_META as $k => $v) {
        echo '<option value="' . h($k) . '">' . $v . '</option>';
    }
    echo '</select></div>';
    echo '<div class="form-group" id="date-group" style="display:none"><label>Date proposée</label><input type="date" name="event_date"></div>';
    echo '<div class="modal-actions"><button type="button" class="btn btn-ghost" onclick="closeModal()">Annuler</button><button type="submit" class="btn btn-primary">Enregistrer</button></div>';
    echo '</div></form></div></div>';
}
