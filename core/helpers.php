<?php
// ═══════════════════════════════════════════════════════════
//  Panneau Vivant — Utilitaires & layout
// ═══════════════════════════════════════════════════════════

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function flash(string $key, string $msg = ''): string
{
    if ($msg !== '') {
        $_SESSION['flash'][$key] = $msg;
        return '';
    }
    $v = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $v;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function fmtDate(?string $d): string
{
    if (!$d) {
        return '';
    }
    $ts = strtotime($d);
    return $ts ? date('d/m/Y', $ts) : $d;
}

function layoutOpen(string $title, ?array $user = null, string $currentAction = ''): void
{
    $appName = h(getSetting('app_name', APP_NAME));
    $appSub  = h(getSetting('app_subtitle', APP_SUBTITLE));
    $isAdmin = $user && $user['role'] === 'admin';
    echo <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$appName} — {$title}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,600;1,400&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg:        #f0e9dc;
  --card-bg:   #fdf9f3;
  --col-0:     #b5694a;
  --col-1:     #5c7d63;
  --col-2:     #7a6045;
  --text:      #2a1e10;
  --muted:     #8a7a68;
  --border:    #ddd3c4;
  --shadow:    0 2px 12px rgba(42,30,16,.10);
  --radius:    10px;
  --nav-h:     56px;
}

html, body { height: 100%; font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); }

/* ── NAV ── */
nav {
  position: sticky; top: 0; z-index: 50;
  background: var(--card-bg); border-bottom: 1px solid var(--border);
  height: var(--nav-h); padding: 0 1.5rem;
  display: flex; align-items: center; justify-content: space-between;
  box-shadow: 0 1px 8px rgba(42,30,16,.06);
}
.nav-brand { font-family: 'Lora', serif; font-size: 1.15rem; font-weight: 600; color: var(--text); text-decoration: none; }
.nav-brand small { display: block; font-size: .68rem; font-weight: 400; color: var(--muted); font-style: italic; }
.nav-links { display: flex; gap: .25rem; align-items: center; }
.nav-links a {
  padding: .4rem .75rem; border-radius: 6px; font-size: .85rem; font-weight: 500;
  color: var(--muted); text-decoration: none; transition: background .15s, color .15s;
}
.nav-links a:hover, .nav-links a.active { background: var(--bg); color: var(--text); }
.nav-links a.active { font-weight: 600; }

