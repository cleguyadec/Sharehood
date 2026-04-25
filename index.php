<?php
// ═══════════════════════════════════════════════════════════
//  Panneau Vivant — Point d'entrée principal
// ═══════════════════════════════════════════════════════════

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (!DEBUG) {
    error_reporting(0);
    ini_set('display_errors', '0');
}

startSession();
initDB();

$action = preg_replace('/[^a-z_]/', '', $_GET['action'] ?? 'board');
$user   = currentUser();

// ───────────────────────────────────────────────────────────
//  POST — traitement des formulaires
// ───────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrfVerify()) {
        flash('error', 'Requête invalide. Rechargez la page et réessayez.');
        redirect('?action=' . $action);
    }

    switch ($action) {

        case 'login':
            $err = doLogin(
                trim($_POST['display_name'] ?? ''),
                $_POST['password'] ?? ''
            );
            if ($err) {
                flash('error', $err);
                redirect('?action=login');
            }
            redirect('?action=board');

        case 'register':
            $err = doRegister(
                trim($_POST['display_name'] ?? ''),
                $_POST['password'] ?? '',
                trim($_POST['invite_code'] ?? ''),
                trim($_POST['household']   ?? ''),
                !empty($_POST['gdpr_consent'])
            );
            if ($err) {
                flash('error', $err);
                redirect('?action=register');
            }
            redirect('?action=board');

        case 'logout':
            doLogout();

        case 'card_add':
            $user = requireAuth();
            if (empty(trim($_POST['title'] ?? ''))) {
                flash('error', 'Le titre est obligatoire.');
            } else {
                addCard($user, $_POST);
                flash('success', 'Carte ajoutée !');
            }
            redirect('?action=board');

        case 'card_move':
            $user = requireAuth();
            moveCard((int)($_POST['card_id'] ?? 0), (int)($_POST['to_col'] ?? 0), $user);
            redirect('?action=board');

        case 'card_delete':
            $user = requireAuth();
            deleteCard((int)($_POST['card_id'] ?? 0), $user);
            redirect('?action=board');

        case 'interest_toggle':
            $user = requireAuth();
            toggleInterest((int)($_POST['card_id'] ?? 0), $user['id']);
            redirect('?action=board');

        case 'library_add':
            $user = requireAuth();
            if (empty(trim($_POST['title'] ?? ''))) {
                flash('error', 'Le titre est obligatoire.');
            } else {
                addLibraryItem($user, $_POST);
                flash('success', 'Objet ajouté à la prêt-o-thèque !');
            }
            redirect('?action=library');

        case 'library_borrow':
            $user = requireAuth();
            if (!borrowItem((int)($_POST['item_id'] ?? 0), $user['id'], $_POST['due_date'] ?? null)) {
                flash('error', 'Emprunt impossible (objet indisponible ?).');
            }
            redirect('?action=library');

        case 'library_return':
            $user = requireAuth();
            returnItem((int)($_POST['loan_id'] ?? 0), $user);
            redirect('?action=library');

        case 'library_delete':
            $user = requireAuth();
            deleteLibraryItem((int)($_POST['item_id'] ?? 0), $user);
            redirect('?action=library');

        case 'change_password':
            $user = requireAuth();
            $err  = changePassword($user, $_POST['old_password'] ?? '', $_POST['new_password'] ?? '');
            if ($err) {
                flash('error', $err);
            } else {
                flash('success', 'Mot de passe modifié.');
            }
            redirect('?action=my_data');

        case 'delete_account':
            $user = requireAuth();
            if (trim($_POST['confirm'] ?? '') === $user['display_name']) {
                $id = $user['id'];
                doLogout(); // redirige, donc on agit avant
                deleteAccount($id);
            } else {
                flash('error', 'Confirmation incorrecte — compte non supprimé.');
                redirect('?action=my_data');
            }
            break;

        case 'admin_toggle_user':
            requireAdmin();
            toggleUserActive((int)($_POST['user_id'] ?? 0));
            redirect('?action=admin');

        case 'admin_set_role':
            requireAdmin();
            setUserRole((int)($_POST['user_id'] ?? 0), $_POST['role'] ?? 'member');
            redirect('?action=admin');

        case 'admin_delete_user':
            requireAdmin();
            deleteAccount((int)($_POST['user_id'] ?? 0));
            flash('success', 'Compte supprimé et données anonymisées.');
            redirect('?action=admin');
    }
}

