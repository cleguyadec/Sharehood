<?php
// ═══════════════════════════════════════════════════════════
//  Panneau Vivant — Script d'installation (première exécution)
//
//  1. Déposez tous les fichiers sur votre serveur
//  2. Accédez à https://votre-site.fr/panneau-vivant/install.php
//  3. Créez votre compte administrateur
//  4. Supprimez install.php du serveur
// ═══════════════════════════════════════════════════════════

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Si la DB existe déjà et contient des utilisateurs → bloquer
$dbExists = file_exists(DB_PATH);
if ($dbExists) {
    initDB();
    $count = (int) getDB()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count > 0) {
        die('<h2 style="font-family:sans-serif;padding:2rem;color:#c0392b">
          Installation déjà effectuée. Supprimez install.php du serveur.
        </h2>');
    }
} else {
    initDB();
}

startSession();
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['display_name'] ?? '');
    $pw       = $_POST['password']          ?? '';
    $pw2      = $_POST['password2']         ?? '';
    $invite   = trim($_POST['invite_code']  ?? '');
    $gdpr     = !empty($_POST['gdpr_consent']);

    if (!$gdpr) {
        $error = 'Vous devez accepter la politique de confidentialité.';
    } elseif ($pw !== $pw2) {
        $error = 'Les mots de passe ne correspondent pas.';
    } elseif ($invite !== INVITE_CODE) {
        $error = 'Code d\'invitation incorrect (défini dans config.php).';
    } else {
        $err = doRegister($name, $pw, $invite, $_POST['household'] ?? '', $gdpr);
        if ($err) {
            $error = $err;
        } else {
            // Promouvoir le premier utilisateur en admin
            $id = (int) getDB()->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetchColumn();
            getDB()->prepare('UPDATE users SET role = "admin" WHERE id = ?')->execute([$id]);

            $success = 'Compte administrateur créé ! <strong>Supprimez install.php du serveur maintenant.</strong>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Installation — Le Panneau Vivant</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: sans-serif; background: #f0e9dc; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 2rem; }
  .box { background: #fff; border-radius: 10px; padding: 2.5rem 2rem; max-width: 460px; width: 100%; box-shadow: 0 4px 20px rgba(0,0,0,.1); }
  h1 { font-size: 1.4rem; margin-bottom: 1.5rem; }
  .form-group { display: flex; flex-direction: column; gap: .35rem; margin-bottom: 1rem; }
  label { font-size: .8rem; font-weight: 600; text-transform: uppercase; color: #888; letter-spacing: .05em; }
  input[type=text], input[type=password] { padding: .65rem .9rem; border: 1px solid #ddd; border-radius: 6px; font-size: .92rem; }
  .check { display: flex; gap: .5rem; align-items: flex-start; font-size: .88rem; color: #666; margin-bottom: 1rem; }
  .btn { display: block; width: 100%; padding: .75rem; background: #5c7d63; color: #fff; border: none; border-radius: 6px; font-size: 1rem; cursor: pointer; }
  .alert { padding: .75rem 1rem; border-radius: 6px; font-size: .9rem; margin-bottom: 1rem; }
  .alert-error   { background: #fde8e8; color: #922b21; }
  .alert-success { background: #e8f5e9; color: #1e6e28; }
  .note { font-size: .8rem; color: #999; margin-top: 1.5rem; background: #f5f5f5; padding: .75rem; border-radius: 6px; }
  a.go { display: inline-block; margin-top: 1rem; padding: .65rem 1.2rem; background: #5c7d63; color: #fff; border-radius: 6px; text-decoration: none; font-size: .9rem; }
</style>
</head>
<body>
<div class="box">
  <h1>🌿 Installation — Le Panneau Vivant</h1>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
    <a class="go" href="?action=board">Accéder à l'application →</a>
    <div class="note">⚠️ Pensez à supprimer <code>install.php</code> de votre serveur.</div>
  <?php else: ?>

  <form method="post">
    <div class="form-group">
      <label>Prénom ou pseudo *</label>
      <input type="text" name="display_name" required maxlength="50" placeholder="ex : Clément">
    </div>
    <div class="form-group">
      <label>Mot de passe * (8 min.)</label>
      <input type="password" name="password" required minlength="8">
    </div>
    <div class="form-group">
      <label>Confirmer le mot de passe *</label>
      <input type="password" name="password2" required minlength="8">
    </div>
    <div class="form-group">
      <label>Foyer (facultatif)</label>
      <input type="text" name="household" maxlength="100" placeholder="ex : Foyer des Tournesols">
    </div>
    <div class="form-group">
      <label>Code d'invitation * (défini dans config.php)</label>
      <input type="text" name="invite_code" required>
    </div>
    <div class="check">
      <input type="checkbox" name="gdpr_consent" id="gdpr" required>
      <label for="gdpr" style="text-transform:none;font-size:.88rem;font-weight:400">
        Je reconnais être le responsable de traitement de cet espace et accepte de gérer les données
        personnelles des membres conformément au RGPD.
      </label>
    </div>
    <button type="submit" class="btn">Créer le compte administrateur</button>
  </form>

  <div class="note">
    Ce script crée la base de données SQLite et le premier compte (admin).
    Le code d'invitation est défini dans <code>config.php</code> — changez-le avant d'inviter d'autres membres.
  </div>

  <?php endif; ?>
</div>
</body>
</html>