/* ── BOUTONS ── */
.btn {
  display: inline-flex; align-items: center; gap: .4rem;
  padding: .6rem 1.1rem; border: none; border-radius: 6px;
  font-family: 'DM Sans', sans-serif; font-size: .88rem; font-weight: 500;
  cursor: pointer; text-decoration: none; transition: opacity .15s, transform .1s;
}
.btn:hover   { opacity: .85; }
.btn:active  { transform: scale(.97); }
.btn-primary { background: var(--col-1); color: #fff; }
.btn-danger  { background: #c0392b;     color: #fff; }
.btn-ghost   { background: transparent; color: var(--muted); border: 1px solid var(--border); }
.btn-sm      { padding: .35rem .7rem; font-size: .8rem; }
.btn-dashed  { background: transparent; border: 1.5px dashed var(--border); color: var(--muted); width: 100%; justify-content: center; }
.btn-dashed:hover { border-color: var(--col-1); color: var(--col-1); opacity: 1; }

/* ── FORMS ── */
.form-group { display: flex; flex-direction: column; gap: .35rem; }
.form-group label { font-size: .78rem; font-weight: 500; color: var(--muted); text-transform: uppercase; letter-spacing: .07em; }
.form-group input, .form-group select, .form-group textarea {
  width: 100%; padding: .65rem .9rem;
  border: 1px solid var(--border); border-radius: 6px;
  background: var(--bg); font-family: 'DM Sans', sans-serif;
  font-size: .92rem; color: var(--text); outline: none;
  transition: border-color .2s;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: var(--col-1); }
.form-group textarea { resize: vertical; min-height: 80px; }
.form-check { display: flex; gap: .6rem; align-items: flex-start; font-size: .88rem; color: var(--muted); }
.form-check input { width: auto; margin-top: .2rem; }

/* ── ALERTES ── */
.alert { padding: .75rem 1rem; border-radius: 6px; font-size: .9rem; margin-bottom: 1rem; }
.alert-error   { background: #fde8e8; color: #922b21; border: 1px solid #f5b7b1; }
.alert-success { background: #e8f5e9; color: #1e6e28; border: 1px solid #a9dfb0; }

/* ── TAGS ── */
.tag { display: inline-block; font-size: .7rem; font-weight: 500; text-transform: uppercase;
  letter-spacing: .07em; padding: .15rem .55rem; border-radius: 20px; }
.tag-savoir  { background: #fde8d8; color: #b5694a; }
.tag-jeux    { background: #dff0e2; color: #4a7d58; }
.tag-lecture { background: #e8e0f0; color: #6a5a8a; }
.tag-nature  { background: #e2efd9; color: #4d7038; }
.tag-cinema  { background: #fce8e8; color: #9c3a3a; }
.tag-autre   { background: #ede8e0; color: #7a6045; }

/* ── MODAL ── */
.modal-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(42,30,16,.45); z-index: 200;
  align-items: center; justify-content: center; padding: 1rem;
}
.modal-overlay.open { display: flex; }
.modal {
  background: var(--card-bg); border-radius: var(--radius);
  width: 100%; max-width: 500px; padding: 2rem;
  box-shadow: 0 8px 40px rgba(42,30,16,.2);
  display: flex; flex-direction: column; gap: 1rem;
  max-height: 90vh; overflow-y: auto;
  animation: slideUp .2s ease;
}
.modal h3 { font-family: 'Lora', serif; font-size: 1.2rem; }
.modal-actions { display: flex; justify-content: flex-end; gap: .6rem; padding-top: .5rem; }
@keyframes slideUp {
  from { transform: translateY(20px); opacity: 0; }
  to   { transform: translateY(0);    opacity: 1; }
}

/* ── PAGE WRAPPER ── */
.page { padding: 2rem; max-width: 1400px; margin: 0 auto; }
.page-header { margin-bottom: 1.5rem; }
.page-header h1 { font-family: 'Lora', serif; font-size: 1.6rem; }
.page-header p  { color: var(--muted); margin-top: .35rem; font-size: .9rem; }

/* ── RULE BANNER ── */
.rule-banner {
  background: #2a1e10; color: #f2ebe0; text-align: center;
  padding: .55rem 1rem; font-family: 'Lora', serif;
  font-style: italic; font-size: .92rem; letter-spacing: .02em;
}

/* ── BOARD ── */
.board { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; align-items: start; }
@media (max-width: 900px) { .board { grid-template-columns: 1fr; } }

.column { background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
.col-header {
  padding: .9rem 1.25rem .75rem;
  border-bottom: 3px solid currentColor;
  display: flex; align-items: center; justify-content: space-between;
}
.col-header h2 { font-family: 'Lora', serif; font-size: 1.05rem; display: flex; align-items: center; gap: .5rem; }
.col-header .badge {
  font-family: 'DM Sans', sans-serif; font-size: .72rem; font-weight: 600;
  background: currentColor; color: var(--card-bg) !important;
  border-radius: 20px; padding: .12rem .5rem; opacity: .8;
}
.col-desc { font-size: .78rem; color: var(--muted); padding: .5rem 1.25rem; border-bottom: 1px solid var(--border); font-style: italic; }
.col-0 .col-header { color: var(--col-0); }
.col-1 .col-header { color: var(--col-1); }
.col-2 .col-header { color: var(--col-2); }
.cards-list { padding: .75rem; display: flex; flex-direction: column; gap: .6rem; min-height: 60px; }
.col-footer { padding: .6rem .75rem; border-top: 1px solid var(--border); }

/* ── CARD ── */
.card {
  background: var(--card-bg); border: 1px solid var(--border);
  border-radius: 10px; padding: 1rem 1.1rem;
  animation: fadeIn .22s ease;
}
@keyframes fadeIn { from { opacity:0; transform: translateY(-4px); } to { opacity:1; transform:none; } }
.card-header { display: flex; align-items: flex-start; justify-content: space-between; gap: .4rem; margin-bottom: .5rem; }
.card-chips  { display: flex; align-items: center; gap: .3rem; flex-wrap: wrap; flex: 1; }
.card-title  { font-family: 'Lora', serif; font-size: 1rem; font-weight: 600; line-height: 1.35; margin-bottom: .25rem; }
.card-body   { font-size: .83rem; color: var(--muted); line-height: 1.55; margin-bottom: .25rem; }
.card-meta   { display: flex; align-items: center; gap: .55rem; flex-wrap: wrap; margin-top: .3rem; }
.card-author { font-size: .75rem; color: var(--muted); }
.card-date   { font-size: .74rem; color: var(--muted); }
.audience-chip { font-size: .7rem; color: var(--muted); }
.card-footer { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .3rem; margin-top: .65rem; }
.card-divider { border: none; border-top: 1px solid var(--border); margin: .55rem 0 0; }

/* ── BOUTON ICÔNE (supprimer) ── */
.btn-icon {
  display: inline-flex; align-items: center; justify-content: center;
  width: 28px; height: 28px; border-radius: 5px; flex-shrink: 0;
  border: 1px solid transparent; background: transparent; cursor: pointer;
  color: var(--muted); font-size: .85rem; line-height: 1; transition: all .15s;
}
.btn-icon:hover { background: #fde8e8; color: #c0392b; border-color: #f5b7b1; }

/* ── INTÉRÊT ── */
.interest-btn {
  display: inline-flex; align-items: center; gap: .3rem;
  background: transparent; border: 1px solid var(--border);
  color: var(--muted); border-radius: 20px;
  padding: .22rem .75rem; font-size: .8rem; cursor: pointer;
  font-family: 'DM Sans', sans-serif; transition: all .15s;
}
.interest-btn:hover { border-color: var(--col-1); color: var(--col-1); }
.interest-btn.active { background: var(--col-1); border-color: var(--col-1); color: #fff; }

/* ── FOCUS VISIBLE (accessibilité clavier) ── */
:focus-visible { outline: 2.5px solid var(--col-1); outline-offset: 2px; border-radius: 3px; }

/* ── TABLE ── */
.data-table { width: 100%; border-collapse: collapse; font-size: .88rem; }
.data-table th { text-align: left; padding: .6rem .8rem; font-weight: 500; color: var(--muted); font-size: .75rem; text-transform: uppercase; letter-spacing: .06em; border-bottom: 2px solid var(--border); }
.data-table td { padding: .65rem .8rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: rgba(0,0,0,.02); }

/* ── LIBRARY ── */
.lib-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1rem; }
.lib-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius); padding: 1rem; box-shadow: var(--shadow); }
.lib-card-cat { font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); margin-bottom: .4rem; }
.lib-card-title { font-family: 'Lora', serif; font-size: .95rem; font-weight: 600; }
.lib-card-sub   { font-size: .8rem; color: var(--muted); margin-top: .15rem; }
.lib-card-desc  { font-size: .82rem; color: var(--muted); margin-top: .4rem; line-height: 1.5; }
.lib-status { display: inline-block; font-size: .72rem; font-weight: 500; padding: .15rem .5rem; border-radius: 20px; margin-top: .5rem; }
.lib-status.avail { background: #e2efd9; color: #4d7038; }
.lib-status.taken { background: #fce8e8; color: #9c3a3a; }
.lib-card-actions { margin-top: .75rem; display: flex; gap: .4rem; flex-wrap: wrap; }

/* ── SECTION BOX ── */
.section-box { background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; margin-bottom: 2rem; }
.section-box-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.section-box-header h2 { font-family: 'Lora', serif; font-size: 1.1rem; }
.section-box-body { padding: 1.5rem; }

/* ── BADGE ROLE ── */
.role-badge { display: inline-block; font-size: .72rem; padding: .15rem .55rem; border-radius: 20px; font-weight: 500; }
.role-admin    { background: #fde8d8; color: #b5694a; }
.role-member   { background: #dff0e2; color: #4a7d58; }
.role-external { background: #e8e0f0; color: #6a5a8a; }

/* ── AUTH PAGES ── */
.auth-wrap { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem; gap: 1.5rem; }
.auth-box { background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); padding: 2.5rem 2rem; width: 100%; max-width: 400px; display: flex; flex-direction: column; gap: 1.25rem; }
.auth-box h1 { font-family: 'Lora', serif; font-size: 1.3rem; }

/* ── FOOTER ── */
footer { text-align: center; padding: 1.25rem; color: var(--muted); font-size: .75rem; border-top: 1px solid var(--border); margin-top: 2rem; }

/* ── ACCESSIBILITÉ ── */
/* Classe pour les labels lisibles uniquement par les lecteurs d'écran */
.sr-only {
  position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
  overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0;
}

/* ── MISC ── */
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 600px) { .grid-2 { grid-template-columns: 1fr; } }
.text-muted { color: var(--muted); }
.text-sm    { font-size: .85rem; }
.mt-1  { margin-top: .5rem; }
.mt-2  { margin-top: 1rem; }
.mt-3  { margin-top: 1.5rem; }
.flex  { display: flex; }
.flex-between { display: flex; align-items: center; justify-content: space-between; }
.gap-1 { gap: .5rem; }
.w-full { width: 100%; }
.danger-zone { background: #fde8e8; border: 1px solid #f5b7b1; border-radius: var(--radius); padding: 1.5rem; }
.danger-zone h3 { color: #922b21; font-size: 1rem; margin-bottom: .5rem; }

/* ── BADGES STATUT ── */
.status-badge { display: inline-block; font-size: .67rem; font-weight: 600; text-transform: uppercase;
  letter-spacing: .06em; padding: .14rem .5rem; border-radius: 20px; }
.status-a_planifier { background: #fff3cd; color: #7d5800; }
.status-planifiee   { background: #d4edda; color: #155724; }
.status-annulee     { background: #f8d7da; color: #721c24; }
.status-reportee    { background: #d1ecf1; color: #0c5460; }

/* ── SECTION LABEL (quand ?, présences…) ── */
.section-label {
  display: block; font-size: .68rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .08em; color: var(--muted); margin-bottom: .4rem;
}

/* ── SONDAGE DE DATES ── */
.poll-section { margin-top: .55rem; padding-top: .5rem; border-top: 1px solid var(--border); }
.poll-option { display: flex; align-items: center; gap: .35rem; padding: .15rem 0; }
.poll-date-label { font-size: .85rem; font-weight: 500; flex: 1; min-width: 80px; }
.poll-vote-count { font-size: .72rem; color: var(--muted); white-space: nowrap; cursor: default; min-width: 48px; text-align: right; }
.poll-vote-btn {
  display: inline-flex; align-items: center; gap: .2rem;
  padding: .14rem .5rem; font-size: .76rem; border-radius: 4px;
  border: 1px solid var(--border); background: var(--bg); cursor: pointer;
  font-family: 'DM Sans', sans-serif; transition: all .15s;
}
.poll-vote-btn:hover { border-color: var(--col-1); color: var(--col-1); }
.poll-vote-btn.voted { background: var(--col-1); border-color: var(--col-1); color: #fff; }
.poll-add-row { display: flex; gap: .3rem; margin-top: .4rem; flex-wrap: wrap; align-items: center; }
.poll-del-btn { padding: .1rem .3rem; font-size: .65rem; line-height: 1;
  color: var(--muted); border: 1px solid var(--border); border-radius: 4px;
  background: transparent; cursor: pointer; flex-shrink: 0; }
.poll-del-btn:hover { color: #c0392b; border-color: #f5b7b1; }

/* ── GESTION ADMIN (details/summary) ── */
.card-manage { margin-top: .45rem; }
.card-manage > summary {
  font-size: .76rem; color: var(--muted); cursor: pointer;
  list-style: none; display: inline-flex; align-items: center; gap: .25rem;
  padding: .2rem .45rem; border-radius: 5px; border: 1px solid var(--border);
  background: var(--bg); font-family: 'DM Sans', sans-serif; transition: all .15s;
  user-select: none;
}
.card-manage > summary::-webkit-details-marker { display: none; }
.card-manage > summary::before { content: '▸'; display: inline-block; transition: transform .15s; }
.card-manage[open] > summary::before { transform: rotate(90deg); }
.card-manage > summary:hover { color: var(--text); border-color: var(--col-1); }
.card-manage-body { display: flex; gap: .3rem; flex-wrap: wrap; align-items: center; padding-top: .5rem; }

/* ── PRÉSENCES ── */
.presence-section { margin-top: .55rem; padding-top: .5rem; border-top: 1px solid var(--border); }
.presence-btns { display: flex; gap: .35rem; flex-wrap: wrap; margin-bottom: .35rem; }
.presence-btn {
  display: inline-flex; align-items: center; gap: .3rem;
  padding: .28rem .8rem; border-radius: 20px; font-size: .82rem;
  border: 1px solid var(--border); background: var(--bg); cursor: pointer;
  font-family: 'DM Sans', sans-serif; font-weight: 500; transition: all .15s;
}
.presence-btn:hover { opacity: .8; }
.presence-btn.will-be { background: #d4edda; color: #155724; border-color: #c3e6cb; }
.presence-btn.wont-be { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
.presence-list { font-size: .76rem; color: var(--muted); margin-top: .2rem; cursor: default; }

/* ── INTÉRÊTS (lecture) ── */
.voters-row { font-size: .74rem; color: var(--muted); margin-top: .3rem;
  font-style: italic; cursor: default; }

/* ── COMMENTAIRES ── */
.comment-section { margin-top: .5rem; border-top: 1px solid var(--border); padding-top: .5rem; }
.comment-item { padding: .4rem 0; border-bottom: 1px dashed var(--border); font-size: .83rem; }
.comment-item:last-child { border-bottom: none; }
.comment-meta { font-size: .7rem; color: var(--muted); margin-bottom: .15rem; display: flex; align-items: center; gap: .4rem; }
.comment-body { line-height: 1.5; white-space: pre-wrap; word-break: break-word; }
.comment-toggle {
  display: inline-flex; align-items: center; gap: .25rem;
  padding: .15rem .55rem; font-size: .76rem;
  border: 1px solid var(--border); border-radius: 20px;
  background: transparent; color: var(--muted); cursor: pointer;
  font-family: 'DM Sans', sans-serif; transition: all .15s;
}
.comment-toggle:hover { border-color: var(--col-1); color: var(--col-1); }
.comment-toggle.has { background: #e8f0e8; border-color: #9db89d; color: #3a6040; }

/* ── CONDITION OBJET ── */
.cond-badge { display: inline-block; font-size: .68rem; font-weight: 600;
  text-transform: uppercase; letter-spacing: .06em; padding: .12rem .45rem; border-radius: 20px; }
.cond-ok     { background: #e2efd9; color: #4d7038; }
.cond-lost   { background: #fce8e8; color: #9c3a3a; }
.cond-broken { background: #fff3cd; color: #7d5800; }

/* ── ACHATS GROUPÉS ── */
.go-status { display: inline-block; font-size: .68rem; font-weight: 600;
  text-transform: uppercase; letter-spacing: .06em; padding: .14rem .5rem; border-radius: 20px; }
.go-status-open     { background: #dff0e2; color: #2e6b42; }
.go-status-ordered  { background: #fff3cd; color: #7d5800; }
.go-status-received { background: #d1ecf1; color: #0c5460; }
.go-status-closed   { background: #ede8e0; color: #7a6045; }

.go-order { background: var(--card-bg); border: 1px solid var(--border); border-radius: var(--radius);
  box-shadow: var(--shadow); margin-bottom: 1.5rem; overflow: hidden; }
.go-order-head { padding: 1rem 1.5rem; display: flex; align-items: flex-start; justify-content: space-between;
  gap: 1rem; flex-wrap: wrap; }
.go-order-title { font-family: 'Lora', serif; font-size: 1.1rem; font-weight: 600; }
.go-order-meta  { font-size: .8rem; color: var(--muted); margin-top: .2rem; display: flex; flex-wrap: wrap; gap: .5rem; }
.go-order-body  { border-top: 1px solid var(--border); padding: 1.25rem 1.5rem; }
.go-desc        { font-size: .88rem; color: var(--muted); margin-bottom: 1rem; line-height: 1.55; }

.go-product { background: var(--bg); border: 1px solid var(--border); border-radius: 8px;
  padding: .9rem 1rem; margin-bottom: .75rem; }
.go-product-head { display: flex; align-items: center; justify-content: space-between;
  gap: .5rem; flex-wrap: wrap; margin-bottom: .5rem; }
.go-product-name { font-weight: 600; font-size: .95rem; }
.go-product-price { font-size: .82rem; color: var(--muted); }

.go-requests-list { display: flex; flex-wrap: wrap; gap: .35rem; margin: .4rem 0; }
.go-req-chip { font-size: .78rem; background: var(--card-bg); border: 1px solid var(--border);
  border-radius: 20px; padding: .15rem .6rem; color: var(--text); }
.go-req-chip.mine { background: #e8f5e9; border-color: #a9dfb0; color: #1e6e28; }

.go-product-total { font-size: .82rem; font-weight: 600; color: var(--text);
  margin-top: .5rem; padding-top: .5rem; border-top: 1px dashed var(--border); }

.go-my-qty-form { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap;
  background: #f0f7f0; border: 1px solid #a9dfb0; border-radius: 6px;
  padding: .5rem .75rem; margin-bottom: .5rem; }
.go-my-qty-form label { font-size: .78rem; font-weight: 500; color: #2e6b42; white-space: nowrap; }
.go-my-qty-form input[type="number"] { width: 90px; padding: .38rem .6rem; border: 1px solid var(--border);
  border-radius: 5px; font-size: .9rem; background: var(--card-bg); }
.go-my-qty-unit { font-size: .82rem; color: var(--muted); }

.go-dispatch { margin-top: 1.25rem; padding-top: 1rem; border-top: 2px solid var(--border); }
.go-dispatch h3 { font-family: 'Lora', serif; font-size: 1rem; margin-bottom: .75rem; }
.go-person { background: var(--bg); border: 1px solid var(--border); border-radius: 8px;
  padding: .75rem 1rem; margin-bottom: .6rem; }
.go-person-name { font-weight: 600; font-size: .9rem; margin-bottom: .4rem; }
.go-person-row { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap;
  font-size: .83rem; padding: .2rem 0; }
.go-person-row + .go-person-row { border-top: 1px dashed var(--border); padding-top: .35rem; margin-top: .2rem; }
.go-person-subtotal { font-size: .82rem; font-weight: 600; margin-top: .5rem;
  padding-top: .5rem; border-top: 1px solid var(--border); }

.go-check-btn { display: inline-flex; align-items: center; gap: .25rem;
  padding: .2rem .55rem; font-size: .74rem; border-radius: 4px; cursor: pointer;
  border: 1px solid var(--border); background: var(--card-bg);
  font-family: 'DM Sans', sans-serif; font-weight: 500; transition: all .15s; }
.go-check-btn:hover { border-color: var(--col-1); }
.go-check-btn.done { background: #e8f5e9; border-color: #a9dfb0; color: #1e6e28; }

.go-actions { display: flex; gap: .5rem; flex-wrap: wrap; align-items: center;
  margin-top: 1rem; padding-top: .75rem; border-top: 1px solid var(--border); }
.go-total-banner { background: #2a1e10; color: #f2ebe0; border-radius: 6px;
  padding: .6rem 1rem; font-size: .9rem; font-weight: 500; margin-top: .75rem;
  display: flex; align-items: center; justify-content: space-between; }
.go-add-product-form { background: #f0f7f0; border: 1px dashed #a9dfb0; border-radius: 8px;
  padding: .85rem 1rem; margin-bottom: .75rem; display: flex; gap: .5rem; flex-wrap: wrap; align-items: flex-end; }
.go-add-product-form .form-group { flex: 1; min-width: 120px; }
.go-cond-warning { background: #fff8e1; border: 1px solid #ffe082; border-radius: 6px;
  padding: .55rem .85rem; margin-top: .5rem; font-size: .82rem; color: #6d4c00; line-height: 1.5; }
.go-cond-warning strong { color: #5d3f00; }
.go-cond-info { font-size: .76rem; color: var(--muted); margin-top: .15rem; }
.go-tabs { display: flex; gap: .35rem; flex-wrap: wrap; margin-bottom: 1.25rem;
  padding-bottom: .75rem; border-bottom: 2px solid var(--border); }
.go-tab { padding: .38rem .85rem; border: 1px solid var(--border); border-radius: 20px;
  font-size: .83rem; font-weight: 500; background: var(--bg); color: var(--muted);
  cursor: pointer; font-family: 'DM Sans', sans-serif; transition: all .15s; white-space: nowrap; }
.go-tab:hover { color: var(--text); border-color: var(--col-1); }
.go-tab.active { background: var(--col-1); border-color: var(--col-1); color: #fff; }

/* ── FILTER BAR ── */
.filter-bar {
  display: flex; gap: .45rem; flex-wrap: wrap; align-items: center;
  background: var(--card-bg); border: 1px solid var(--border);
  border-radius: var(--radius); padding: .6rem 1rem;
  margin-bottom: 1rem; box-shadow: var(--shadow);
}
.filter-bar input[type="search"],
.filter-bar select {
  padding: .42rem .75rem; border: 1px solid var(--border);
  border-radius: 5px; background: var(--bg);
  font-family: 'DM Sans', sans-serif; font-size: .86rem;
  color: var(--text); outline: none; transition: border-color .2s;
}
.filter-bar input[type="search"] { flex: 1; min-width: 140px; }
.filter-bar input[type="search"]:focus,
.filter-bar select:focus { border-color: var(--col-1); }
.filter-bar .spacer { flex: 1; }

/* ── CALENDRIER ── */
.cal-wrap {
  background: var(--card-bg); border: 1px solid var(--border);
  border-radius: var(--radius); box-shadow: var(--shadow);
  padding: 1rem 1.25rem; margin-bottom: 1.5rem;
}
.cal-nav { display: flex; align-items: center; justify-content: space-between; margin-bottom: .7rem; }
.cal-nav h3 { font-family: 'Lora', serif; font-size: 1.05rem; }
.cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 3px; }
.cal-head { text-align: center; font-size: .68rem; font-weight: 600;
  text-transform: uppercase; letter-spacing: .06em; color: var(--muted); padding: .3rem 0; }
.cal-cell { min-height: 58px; border: 1px solid var(--border); border-radius: 4px;
  padding: .25rem .35rem; font-size: .75rem; background: var(--bg); }
.cal-cell.today { background: #e8f5e9; border-color: var(--col-1); }
.cal-cell.other { opacity: .3; }
.cal-num { font-weight: 500; color: var(--muted); margin-bottom: .15rem; font-size: .72rem; }
.cal-ev { background: var(--col-1); color: #fff; border-radius: 3px;
  padding: .07rem .28rem; margin-bottom: .1rem; font-size: .64rem;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor: pointer;
  line-height: 1.4; transition: opacity .15s; }
.cal-ev:hover { opacity: .8; }
.cal-ev-order { background: var(--col-0); }
.card.cal-focus { box-shadow: 0 0 0 2.5px var(--col-1); background: #f0f7f0; }

/* ── ONGLETS BIBLIOTHÈQUE ── */
.lib-tabs {
  display: flex; gap: .35rem; flex-wrap: wrap;
  margin-bottom: 1rem; padding-bottom: .75rem;
  border-bottom: 2px solid var(--border);
}
.lib-tab {
  padding: .38rem .85rem; border: 1px solid var(--border); border-radius: 20px;
  font-size: .83rem; font-weight: 500; background: var(--bg); color: var(--muted);
  cursor: pointer; font-family: 'DM Sans', sans-serif; transition: all .15s;
  white-space: nowrap;
}
.lib-tab:hover { color: var(--text); border-color: var(--col-1); }
.lib-tab.active { background: var(--col-1); border-color: var(--col-1); color: #fff; }

/* ── BOUTON HAMBURGER ── */
.nav-toggle {
  display: none;
  background: transparent; border: 1px solid var(--border);
  border-radius: 6px; padding: .3rem .6rem; cursor: pointer;
  font-size: 1.15rem; line-height: 1.2; color: var(--text);
  transition: background .15s;
}
.nav-toggle:hover { background: var(--bg); }

/* ── NAV RESPONSIVE ── */
@media (max-width: 700px) {
  nav { height: auto; min-height: var(--nav-h); flex-wrap: wrap; padding: 0 1rem; }
  .nav-brand { padding: .75rem 0; }
  .nav-toggle { display: flex; align-items: center; justify-content: center; margin-left: auto; }
  .nav-links {
    display: none; flex-direction: column; align-items: stretch;
    width: 100%; padding: .4rem 0 .75rem; gap: .15rem;
    border-top: 1px solid var(--border);
  }
  .nav-links.open { display: flex; }
  .nav-links a { padding: .65rem .75rem; border-radius: 6px; font-size: .92rem; }
  .nav-links form { padding: .35rem .75rem 0; }
  .nav-links form button { width: 100%; justify-content: center; }
}

/* ── PAGE RESPONSIVE ── */
@media (max-width: 600px) {
  .page { padding: 1rem; }
  .grid-2 { grid-template-columns: 1fr; }
  .modal { padding: 1.25rem; }
  .board { gap: 1rem; }
}
</style>
</head>
<body>
HTML;

    if ($user) {
        $board   = $currentAction === 'board'        ? 'active' : '';
        $library = $currentAction === 'library'      ? 'active' : '';
        $bilan   = $currentAction === 'dashboard'    ? 'active' : '';
        $orders  = $currentAction === 'group_orders' ? 'active' : '';
        $mydata  = $currentAction === 'my_data'      ? 'active' : '';
        $admin   = $currentAction === 'admin'        ? 'active' : '';
        $uname   = h($user['display_name']);
        $csrf    = csrfField();
        echo <<<HTML
<nav>
  <a class="nav-brand" href="?action=board">{$appName} <small>{$appSub}</small></a>
  <button class="nav-toggle" aria-label="Menu" aria-expanded="false" onclick="toggleNav(this)">☰</button>
  <div class="nav-links" id="nav-links">
    <a href="?action=board"        class="{$board}">🌿 Tableau</a>
    <a href="?action=library"      class="{$library}">📚 Prêt-o-thèque</a>
    <a href="?action=group_orders" class="{$orders}">🛒 Achats</a>
    <a href="?action=dashboard"    class="{$bilan}">📊 Bilan</a>
    <a href="?action=my_data"      class="{$mydata}">👤 {$uname}</a>
HTML;
        if ($isAdmin) {
            echo "<a href=\"?action=admin\" class=\"{$admin}\">⚙️ Admin</a>";
        }
        echo <<<HTML
    <form method="post" action="?action=logout" style="display:inline">
      {$csrf}
      <button type="submit" class="btn btn-ghost btn-sm">Quitter</button>
    </form>
  </div>
</nav>
<script>
function toggleNav(btn) {
  const nav = document.getElementById('nav-links');
  const open = nav.classList.toggle('open');
  btn.setAttribute('aria-expanded', open);
  btn.textContent = open ? '✕' : '☰';
}
document.addEventListener('click', function(e) {
  const nav = document.getElementById('nav-links');
  if (nav && nav.classList.contains('open') && !e.target.closest('nav')) {
    nav.classList.remove('open');
    const btn = document.querySelector('.nav-toggle');
    if (btn) { btn.setAttribute('aria-expanded','false'); btn.textContent='☰'; }
  }
});
</script>
HTML;
    }
}

function layoutClose(): void
{
    $appName = h(getSetting('app_name', APP_NAME));
    echo <<<HTML
<footer>
  {$appName} &nbsp;·&nbsp;
  <a href="?action=privacy" style="color:inherit">Politique de confidentialité</a> &nbsp;·&nbsp;
  <a href="?action=my_data" style="color:inherit">Mes données</a>
</footer>
</body></html>
HTML;
}