// ───────────────────────────────────────────────────────────
//  GET — dispatch vers les vues
// ───────────────────────────────────────────────────────────

if (in_array($action, ['login', 'register', 'privacy'], true)) {
    if ($user && $action !== 'privacy') {
        redirect('?action=board');
    }
} else {
    $user = requireAuth();
}

switch ($action) {
    case 'login':    viewLogin();           break;
    case 'register': viewRegister();        break;
    case 'privacy':  viewPrivacy();         break;
    case 'board':    viewBoard($user);      break;
    case 'library':  viewLibrary($user);    break;
    case 'my_data':  viewMyData($user);     break;
    case 'admin':    viewAdmin($user);      break;
    default:         redirect('?action=board');
}

// ═══════════════════════════════════════════════════════════
//  LAYOUT COMMUN
// ═══════════════════════════════════════════════════════════

function layoutOpen(string $title, ?array $user = null, string $currentAction = ''): void
{
    $appName = h(APP_NAME);
    $appSub  = h(APP_SUBTITLE);
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
  background: var(--bg); border: 1px solid var(--border);
  border-radius: 8px; padding: .85rem 1rem;
  animation: fadeIn .22s ease;
}
@keyframes fadeIn { from { opacity:0; transform: translateY(-5px); } to { opacity:1; transform:none; } }
.card-title { font-family: 'Lora', serif; font-size: .95rem; font-weight: 600; line-height: 1.3; margin: .4rem 0 .3rem; }
.card-body  { font-size: .83rem; color: var(--muted); line-height: 1.5; }
.card-meta  { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .3rem; margin-top: .5rem; }
.card-author { font-size: .75rem; color: var(--muted); font-weight: 500; }
.card-date   { font-size: .72rem; color: var(--muted); }
.card-actions { display: flex; flex-wrap: wrap; gap: .3rem; margin-top: .5rem; }
.interest-btn {
  display: inline-flex; align-items: center; gap: .3rem;
  background: transparent; border: 1px solid var(--border);
  color: var(--muted); border-radius: 20px;
  padding: .2rem .7rem; font-size: .78rem; cursor: pointer;
  font-family: 'DM Sans', sans-serif; transition: all .15s;
}
.interest-btn:hover { border-color: var(--col-1); color: var(--col-1); }
.interest-btn.active { background: var(--col-1); border-color: var(--col-1); color: #fff; }

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
</style>
</head>
<body>
HTML;

    if ($user) {
        $board   = $currentAction === 'board'   ? 'active' : '';
        $library = $currentAction === 'library' ? 'active' : '';
        $mydata  = $currentAction === 'my_data' ? 'active' : '';
        $admin   = $currentAction === 'admin'   ? 'active' : '';
        $uname   = h($user['display_name']);
        $csrf    = csrfField();
        echo <<<HTML
<nav>
  <a class="nav-brand" href="?action=board">{$appName} <small>{$appSub}</small></a>
  <div class="nav-links">
    <a href="?action=board"   class="{$board}">🌿 Tableau</a>
    <a href="?action=library" class="{$library}">📚 Prêt-o-thèque</a>
    <a href="?action=my_data" class="{$mydata}">👤 {$uname}</a>
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
HTML;
    }
}

function layoutClose(): void
{
    echo <<<HTML
<footer>
  Le Panneau Vivant &nbsp;·&nbsp;
  <a href="?action=privacy" style="color:inherit">Politique de confidentialité</a> &nbsp;·&nbsp;
  <a href="?action=my_data" style="color:inherit">Mes données</a>
</footer>
</body></html>
HTML;
}

// ═══════════════════════════════════════════════════════════
//  VUE — LOGIN
// ═══════════════════════════════════════════════════════════

