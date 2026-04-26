<?php
// ═══════════════════════════════════════════════════════════
//  Sharehood — Script d'installation (première exécution)
//
//  1. Déposez tous les fichiers sur votre serveur
//  2. Accédez à https://votre-site.fr/install.php
//  3. Créez votre compte administrateur
//  4. Supprimez install.php du serveur
// ═══════════════════════════════════════════════════════════

// En-têtes de sécurité
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Bloquer si déjà installé (utilisateurs existants)
$dbExists = file_exists(DB_PATH);
if ($dbExists) {
    initDB();
    $count = (int) getDB()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count > 0) {
        http_response_code(403);
        die('<p style="font-family:sans-serif;padding:2rem;color:#c0392b;max-width:500px">
          <strong>Installation déjà effectuée.</strong><br>
          Supprimez <code>install.php</code> du serveur pour des raisons de sécurité.
        </p>');
    }
} else {
    initDB();
}

// Créer data/.htaccess automatiquement si absent
$dataHtaccess = dirname(DB_PATH) . '/.htaccess';
if (!file_exists($dataHtaccess)) {
    @file_put_contents($dataHtaccess, "Require all denied\n");
}

startSession();

// CSRF simple pour install
if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}
$installCsrf = $_SESSION['install_csrf'];

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF
    if (!hash_equals($installCsrf, $_POST['csrf_token'] ?? '')) {
        $error = 'Requête invalide. Rechargez la page et réessayez.';
    } else {
        $name  = trim($_POST['display_name'] ?? '');
        $pw    = $_POST['password']          ?? '';
        $pw2   = $_POST['password2']         ?? '';
        $invite = trim($_POST['invite_code'] ?? '');
        $gdpr  = !empty($_POST['gdpr_consent']);

        if (!$gdpr) {
            $error = 'Vous devez accepter la politique de confidentialité.';
        } elseif (strlen($pw) < 8) {
            $error = 'Le mot de passe doit faire au moins 8 caractères.';
        } elseif ($pw !== $pw2) {
            $error = 'Les mots de passe ne correspondent pas.';
        } elseif ($invite !== INVITE_CODE) {
            $error = 'Code d\'invitation incorrect (défini dans config.php).';
        } else {
            $err = doRegister($name, $pw, $invite, trim($_POST['household'] ?? ''), $gdpr);
            if ($err) {
                $error = $err;
            } else {
                // Promouvoir le premier utilisateur en admin
                $db = getDB();
                $id = (int) $db->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetchColumn();
                $db->prepare('UPDATE users SET role = "admin" WHERE id = ?')->execute([$id]);
                // Invalider le token pour ne pas soumettre deux fois
                unset($_SESSION['install_csrf']);
                $success = true;
            }
        }
    }
}

$appName = htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Installation — <?= $appName ?></title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: sans-serif; background: #f0e9dc; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 2rem; }
  .box { background: #fff; border-radius: 10px; padding: 2.5rem 2rem; max-width: 480px; width: 100%; box-shadow: 0 4px 20px rgba(0,0,0,.1); }
  h1 { font-size: 1.3rem; margin-bottom: 1.5rem; }
  .form-group { display: flex; flex-direction: column; gap: .35rem; margin-bottom: 1rem; }
  label { font-size: .8rem; font-weight: 600; text-transform: uppercase; color: #888; letter-spacing: .05em; }
  input[type=text], input[type=password] {
    padding: .65rem .9rem; border: 1px solid #ddd; border-radius: 6px;
    font-size: .92rem; width: 100%; outline: none;
  }
  input:focus { border-color: #5c7d63; }
  .check { display: flex; gap: .5rem; align-items: flex-start; font-size: .88rem; color: #666; margin-bottom: 1rem; }
  .check input { margin-top: .2rem; flex-shrink: 0; }
  .btn { display: block; width: 100%; padding: .75rem; background: #5c7d63; color: #fff; border: none; border-radius: 6px; font-size: 1rem; cursor: pointer; }
  .btn:hover { opacity: .9; }
  .alert { padding: .75rem 1rem; border-radius: 6px; font-size: .9rem; margin-bottom: 1rem; }
  .alert-error   { background: #fde8e8; color: #922b21; border: 1px solid #f5b7b1; }
  .alert-success { background: #e8f5e9; color: #1e6e28; border: 1px solid #a9dfb0; }
  .note { font-size: .8rem; color: #888; margin-top: 1.5rem; background: #f7f7f5; padding: .75rem; border-radius: 6px; line-height: 1.6; }
  .note code { background: #efefef; padding: .1rem .3rem; border-radius: 3px; font-size: .85em; }
  a.go { display: inline-block; margin-top: 1rem; padding: .65rem 1.2rem; background: #5c7d63; color: #fff; border-radius: 6px; text-decoration: none; font-size: .9rem; }
  a.go:hover { opacity: .9; }
  .warn { background: #fff8e1; border: 1px solid #ffe082; color: #7d5800; padding: .65rem .9rem; border-radius: 6px; font-size: .84rem; margin-top: 1rem; }
</style>
</head>
<body>
<div class="box">
  <h1>🌿 Installation — <?= $appName ?></h1>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success">
      Compte administrateur créé avec succès !
    </div>
    <a class="go" href="index.php?action=board">Accéder à l'application →</a>
    <div class="warn">⚠️ <strong>Action requise :</strong> supprimez <code>install.php</code> du serveur immédiatement pour sécuriser votre installation.</div>
  <?php else: ?>

  <form method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($installCsrf, ENT_QUOTES, 'UTF-8') ?>">
    <div class="form-group">
      <label>Prénom ou pseudo *</label>
      <input type="text" name="display_name" required maxlength="50" placeholder="ex : Clément" autocomplete="name">
    </div>
    <div class="form-group">
      <label>Mot de passe * <span style="font-weight:400;text-transform:none">(8 caractères minimum)</span></label>
      <input type="password" name="password" required minlength="8" autocomplete="new-password">
    </div>
    <div class="form-group">
      <label>Confirmer le mot de passe *</label>
      <input type="password" name="password2" required minlength="8" autocomplete="new-password">
    </div>
    <div class="form-group">
      <label>Foyer <span style="font-weight:400;text-transform:none">(facultatif)</span></label>
      <input type="text" name="household" maxlength="100" placeholder="ex : Foyer des Tournesols">
    </div>
    <div class="form-group">
      <label>Code d'invitation * <span style="font-weight:400;text-transform:none">(défini dans config.php)</span></label>
      <input type="text" name="invite_code" required autocomplete="off">
    </div>
    <div class="check">
      <input type="checkbox" name="gdpr_consent" id="gdpr" required>
      <label for="gdpr" style="text-transform:none;font-size:.88rem;font-weight:400">
        Je reconnais être le responsable de traitement de cet espace et m'engage à gérer les données
        personnelles des membres conformément au RGPD.
      </label>
    </div>
    <button type="submit" class="btn">Créer le compte administrateur</button>
  </form>

  <div class="note">
    Ce script crée la base de données SQLite et le premier compte (admin).<br>
    Le code d'invitation est défini dans <code>config.php</code> — changez-le avant d'inviter d'autres membres.<br><br>
    <strong>Après installation :</strong> supprimez <code>install.php</code> du serveur.
  </div>

  <?php endif; ?>
</div>
</body>
</html>
