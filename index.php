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

// ── En-têtes de sécurité HTTP (avant tout output)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self' https://fonts.googleapis.com https://fonts.gstatic.com");

startSession();
initDB();
migrateDB();

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

        case 'date_poll_add':
            $user = requireAuth();
            if (!addDatePoll((int)($_POST['card_id'] ?? 0), trim($_POST['proposed_date'] ?? ''), $user['id'])) {
                flash('error', 'Date invalide ou déjà proposée.');
            }
            redirect('?action=board');

        case 'date_poll_delete':
            $user = requireAuth();
            deleteDatePoll((int)($_POST['poll_id'] ?? 0), $user);
            redirect('?action=board');

        case 'date_poll_vote':
            $user = requireAuth();
            toggleDatePollVote((int)($_POST['poll_id'] ?? 0), $user['id']);
            redirect('?action=board');

        case 'card_confirm_date':
            $user = requireAuth();
            if (!confirmCardDate((int)($_POST['card_id'] ?? 0), trim($_POST['event_date'] ?? ''), $user)) {
                flash('error', 'Impossible de confirmer la date.');
            } else {
                flash('success', 'Date confirmée ! Les membres peuvent confirmer leur présence.');
            }
            redirect('?action=board');

        case 'card_status_update':
            $user = requireAuth();
            updateCardStatus((int)($_POST['card_id'] ?? 0), trim($_POST['status'] ?? ''), $user);
            redirect('?action=board');

        case 'presence_toggle':
            $user = requireAuth();
            togglePresence((int)($_POST['card_id'] ?? 0), $user['id'], (int)($_POST['attending'] ?? 1));
            redirect('?action=board');

        case 'comment_add':
            $user = requireAuth();
            if (!addComment((int)($_POST['card_id'] ?? 0), $user['id'], trim($_POST['body'] ?? ''))) {
                flash('error', 'Commentaire vide.');
            }
            redirect('?action=board');

        case 'comment_delete':
            $user = requireAuth();
            deleteComment((int)($_POST['comment_id'] ?? 0), $user);
            redirect('?action=board');

        case 'library_condition':
            $user = requireAuth();
            setItemCondition((int)($_POST['item_id'] ?? 0), trim($_POST['condition'] ?? ''), $user);
            redirect('?action=library');

        case 'library_edit':
            $user = requireAuth();
            if (empty(trim($_POST['title'] ?? ''))) {
                flash('error', 'Le titre est obligatoire.');
            } elseif (!updateLibraryItem((int)($_POST['item_id'] ?? 0), $_POST, $user)) {
                flash('error', 'Modification non autorisée.');
            } else {
                flash('success', 'Fiche mise à jour.');
            }
            redirect('?action=library');

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

        case 'admin_reset_password':
            requireAdmin();
            $err = adminResetPassword((int)($_POST['user_id'] ?? 0), $_POST['new_password'] ?? '');
            if ($err) {
                flash('error', $err);
            } else {
                flash('success', 'Mot de passe réinitialisé avec succès.');
            }
            redirect('?action=admin');

        case 'admin_generate_reset':
            requireAdmin();
            $tok = adminGenerateResetToken((int)($_POST['user_id'] ?? 0));
            if ($tok) {
                $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
                         . strtok($_SERVER['REQUEST_URI'], '?');
                flash('success', 'reset_url:' . $baseUrl . '?action=reset_password&token=' . $tok);
            } else {
                flash('error', 'Impossible de générer le lien (utilisateur introuvable ou inactif).');
            }
            redirect('?action=admin');

        case 'forgot_password':
            $token = createPasswordResetToken(trim($_POST['display_name'] ?? ''));
            flash('success', 'Demande enregistrée. Contactez un administrateur pour recevoir votre lien de réinitialisation.');
            redirect('?action=forgot_password');

        case 'reset_password':
            $err = consumePasswordResetToken($_POST['token'] ?? '', $_POST['new_password'] ?? '');
            if ($err) {
                flash('error', $err);
                redirect('?action=reset_password&token=' . urlencode($_POST['token'] ?? ''));
            }
            flash('success', 'Mot de passe modifié. Vous pouvez maintenant vous connecter.');
            redirect('?action=login');
    }
}