function viewLogin(): void
{
    layoutOpen('Connexion');
    $err = flash('error');
    echo '<div class="auth-wrap">';
    echo '<div><h1 style="font-family:\'Lora\',serif;font-size:1.8rem;text-align:center">🌿 Le Panneau Vivant</h1>';
    echo '<p style="text-align:center;color:var(--muted);margin-top:.4rem">' . h(APP_SUBTITLE) . '</p></div>';
    if ($err) {
        echo '<div class="alert alert-error">' . h($err) . '</div>';
    }
    echo <<<HTML
<form class="auth-box" method="post" action="?action=login">
HTML;
    echo csrfField();
    echo <<<HTML
  <h1>Connexion</h1>
  <div class="form-group">
    <label>Prénom / pseudo</label>
    <input type="text" name="display_name" required autofocus autocomplete="username">
  </div>
  <div class="form-group">
    <label>Mot de passe</label>
    <input type="password" name="password" required autocomplete="current-password">
  </div>
  <button type="submit" class="btn btn-primary w-full">Se connecter</button>
  <p class="text-sm text-muted" style="text-align:center">
    Pas encore de compte ? <a href="?action=register">Créer un compte</a>
  </p>
</form>
</div>
HTML;
    layoutClose();
}

// ═══════════════════════════════════════════════════════════
//  VUE — REGISTER
// ═══════════════════════════════════════════════════════════

function viewRegister(): void
{
    layoutOpen('Créer un compte');
    $err = flash('error');
    if ($err) {
        echo '<div style="max-width:420px;margin:1rem auto"><div class="alert alert-error">' . h($err) . '</div></div>';
    }
    echo '<div class="auth-wrap">';
    echo '<div><h1 style="font-family:\'Lora\',serif;font-size:1.8rem;text-align:center">🌿 Le Panneau Vivant</h1>';
    echo '<p style="text-align:center;color:var(--muted);margin-top:.4rem">' . h(APP_SUBTITLE) . '</p></div>';
    if ($err) {
        echo '<div class="alert alert-error" style="max-width:420px;width:100%">' . h($err) . '</div>';
    }
    echo '<form class="auth-box" method="post" action="?action=register">';
    echo csrfField();
    echo <<<HTML
  <h1>Créer un compte</h1>
  <div class="form-group">
    <label>Prénom ou pseudo *</label>
    <input type="text" name="display_name" required autofocus maxlength="50" autocomplete="username">
    <small class="text-muted text-sm">Un pseudonyme suffit — pas besoin de nom complet.</small>
  </div>
  <div class="form-group">
    <label>Mot de passe * (8 caractères min.)</label>
    <input type="password" name="password" required minlength="8" autocomplete="new-password">
  </div>
  <div class="form-group">
    <label>Foyer (facultatif)</label>
    <input type="text" name="household" maxlength="100" placeholder="ex : Foyer des Tournesols">
  </div>
  <div class="form-group">
    <label>Code d'invitation *</label>
    <input type="text" name="invite_code" required autocomplete="off">
  </div>
  <div class="form-check">
    <input type="checkbox" name="gdpr_consent" id="gdpr" required>
    <label for="gdpr">
      J'ai lu et j'accepte la
      <a href="?action=privacy" target="_blank">politique de confidentialité</a>.
      Je sais que mon prénom/pseudo et mon foyer sont stockés sur ce serveur.
    </label>
  </div>
  <button type="submit" class="btn btn-primary w-full">Créer mon compte</button>
  <p class="text-sm text-muted" style="text-align:center">
    Déjà un compte ? <a href="?action=login">Se connecter</a>
  </p>
</form>
</div>
HTML;
    layoutClose();
}

// ═══════════════════════════════════════════════════════════
//  VUE — BOARD
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

    echo '<div class="board">';

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
            renderCard($card, $user, $colId);
        }
        echo '</div>';

        // Bouton ajout
        echo '<div class="col-footer">';
        echo '<button class="btn btn-dashed" onclick="openAddModal(' . $colId . ')">+ Ajouter</button>';
        echo '</div></div>';
    }

    echo '</div>'; // .board

    // ── Modal ajout de carte
    renderCardModal();

    echo '</div>'; // .page
    echo <<<'JS'
<script>
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

