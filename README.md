# 🌿 Le Panneau Vivant

Application de convivialité pour habitats participatifs.  
Tableau partagé + Prêt-o-thèque + gestion RGPD.

---

## Déploiement en 5 minutes

### Prérequis serveur
- PHP 8.1+
- Extension SQLite3 activée (`php -m | grep sqlite`)
- Apache avec mod_rewrite et mod_headers (ou Nginx)

### Étapes

1. **Déposez les fichiers** sur votre hébergement dans un sous-dossier :
   ```
   /public_html/panneau-vivant/
   ├── .htaccess
   ├── config.php
   ├── functions.php
   ├── index.php
   ├── install.php
   └── data/
       └── .htaccess
   ```

2. **Configurez** `config.php` :
   - Changez `INVITE_CODE` (code pour créer des comptes)
   - Adaptez `APP_SUBTITLE` et `RGPD_RESPONSABLE`
   - Vérifiez que `DB_PATH` est accessible en écriture

3. **Lancez l'installation** :
   Accédez à `https://votre-site.fr/panneau-vivant/install.php`
   Créez votre compte administrateur.

4. **Supprimez `install.php`** du serveur après installation.

5. **Invitez les membres** en partageant l'URL et le code d'invitation.

---

## Permissions fichiers

```bash
chmod 750 data/
chmod 640 config.php functions.php
```

Si la base SQLite ne se crée pas, vérifiez que le dossier `data/` est accessible en écriture par le serveur web (www-data).

---

## Structure des fichiers

| Fichier | Rôle |
|---|---|
| `config.php` | **À modifier** — paramètres de l'application |
| `functions.php` | Logique métier (DB, auth, CRUD) |
| `index.php` | Routeur + toutes les vues |
| `install.php` | Installation (supprimer après usage) |
| `.htaccess` | Sécurité Apache |
| `data/.htaccess` | Protection de la base SQLite |
| `data/panneau.sqlite` | Base de données (créée automatiquement) |

---

## Sécurité

- Mots de passe hachés en **bcrypt** (coût 12)
- Protection **CSRF** sur tous les formulaires
- **Rate limiting** sur le login (5 tentatives / 15 min)
- Requêtes SQL via **PDO préparé** (protection injection)
- Session **httpOnly + SameSite=Lax**
- Dossier `data/` protégé contre l'accès web direct

---

## RGPD

Données collectées : prénom/pseudo, foyer (optionnel), mot de passe haché, date de consentement.  
Droit de suppression : dans « Mon compte » → le contenu est conservé anonymisé.  
Registre de traitement visible dans le panneau Admin.

---

## Évolutions prévues

- [x] Tableau 3 colonnes (idées → planifié → vécu)
- [x] Prêt-o-thèque (livres, outils, jeux)
- [x] Gestion des membres (rôles admin/member/external)
- [ ] Notifications par email (optionnel)
- [ ] Ouverture aux membres extérieurs (rôle `external` déjà présent)
- [ ] Système de commentaires sur les cartes
- [ ] Calendrier des événements

---

## Licence

Usage libre pour habitat participatif, coopérative, ou usage personnel.