// ───────────────────────────────────────────────────────────
//  GET — dispatch vers les vues
// ───────────────────────────────────────────────────────────

if (in_array($action, ['login', 'register', 'privacy', 'forgot_password', 'reset_password'], true)) {
    if ($user && $action === 'login') {
        redirect('?action=board');
    }
} else {
    $user = requireAuth();
}

switch ($action) {
    case 'login':          viewLogin();                                  break;
    case 'register':       viewRegister();                               break;
    case 'privacy':        viewPrivacy();                                break;
    case 'forgot_password': viewForgotPassword();                        break;
    case 'reset_password':  viewResetPassword($_GET['token'] ?? '');     break;
    case 'board':          viewBoard($user);                             break;
    case 'library':        viewLibrary($user);                          break;
    case 'dashboard':      viewDashboard($user);                        break;
    case 'my_data':        viewMyData($user);                           break;
    case 'admin':          viewAdmin($user);                            break;
    default:               redirect('?action=board');
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
        $board   = $currentAction === 'board'     ? 'active' : '';
        $library = $currentAction === 'library'   ? 'active' : '';
        $bilan   = $currentAction === 'dashboard' ? 'active' : '';
        $mydata  = $currentAction === 'my_data'   ? 'active' : '';
        $admin   = $currentAction === 'admin'     ? 'active' : '';
        $uname   = h($user['display_name']);
        $csrf    = csrfField();
        echo <<<HTML
<nav>
  <a class="nav-brand" href="?action=board">{$appName} <small>{$appSub}</small></a>
  <button class="nav-toggle" aria-label="Menu" aria-expanded="false" onclick="toggleNav(this)">☰</button>
  <div class="nav-links" id="nav-links">
    <a href="?action=board"     class="{$board}">🌿 Tableau</a>
    <a href="?action=library"   class="{$library}">📚 Prêt-o-thèque</a>
    <a href="?action=dashboard" class="{$bilan}">📊 Bilan</a>
    <a href="?action=my_data"   class="{$mydata}">👤 {$uname}</a>
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
  <p class="text-sm text-muted" style="text-align:center">
    <a href="?action=forgot_password">Mot de passe oublié ?</a>
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

    // Événements pour le calendrier (col 1, planifiée)
    $calEvents = [];
    foreach ($cards[1] ?? [] as $c) {
        if (($c['status'] ?? '') === 'planifiee' && !empty($c['event_date'])) {
            $calEvents[] = ['id' => (int)$c['id'], 'date' => $c['event_date'], 'title' => $c['title']];
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

    // ── Modal ajout de carte
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
function calClickEvent(cardId) {
  // Retirer les anciens highlights
  document.querySelectorAll('.card.cal-focus').forEach(c => c.classList.remove('cal-focus'));
  const card = document.querySelector('.card[data-id="' + cardId + '"]');
  if (!card) return;
  card.classList.add('cal-focus');
  card.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
function renderCal() {
  const today  = new Date().toISOString().slice(0, 10);
  const first  = new Date(calY, calM, 1).getDay();
  const offset = (first + 6) % 7; // lundi en premier
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
      const t = e.title.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
      html += '<div class="cal-ev" onclick="calClickEvent(' + e.id + ')" title="' + t + '">' + t + '</div>';
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

    // ── En-tête : tag + statut + public · bouton supprimer (à droite)
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

    // ── Titre et description
    echo '<h3 class="card-title">' . h($card['title']) . '</h3>';
    if ($card['body']) {
        echo '<p class="card-body">' . nl2br(h($card['body'])) . '</p>';
    }

    // ── Méta : auteur · date (planifiée)
    $dateStr = ($card['event_date'] && $status === 'planifiee') ? fmtDate($card['event_date']) : '';
    echo '<div class="card-meta">';
    echo '<span class="card-author">— ' . $authorName . '</span>';
    if ($dateStr) {
        echo '<time class="card-date" datetime="' . h($card['event_date'] ?? '') . '">📅 ' . $dateStr . '</time>';
    }
    echo '</div>';

    // ── Pied : intérêt (gauche) · bouton avancer (droite)
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

    // ── Section planification (col 1 uniquement)
    if ($colId === 1) {
        renderPlanningSection($card, $user, $cardId, $status, $canAct);
    }

    // ── Section commentaires
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

    // ── État a_planifier : sondage de dates
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

    // ── Proposer une date (tous les membres)
    echo '<div class="poll-add-row">';
    echo '<form method="post" action="?action=date_poll_add" style="display:flex;gap:.3rem;flex-wrap:wrap;align-items:center">';
    echo csrfField();
    echo '<input type="hidden" name="card_id" value="' . $cardId . '">';
    echo '<label for="date-prop-' . $cardId . '" class="sr-only">Proposer une date</label>';
    echo '<input type="date" id="date-prop-' . $cardId . '" name="proposed_date" required style="font-size:.82rem;padding:.28rem .5rem;border:1px solid var(--border);border-radius:5px;background:var(--bg)">';
    echo '<button type="submit" class="btn btn-ghost btn-sm">+ Proposer</button>';
    echo '</form>';
    echo '</div>';

    // ── Admin / auteur : confirmer la date et gérer le statut (dans <details>)
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

    // ── Gérer la date et le statut (admin/auteur uniquement, dans <details>)
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

    // Grouper par catégorie + pré-charger les stats (pour les onglets)
    $bycat       = [];
    foreach ($items as $it) {
        $bycat[$it['category']][] = $it;
    }
    $activeLoans = getActiveLoans();
    $topItems    = getTopItems(10);
    $topFiltered = array_filter($topItems, fn($i) => (int)$i['loan_count'] > 0);

    // ── Onglets de navigation
    echo '<div class="lib-tabs">';
    echo '<button class="lib-tab active" onclick="switchLibTab(\'\', this)">Tout';
    if (count($items) > 0) {
        echo ' <span style="opacity:.65">(' . count($items) . ')</span>';
    }
    echo '</button>';
    foreach (LIB_CAT_META as $k => $m) {
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

    // ── Barre de filtres texte (masquée sur onglets Emprunts / Top)
    echo '<div class="filter-bar" id="lib-filter-bar">';
    echo '<input type="search" id="lib-search" placeholder="🔍 Rechercher titre, auteur, description…" oninput="filterLib()">';
    echo '<select id="lib-cat" onchange="filterLib()"><option value="">Toutes catégories</option>';
    foreach (LIB_CAT_META as $k => $m) {
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

    foreach (LIB_CAT_META as $cat => $meta) {
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
    foreach (LIB_CAT_META as $k => $m) {
        echo '<option value="' . h($k) . '">' . $m['emoji'] . ' ' . $m['label'] . '</option>';
    }
    echo '</select></div>';
    echo '<div class="form-group"><label>Titre *</label><input type="text" name="title" required></div>';
    echo '<div class="form-group"><label>Auteur / marque / info</label><input type="text" name="subtitle" placeholder="ex : Robin Hobb, DeWalt, Wingspan…"></div>';
    echo '<div class="form-group"><label>Description</label><textarea name="description" placeholder="État, édition, remarque…"></textarea></div>';
    echo '<div class="form-group"><label>Lien (URL)</label><input type="url" name="url" placeholder="https://…"></div>';
    // ── Champs jeux
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
    // ── Champs livres
    echo '<div id="add-book-fields" style="display:none;flex-direction:column;gap:1rem">';
    echo '<div class="grid-2">';
    echo '<div class="form-group"><label>Genre / catégorie</label><input type="text" name="book_genre" placeholder="ex : Roman, BD, Essai…"></div>';
    echo '<div class="form-group"><label>Âge cible</label><input type="number" name="age_min" min="0" max="99" placeholder="ex : 12"></div>';
    echo '</div>';
    echo '</div>';
    echo '<div class="modal-actions"><button type="button" class="btn btn-ghost" onclick="document.getElementById(\'lib-modal\').classList.remove(\'open\')">Annuler</button><button type="submit" class="btn btn-primary">Ajouter</button></div>';
    echo '</div></form></div></div>';

    // ── Modal d'édition (unique, peuplé par JS)
    echo '<div class="modal-overlay" id="lib-edit-modal">';
    echo '<div class="modal">';
    echo '<h3>Modifier la fiche</h3>';
    echo '<form method="post" action="?action=library_edit">';
    echo csrfField();
    echo '<input type="hidden" name="item_id" id="edit-item-id">';
    echo '<div style="display:flex;flex-direction:column;gap:1rem">';
    echo '<div class="form-group"><label>Catégorie</label><select name="category" id="edit-cat" onchange="updateLibFields(this.value,\'edit\')">';
    foreach (LIB_CAT_META as $k => $m) {
        echo '<option value="' . h($k) . '">' . $m['emoji'] . ' ' . $m['label'] . '</option>';
    }
    echo '</select></div>';
    echo '<div class="form-group"><label>Titre *</label><input type="text" name="title" id="edit-title" required></div>';
    echo '<div class="form-group"><label>Auteur / marque / info</label><input type="text" name="subtitle" id="edit-subtitle" placeholder="ex : Robin Hobb, DeWalt, Wingspan…"></div>';
    echo '<div class="form-group"><label>Description</label><textarea name="description" id="edit-description" placeholder="État, édition, remarque…"></textarea></div>';
    echo '<div class="form-group"><label>Lien (URL)</label><input type="url" name="url" id="edit-url" placeholder="https://…"></div>';
    // ── Champs jeux
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
    // ── Champs livres
    echo '<div id="edit-book-fields" style="display:none;flex-direction:column;gap:1rem">';
    echo '<div class="grid-2">';
    echo '<div class="form-group"><label>Genre / catégorie</label><input type="text" name="book_genre" id="edit-book-genre" placeholder="ex : Roman, BD, Essai…"></div>';
    echo '<div class="form-group"><label>Âge cible</label><input type="number" name="age_min" id="edit-age-min-book" min="0" max="99" placeholder="ex : 12"></div>';
    echo '</div>';
    echo '</div>';
    echo '<div class="modal-actions"><button type="button" class="btn btn-ghost" onclick="document.getElementById(\'lib-edit-modal\').classList.remove(\'open\')">Annuler</button><button type="submit" class="btn btn-primary">Enregistrer</button></div>';
    echo '</div></form></div></div>';

    // ── Journal des emprunts en cours (onglet dédié, caché par défaut)
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
            $lcat    = LIB_CAT_META[$loan['category']] ?? LIB_CAT_META['autre'];
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

    // ── Top des objets empruntés (onglet dédié, caché par défaut)
    if (!empty($topFiltered)) {
        echo '<div class="section-box mt-2" id="lib-panel-top" style="display:none">';
        echo '<div class="section-box-header"><h2>🏆 Top des objets empruntés</h2></div>';
        echo '<div style="overflow-x:auto"><table class="data-table">';
        echo '<thead><tr><th>#</th><th>Objet</th><th>Nb emprunts</th><th>Durée cumulée</th><th>État</th></tr></thead><tbody>';
        $rank = 1;
        foreach ($topFiltered as $top) {
            $tcat  = LIB_CAT_META[$top['category']] ?? LIB_CAT_META['autre'];
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
    $cat       = LIB_CAT_META[$it['category']] ?? LIB_CAT_META['autre'];
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

    // ── Métadonnées enrichies
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

// ═══════════════════════════════════════════════════════════
//  VUE — BILAN PERSONNEL
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
            $cat    = LIB_CAT_META[$row['category']] ?? LIB_CAT_META['autre'];
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
            $cat    = LIB_CAT_META[$row['category']] ?? LIB_CAT_META['autre'];
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

    echo '</div>';
    layoutClose();
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

// ═══════════════════════════════════════════════════════════
//  VUE — MOT DE PASSE OUBLIÉ
// ═══════════════════════════════════════════════════════════

function viewForgotPassword(): void
{
    layoutOpen('Mot de passe oublié');
    $err = flash('error');
    $ok  = flash('success');

    echo '<div class="auth-wrap">';
    echo '<div><h1 style="font-family:\'Lora\',serif;font-size:1.8rem;text-align:center">🌿 ' . h(APP_NAME) . '</h1>';
    echo '<p style="text-align:center;color:var(--muted);margin-top:.4rem">' . h(APP_SUBTITLE) . '</p></div>';

    if ($err) echo '<div class="alert alert-error" style="max-width:420px;width:100%">'   . h($err) . '</div>';
    if ($ok)  echo '<div class="alert alert-success" style="max-width:420px;width:100%">' . h($ok)  . '</div>';

    if (!$ok) {
        echo '<form class="auth-box" method="post" action="?action=forgot_password">';
        echo csrfField();
        echo '<h1>Mot de passe oublié</h1>';
        echo '<p class="text-sm text-muted" style="margin-bottom:.5rem">Saisissez votre prénom ou pseudo. Un administrateur vous communiquera ensuite un lien de réinitialisation.</p>';
        echo '<div class="form-group"><label>Prénom / pseudo</label><input type="text" name="display_name" required autofocus autocomplete="username"></div>';
        echo '<button type="submit" class="btn btn-primary w-full">Envoyer la demande</button>';
        echo '<p class="text-sm text-muted" style="text-align:center"><a href="?action=login">← Retour à la connexion</a></p>';
        echo '</form>';
    } else {
        echo '<div class="auth-box">';
        echo '<h1>Demande enregistrée</h1>';
        echo '<p class="text-sm text-muted">Un administrateur va préparer votre lien de réinitialisation et vous le communiquer.</p>';
        echo '<a href="?action=login" class="btn btn-primary w-full" style="margin-top:1rem;display:block;text-align:center">Retour à la connexion</a>';
        echo '</div>';
    }

    echo '</div>';
    layoutClose();
}

// ═══════════════════════════════════════════════════════════
//  VUE — RÉINITIALISATION DU MOT DE PASSE
// ═══════════════════════════════════════════════════════════

function viewResetPassword(string $token): void
{
    layoutOpen('Nouveau mot de passe');
    $err = flash('error');

    echo '<div class="auth-wrap">';
    echo '<div><h1 style="font-family:\'Lora\',serif;font-size:1.8rem;text-align:center">🌿 ' . h(APP_NAME) . '</h1>';
    echo '<p style="text-align:center;color:var(--muted);margin-top:.4rem">' . h(APP_SUBTITLE) . '</p></div>';

    $valid = $token && validatePasswordResetToken($token);

    if (!$valid) {
        echo '<div class="auth-box">';
        echo '<h1>Lien invalide</h1>';
        echo '<p class="text-sm text-muted">Ce lien de réinitialisation est invalide ou a expiré (durée de validité : 1 heure).</p>';
        echo '<p class="text-sm text-muted" style="margin-top:.75rem">Faites une nouvelle demande si nécessaire.</p>';
        echo '<a href="?action=forgot_password" class="btn btn-primary w-full" style="margin-top:1rem;display:block;text-align:center">Nouvelle demande</a>';
        echo '</div>';
    } else {
        if ($err) echo '<div class="alert alert-error" style="max-width:420px;width:100%">' . h($err) . '</div>';
        echo '<form class="auth-box" method="post" action="?action=reset_password">';
        echo csrfField();
        echo '<input type="hidden" name="token" value="' . h($token) . '">';
        echo '<h1>Nouveau mot de passe</h1>';
        echo '<div class="form-group"><label>Nouveau mot de passe</label><input type="password" name="new_password" required minlength="8" autofocus autocomplete="new-password"></div>';
        echo '<div class="form-group"><label>Confirmer le mot de passe</label><input type="password" id="pw_confirm" required minlength="8" autocomplete="new-password"></div>';
        echo '<button type="submit" class="btn btn-primary w-full" id="pw_submit">Enregistrer le nouveau mot de passe</button>';
        echo '</form>';
        echo '<script>
document.querySelector("form").addEventListener("submit", function(e) {
    var p1 = this.querySelector("[name=new_password]").value;
    var p2 = document.getElementById("pw_confirm").value;
    if (p1 !== p2) { e.preventDefault(); alert("Les mots de passe ne correspondent pas."); }
});
</script>';
    }

    echo '</div>';
    layoutClose();
}

// ═══════════════════════════════════════════════════════════
//  VUE — ADMIN
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