function renderCard(array $card, array $user, int $colId): void
{
    $tag       = TAG_META[$card['tag']] ?? TAG_META['autre'];
    $interests = getCardInterests((int) $card['id']);
    $myInterest = false;
    $interestNames = [];
    foreach ($interests as $i) {
        $interestNames[] = h($i['display_name']);
        if ($i['user_id'] == $user['id']) {
            $myInterest = true;
        }
    }
    $intCount = count($interests);
    $intLabel = $myInterest
        ? ($intCount > 1 ? "✋ Toi + " . ($intCount - 1) . " autre" . ($intCount > 2 ? 's' : '') : '✋ Tu es partant·e')
        : ($intCount > 0 ? "✋ {$intCount} partant" . ($intCount > 1 ? 's' : '') : '✋ Je suis partant·e');
    $intActive = $myInterest ? 'active' : '';
    $intTip    = $interestNames ? 'title="' . implode(', ', $interestNames) . '"' : '';

    $canAct = ($card['author_id'] == $user['id']) || ($user['role'] === 'admin');
    $authorName = $card['author_name'] ? '— ' . h($card['author_name']) : '— anonyme';
    $audience   = AUDIENCE_META[$card['audience']] ?? '';
    $eventDate  = $card['event_date'] ? '📅 ' . fmtDate($card['event_date']) : '';

    echo '<div class="card">';
    echo '<span class="tag ' . $tag['cls'] . '">' . $tag['emoji'] . ' ' . $tag['label'] . '</span>';
    echo '<div class="card-title">' . h($card['title']) . '</div>';
    if ($card['body']) {
        echo '<div class="card-body">' . nl2br(h($card['body'])) . '</div>';
    }
    echo '<div class="card-meta">';
    echo '<span class="card-author">' . $authorName . '</span>';
    echo '<span class="card-date">' . $audience . ($audience && $eventDate ? ' · ' : '') . $eventDate . '</span>';
    echo '</div>';
    echo '<div class="card-actions">';

    // Intérêt (col 1 seulement)
    if ($colId === 1) {
        echo '<form method="post" action="?action=interest_toggle" style="display:inline">';
        echo csrfField();
        echo '<input type="hidden" name="card_id" value="' . (int) $card['id'] . '">';
        echo '<button type="submit" class="interest-btn ' . $intActive . '" ' . $intTip . '>' . $intLabel . '</button>';
        echo '</form>';
    }

    // Déplacer vers col suivante
    if ($canAct && $colId < 2) {
        $nextCol  = $colId + 1;
        $nextIcon = $nextCol === 1 ? '📅 Planifier' : '✅ Archiver';
        echo '<form method="post" action="?action=card_move" style="display:inline">';
        echo csrfField();
        echo '<input type="hidden" name="card_id" value="' . (int) $card['id'] . '">';
        echo '<input type="hidden" name="to_col"  value="' . $nextCol . '">';
        echo '<button type="submit" class="btn btn-ghost btn-sm">' . $nextIcon . '</button>';
        echo '</form>';
    }

    // Supprimer
    if ($canAct) {
        echo '<form method="post" action="?action=card_delete" style="display:inline;margin-left:auto">';
        echo csrfField();
        echo '<input type="hidden" name="card_id" value="' . (int) $card['id'] . '">';
        echo '<button type="submit" class="btn btn-ghost btn-sm" style="color:#c0392b;border-color:#f5b7b1" ';
        echo 'onclick="return confirm(\'Supprimer cette carte ?\')">✕</button>';
        echo '</form>';
    }

    echo '</div></div>';
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

// ═══════════════════════════════════════════════════════════
//  VUE — PRÊT-O-THÈQUE
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

    // Grouper par catégorie
    $bycat = [];
    foreach ($items as $it) {
        $bycat[$it['category']][] = $it;
    }

    if (empty($items)) {
        echo '<p class="text-muted" style="text-align:center;padding:3rem 0">Aucun objet pour l\'instant. Soyez le premier à proposer quelque chose !</p>';
    }

    foreach (LIB_CAT_META as $cat => $meta) {
        if (empty($bycat[$cat])) {
            continue;
        }
        echo '<div class="section-box mt-2">';
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
    echo '<div class="form-group"><label>Catégorie</label><select name="category">';
    foreach (LIB_CAT_META as $k => $m) {
        echo '<option value="' . h($k) . '">' . $m['emoji'] . ' ' . $m['label'] . '</option>';
    }
    echo '</select></div>';
    echo '<div class="form-group"><label>Titre *</label><input type="text" name="title" required></div>';
    echo '<div class="form-group"><label>Auteur / marque / info</label><input type="text" name="subtitle" placeholder="ex : Robin Hobb, DeWalt, Wingspan…"></div>';
    echo '<div class="form-group"><label>Description</label><textarea name="description" placeholder="État, édition, remarque…"></textarea></div>';
    echo '<div class="modal-actions"><button type="button" class="btn btn-ghost" onclick="document.getElementById(\'lib-modal\').classList.remove(\'open\')">Annuler</button><button type="submit" class="btn btn-primary">Ajouter</button></div>';
    echo '</div></form></div></div>';

    echo <<<'JS'
<script>
document.getElementById('lib-modal').addEventListener('click', function(e){
  if(e.target===this)this.classList.remove('open');
});
</script>
JS;

    echo '</div>';
    layoutClose();
}

function renderLibItem(array $it, array $user): void
{
    $cat     = LIB_CAT_META[$it['category']] ?? LIB_CAT_META['autre'];
    $avail   = (bool) $it['available'];
    $canAct  = $it['owner_id'] == $user['id'] || $user['role'] === 'admin';
    $statusCls   = $avail ? 'avail' : 'taken';
    $statusLabel = $avail ? '✓ Disponible' : '⏳ Emprunté par ' . h($it['borrower_name'] ?? '…');
    $owner       = $it['owner_name'] ? 'Proposé par ' . h($it['owner_name']) : '';

    echo '<div class="lib-card">';
    echo '<div class="lib-card-cat">' . $cat['emoji'] . ' ' . $cat['label'] . '</div>';
    echo '<div class="lib-card-title">' . h($it['title']) . '</div>';
    if ($it['subtitle']) {
        echo '<div class="lib-card-sub">' . h($it['subtitle']) . '</div>';
    }
    if ($it['description']) {
        echo '<div class="lib-card-desc">' . h($it['description']) . '</div>';
    }
    echo '<span class="lib-status ' . $statusCls . '">' . $statusLabel . '</span>';
    if ($owner) {
        echo '<div class="text-sm text-muted mt-1">' . $owner . '</div>';
    }
    echo '<div class="lib-card-actions">';

    if ($avail && $it['owner_id'] != $user['id']) {
        echo '<form method="post" action="?action=library_borrow">';
        echo csrfField();
        echo '<input type="hidden" name="item_id" value="' . (int)$it['id'] . '">';
        echo '<input type="date" name="due_date" title="Date de retour prévue" style="font-size:.78rem;padding:.25rem .5rem;border:1px solid var(--border);border-radius:5px;background:var(--bg)">';
        echo '<button type="submit" class="btn btn-primary btn-sm" style="margin-top:.3rem">📥 Emprunter</button>';
        echo '</form>';
    }

    if (!$avail && $it['borrower_id'] == $user['id']) {
        echo '<form method="post" action="?action=library_return">';
        echo csrfField();
        echo '<input type="hidden" name="loan_id" value="' . (int)$it['loan_id'] . '">';
        echo '<button type="submit" class="btn btn-ghost btn-sm">📤 Retourner</button>';
        echo '</form>';
    }

    // Admin/owner peut forcer le retour ou supprimer
    if ($canAct) {
        if (!$avail) {
            echo '<form method="post" action="?action=library_return">';
            echo csrfField();
            echo '<input type="hidden" name="loan_id" value="' . (int)$it['loan_id'] . '">';
            echo '<button type="submit" class="btn btn-ghost btn-sm">↩ Retour forcé</button>';
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

// ═══════════════════════════════════════════════════════════
//  VUE — MES DONNÉES (RGPD)
// ═══════════════════════════════════════════════════════════

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
    echo '<p class="text-sm text-muted mt-1">Participations signalées : <strong>' . count($data['interests']) . '</strong></p>';
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

// ═══════════════════════════════════════════════════════════
//  VUE — ADMIN
// ═══════════════════════════════════════════════════════════

function viewAdmin(array $user): void
{
    $user = requireAdmin();
    layoutOpen('Administration', $user, 'admin');
    $users = getAllUsers();
    $err   = flash('error');
    $ok    = flash('success');

    echo '<div class="page">';
    echo '<div class="page-header"><h1>⚙️ Administration</h1><p>Gestion des comptes et modération.</p></div>';
    if ($err) echo '<div class="alert alert-error">'   . h($err) . '</div>';
    if ($ok)  echo '<div class="alert alert-success">' . h($ok)  . '</div>';

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
            echo '<div style="display:flex;gap:.3rem">';
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
            echo '</div>';
        } else {
            echo '<em class="text-muted text-sm">Vous</em>';
        }
        echo '</td></tr>';
    }

    echo '</tbody></table></div></div>';

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

// ═══════════════════════════════════════════════════════════
//  VUE — POLITIQUE DE CONFIDENTIALITÉ
// ═══════════════════════════════════════════════════════════

function viewPrivacy(): void
{
    layoutOpen('Politique de confidentialité');
    $resp = h(RGPD_RESPONSABLE);
    $mail = h(RGPD_CONTACT);
    echo <<<HTML
<div class="page" style="max-width:760px;margin:0 auto">
  <div class="page-header">
    <h1>Politique de confidentialité</h1>
    <p>Conformément au Règlement Général sur la Protection des Données (RGPD)</p>
  </div>

  <div class="section-box">
    <div class="section-box-body" style="display:flex;flex-direction:column;gap:1.25rem;line-height:1.7;font-size:.92rem">

      <div>
        <h2 style="font-family:'Lora',serif;font-size:1.05rem;margin-bottom:.5rem">1. Responsable du traitement</h2>
        <p>{$resp} — contact : <strong>{$mail}</strong></p>
      </div>

      <div>
        <h2 style="font-family:'Lora',serif;font-size:1.05rem;margin-bottom:.5rem">2. Données collectées</h2>
        <p>Lors de la création de votre compte, nous collectons :</p>
        <ul style="margin-top:.5rem;padding-left:1.25rem">
          <li><strong>Prénom ou pseudonyme</strong> — peut être un pseudo, pas nécessairement votre vrai nom</li>
          <li><strong>Nom de foyer</strong> (facultatif)</li>
          <li><strong>Mot de passe</strong> stocké sous forme hachée (bcrypt) — jamais lisible</li>
          <li><strong>Date de consentement</strong> RGPD</li>
        </ul>
        <p style="margin-top:.5rem">Nous enregistrons également les cartes que vous créez, vos signalements d'intérêt et vos emprunts dans la prêt-o-thèque.</p>
      </div>

      <div>
        <h2 style="font-family:'Lora',serif;font-size:1.05rem;margin-bottom:.5rem">3. Finalité et base légale</h2>
        <p>Ces données sont utilisées uniquement pour faire fonctionner Le Panneau Vivant : identifier les membres, afficher les contributions, gérer les emprunts.<br>
        <strong>Base légale :</strong> votre consentement explicite (art. 6.1.a RGPD), donné lors de l'inscription.</p>
      </div>

      <div>
        <h2 style="font-family:'Lora',serif;font-size:1.05rem;margin-bottom:.5rem">4. Conservation et sécurité</h2>
        <p>Vos données sont conservées jusqu'à la suppression de votre compte. Elles sont stockées dans une base de données SQLite sur un serveur privé, accessible uniquement par les administrateurs. Aucun transfert hors de l'Union Européenne.</p>
      </div>

      <div>
        <h2 style="font-family:'Lora',serif;font-size:1.05rem;margin-bottom:.5rem">5. Vos droits</h2>
        <ul style="padding-left:1.25rem">
          <li><strong>Accès</strong> — consultez vos données dans « Mon compte »</li>
          <li><strong>Rectification</strong> — contactez un administrateur</li>
          <li><strong>Effacement</strong> — supprimez votre compte dans « Mon compte » ; vos cartes restent visibles de façon anonyme</li>
          <li><strong>Opposition / portabilité</strong> — contactez : {$mail}</li>
          <li><strong>Réclamation</strong> — auprès de la CNIL (cnil.fr)</li>
        </ul>
      </div>

      <div>
        <h2 style="font-family:'Lora',serif;font-size:1.05rem;margin-bottom:.5rem">6. Cookies</h2>
        <p>Un unique cookie de session (httpOnly, SameSite=Lax) est utilisé pour vous maintenir connecté·e. Aucun cookie de traçage ou publicitaire.</p>
      </div>

      <div style="text-align:center;margin-top:.5rem">
        <a href="?action=login" class="btn btn-ghost btn-sm">← Retour</a>
      </div>
    </div>
  </div>
</div>
HTML;
    layoutClose();
}
