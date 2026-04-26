# 🌿 Sharehood — Le Panneau Vivant

Application de convivialité pour habitats participatifs, coopératives et groupes locaux.

**Fonctionnalités principales :**
- Tableau kanban partagé (idées → planifié → vécu)
- Calendrier des événements à venir
- Prêt-o-thèque (livres, outils, jeux)
- Bilan personnel (emprunts, prêts, activités)
- Gestion des membres et RGPD intégrée

---

## Déploiement en 5 minutes

### Prérequis serveur

- PHP 8.1+
- Extension SQLite3 (`php -m | grep sqlite3`)
- Apache avec `mod_rewrite` et `mod_headers` (ou Nginx)

### Structure des fichiers

```
votre-dossier/
├── .htaccess          ← sécurité Apache (fourni)
├── config.php         ← à modifier avant le lancement
├── functions.php      ← logique métier (ne pas modifier)
├── index.php          ← application complète
├── install.php        ← à supprimer après installation
└── data/
    ├── .htaccess      ← créé automatiquement par install.php
    └── panneau.sqlite ← base de données (créée automatiquement)
```

### Étapes

1. **Déposez les fichiers** sur votre hébergement.

2. **Configurez** `config.php` avant toute chose :
   ```php
   define('APP_NAME',     'Sharehood');
   define('APP_SUBTITLE', 'Votre groupe');
   define('INVITE_CODE',  'mon-code-secret'); // changez-le !
   define('DB_PATH', __DIR__ . '/data/panneau.sqlite');
   define('DEBUG', false); // false en production
   define('RGPD_RESPONSABLE', 'Nom du responsable');
   define('RGPD_CONTACT',     'contact@example.fr');
   ```

3. **Permissions fichiers** :
   ```bash
   chmod 750 data/
   chmod 640 config.php functions.php
   ```

4. **Lancez l'installation** en accédant à :
   ```
   https://votre-site.fr/install.php
   ```
   Créez votre compte administrateur.

5. **Supprimez `install.php`** immédiatement après l'installation.

6. **Invitez les membres** en leur communiquant l'URL et le code d'invitation.

---

## Fonctionnalités détaillées

### 🌿 Tableau d'activités

- Trois colonnes : **Idées** / **À planifier** / **Vécu**
- Tags, statuts (à planifier, planifiée, annulée, reportée), audience
- Sondage de dates collaboratif par carte
- Confirmation de présence (✓ / ✗) sur les événements planifiés
- Commentaires par carte (collapsibles, suppression auteur/admin)
- Barre de filtres client-side (texte, tag, audience)
- Calendrier mensuel des événements planifiés — clic sur un événement met la carte en évidence

### 📚 Prêt-o-thèque

- Catégories : Livres, Outils, Jeux, Autre
- Fiches enrichies : URL, durée de partie, tranche d'âge, nombre de joueurs (jeux) ; genre et âge cible (livres)
- Édition des fiches après publication (propriétaire ou admin)
- Gestion de l'état : OK / Perdu / Cassé
- Journal des emprunts en cours (onglet dédié)
- Classement des objets les plus empruntés
- Barre de recherche et filtres (catégorie, disponibilité, état)
- Navigation par onglets-pilules par catégorie

### 📊 Bilan personnel

- Mes objets actuellement prêtés (avec emprunteur et durée)
- Mes emprunts en cours (avec bouton retour direct)
- Activités qui m'intéressent (marquées via ✦)
- Mes présences confirmées sur les événements planifiés
- Mes cartes proposées

### 👤 Mon compte

- Changement de mot de passe
- Suppression du compte (données anonymisées, contenu conservé)
- Export RGPD des données personnelles

### ⚙️ Administration

- Activation / désactivation de comptes membres
- Attribution des rôles (admin / member / external)
- Suppression de comptes
- Registre de traitement RGPD

---

## Sécurité

| Mesure | Détail |
|---|---|
| Mots de passe | bcrypt, coût 12 |
| Protection CSRF | Token par session, `hash_equals()` |
| Anti-brute-force | 5 tentatives / 15 min par identifiant |
| Requêtes SQL | PDO préparé uniquement |
| Cookies de session | `httpOnly`, `SameSite=Lax`, `secure` si HTTPS |
| En-têtes HTTP | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `CSP`, `Permissions-Policy` |
| Accès fichiers | `config.php` et `functions.php` bloqués par `.htaccess` |
| Base de données | `data/` protégé par `.htaccess` (créé par install.php) |
| Erreurs | `display_errors = off` en production (DEBUG = false) |

**Après installation :** décommentez la section `install.php` dans `.htaccess` pour bloquer son accès même si vous oubliez de le supprimer.

---

## RGPD

- Données collectées : prénom/pseudo, foyer (optionnel), mot de passe haché, date de consentement RGPD.
- Consentement explicite recueilli à l'inscription.
- Droit de suppression : dans *Mon compte* — le contenu est conservé mais anonymisé (auteur = NULL).
- Responsable de traitement configurable dans `config.php`.
- Registre de traitement visible dans le panneau Admin.

---

## Rôles utilisateurs

| Rôle | Droits |
|---|---|
| `admin` | Tout : gestion membres, suppression contenu, retour forcé d'emprunt |
| `member` | Création de cartes, emprunts, commentaires, sondages, présences |
| `external` | Accès lecture + participation limitée (à définir selon vos besoins) |

---

## Licence

Usage libre pour habitat participatif, coopérative, association ou usage personnel.
