<?php
// ═══════════════════════════════════════════════════════════
//  Module Auth — Vues
// ═══════════════════════════════════════════════════════════

function viewLogin(): void
{
    layoutOpen('Connexion');
    $err = flash('error');
    echo '<div class="auth-wrap">';
    echo '<div><h1 style="font-family:\'Lora\',serif;font-size:1.8rem;text-align:center">🌿 ' . h(getSetting('app_name', APP_NAME)) . '</h1>';
    echo '<p style="text-align:center;color:var(--muted);margin-top:.4rem">' . h(getSetting('app_subtitle', APP_SUBTITLE)) . '</p></div>';
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

function viewRegister(): void
{
    layoutOpen('Créer un compte');
    $err = flash('error');
    if ($err) {
        echo '<div style="max-width:420px;margin:1rem auto"><div class="alert alert-error">' . h($err) . '</div></div>';
    }
    echo '<div class="auth-wrap">';
    echo '<div><h1 style="font-family:\'Lora\',serif;font-size:1.8rem;text-align:center">🌿 ' . h(getSetting('app_name', APP_NAME)) . '</h1>';
    echo '<p style="text-align:center;color:var(--muted);margin-top:.4rem">' . h(getSetting('app_subtitle', APP_SUBTITLE)) . '</p></div>';
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

function viewForgotPassword(): void
{
    layoutOpen('Mot de passe oublié');
    $err = flash('error');
    $ok  = flash('success');

    echo '<div class="auth-wrap">';
    echo '<div><h1 style="font-family:\'Lora\',serif;font-size:1.8rem;text-align:center">🌿 ' . h(getSetting('app_name', APP_NAME)) . '</h1>';
    echo '<p style="text-align:center;color:var(--muted);margin-top:.4rem">' . h(getSetting('app_subtitle', APP_SUBTITLE)) . '</p></div>';

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

function viewResetPassword(string $token): void
{
    layoutOpen('Nouveau mot de passe');
    $err = flash('error');

    echo '<div class="auth-wrap">';
    echo '<div><h1 style="font-family:\'Lora\',serif;font-size:1.8rem;text-align:center">🌿 ' . h(getSetting('app_name', APP_NAME)) . '</h1>';
    echo '<p style="text-align:center;color:var(--muted);margin-top:.4rem">' . h(getSetting('app_subtitle', APP_SUBTITLE)) . '</p></div>';

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

function viewPrivacy(): void
{
    layoutOpen('Politique de confidentialité');
    $resp    = h(RGPD_RESPONSABLE);
    $mail    = h(RGPD_CONTACT);
    $appName = h(getSetting('app_name', APP_NAME));
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
        <p>Ces données sont utilisées uniquement pour faire fonctionner {$appName} : identifier les membres, afficher les contributions, gérer les emprunts.<br>
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
