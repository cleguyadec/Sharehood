# 🌿 Sharehood — Le Panneau Vivant

Application de convivialité pour habitats participatifs, coopératives et groupes locaux.

**Fonctionnalités :**
- Tableau kanban partagé (idées → planifié → vécu)
- Calendrier des événements avec sondage de dates
- Prêt-o-thèque (livres, outils, jeux — catégories personnalisables)
- Achats groupés
- Bilan personnel (emprunts, prêts, activités)
- Gestion des membres et RGPD intégrée
- Administration : identité de l'application, catégories, comptes

---

## Déploiement

### Prérequis

- PHP 8.1+ avec l'extension SQLite3 (`php -m | grep sqlite3`)
- Apache avec `mod_rewrite` et `mod_headers` (ou Nginx)

### Structure des fichiers

```
votre-dossier/
├── .htaccess          ← sécurité Apache (fourni)
├── config.php         ← à modifier avant le lancement
├── index.php          ← dispatcher (76 lignes)
├── install.php        ← à supprimer après installation
├── core/
│   ├── db.php         ← connexion base de données + migrations
│   ├── auth.php       ← session, CSRF, authentification
│   ├── helpers.php    ← fonctions utilitaires, layout HTML
│   └── settings.php   ← paramètres dynamiques (admin)
├── modules/
│   ├── auth/          ← connexion, inscription, reset mot de passe
│   ├── board/         ← tableau kanban
│   ├── library/       ← prêt-o-thèque
│   ├── group_orders/  ← achats groupés
│   ├── admin/         ← panneau d'administration
│   └── profile/       ← dashboard et mes données
└── data/
    ├── .htaccess      ← créé automatiquement par install.php
    └── panneau.sqlite ← base de données SQLite
```

### Étapes

1. **Déposez les fichiers** sur votre hébergement (décompressez le zip).

2. **Configurez** `config.php` :
   ```php
   define('APP_NAME',     'Sharehood');        // nom affiché (modifiable depuis l'admin)
   define('APP_SUBTITLE', 'Votre groupe');     // sous-titre (modifiable depuis l'admin)
   define('INVITE_CODE',  'mon-code-secret'); // changez-le !
   define('DB_PATH', __DIR__ . '/data/panneau.sqlite');
   define('DEBUG', false);                    // false en production
   define('RGPD_RESPONSABLE', 'Nom du responsable');
   define('RGPD_CONTACT',     'contact@example.fr');
   ```

3. **Permissions** :
   ```bash
   chmod 750 data/
   chmod 640 config.php
   ```

4. **Lancez l'installation** : accédez à `https://votre-site.fr/install.php`

5. **Supprimez `install.php`** immédiatement après.

6. **Invitez les membres** en leur communiquant l'URL et le code d'invitation.

> **Mise à jour** : remplacez simplement les fichiers. La base de données est automatiquement migrée au premier chargement — aucune perte de données.

---

## Fonctionnalités

### 🌿 Tableau d'activités
- Trois colonnes : Idées / À planifier / Vécu
- Tags, statuts, audience, sondage de dates, présences, commentaires
- Calendrier mensuel — clic sur un événement met la carte en évidence

### 📚 Prêt-o-thèque
- Catégories personnalisables depuis l'administration
- Fiches enrichies (URL, durée, tranche d'âge, joueurs, genre livre)
- Gestion de l'état : OK / Perdu / Cassé, journal des emprunts

### 🛒 Achats groupés
- Commandes collectives avec produits et quantités
- Suivi paiement / distribution par participant

### ⚙️ Administration
- Identité de l'application (nom, sous-titre) modifiable sans toucher aux fichiers
- Catégories prêt-o-thèque : ajout, renommage, suppression
- Gestion des membres : rôles, activation, réinitialisation mot de passe

---

## Sécurité

| Mesure | Détail |
|---|---|
| Mots de passe | bcrypt, coût 12 |
| Protection CSRF | Token par session, `hash_equals()` |
| Anti-brute-force | 5 tentatives / 15 min par identifiant |
| Requêtes SQL | PDO préparé uniquement |
| Cookies session | `httpOnly`, `SameSite=Lax`, `secure` si HTTPS |
| En-têtes HTTP | CSP, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` |
| Base de données | `data/` protégé par `.htaccess` |
| Erreurs | `display_errors = off` en production (`DEBUG = false`) |

---

## RGPD

- Données collectées : prénom/pseudo, foyer (optionnel), mot de passe haché, date de consentement.
- Consentement explicite à l'inscription.
- Droit de suppression dans *Mon compte* — contenu conservé anonymisé.
- Responsable de traitement configurable dans `config.php`.
- Registre de traitement visible dans l'Admin.

---

## Rôles

| Rôle | Droits |
|---|---|
| `admin` | Tout : gestion membres, suppression contenu, retour forcé d'emprunt |
| `member` | Création de cartes, emprunts, commentaires, sondages, présences |
| `external` | Accès lecture + participation limitée |

---

## Développement

### Architecture

Sharehood est organisé en **modules indépendants**. Chaque module est un dossier dans `modules/` qui déclare ses propres migrations de base de données, fonctions métier, vues et routes. `index.php` (76 lignes) charge tous les modules et dispatche les requêtes.

```
index.php               ← dispatcher : charge les modules, exécute les migrations,
                           dispatche POST → actions, GET → routes
core/
  db.php                ← getDB() + runMigrations()
  auth.php              ← session, CSRF, requireAuth(), requireAdmin()
  helpers.php           ← h(), flash(), redirect(), fmtDate(), layout HTML+CSS
  settings.php          ← getSetting(), setSetting()
modules/
  [feature]/
    migrations.php      ← schéma BDD versionné (appliqué une seule fois)
    functions.php       ← logique métier (fonctions PHP nommées)
    views.php           ← fonctions de rendu HTML
    module.php          ← point d'entrée : inclut les fichiers, déclare routes/actions
```

### Ajouter un module

Créez le dossier `modules/ma_fonctionnalite/` avec ces quatre fichiers :

**`migrations.php`** — schéma de base de données, appliqué une seule fois et jamais rejoué :

```php
<?php
return [
    '001_ma_fonctionnalite_table' => function(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS ma_table (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER NOT NULL,
            title      TEXT    NOT NULL,
            created_at TEXT    NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
    },
    // Ajouts futurs : une nouvelle clé = une nouvelle migration
    '002_ma_fonctionnalite_add_column' => function(PDO $db): void {
        try { $db->exec("ALTER TABLE ma_table ADD COLUMN description TEXT"); }
        catch (\PDOException) { /* colonne déjà présente */ }
    },
];
```

**`functions.php`** — logique métier pure, sans HTML :

```php
<?php
function getMesItems(int $userId): array
{
    $stmt = getDB()->prepare('SELECT * FROM ma_table WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function addMonItem(array $user, array $data): void
{
    getDB()->prepare('INSERT INTO ma_table (user_id, title) VALUES (?, ?)')
           ->execute([$user['id'], trim($data['title'])]);
}
```

**`views.php`** — rendu HTML, doit toujours recevoir un utilisateur authentifié :

```php
<?php
function viewMaFonctionnalite(array $user): void
{
    layoutOpen('Ma fonctionnalité', $user, 'ma_fonctionnalite');
    $items = getMesItems($user['id']);
    echo '<div class="page">';
    // ... HTML
    echo '</div>';
    layoutClose();
}
```

**`module.php`** — point d'entrée, déclare tout :

```php
<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/views.php';

return [
    'migrations'   => require __DIR__ . '/migrations.php',

    // Routes publiques (accessibles sans être connecté)
    // Omettre la clé si toutes vos routes sont protégées
    // 'public_routes' => ['ma_page_publique'],

    'routes' => [
        'ma_fonctionnalite' => fn(?array $u) => viewMaFonctionnalite($u),
    ],

    'actions' => [
        'mon_action_add' => function(?array $u): void {
            $user = requireAuth();
            if (empty(trim($_POST['title'] ?? ''))) {
                flash('error', 'Le titre est obligatoire.');
            } else {
                addMonItem($user, $_POST);
                flash('success', 'Ajouté !');
            }
            redirect('?action=ma_fonctionnalite');
        },
    ],
];
```

C'est tout. Le module est automatiquement découvert au prochain chargement de la page. Aucun autre fichier à modifier.

> **Ajouter un lien dans la navigation** : la nav est dans `core/helpers.php`, fonction `layoutOpen()`. Cherchez le bloc `<nav>` et ajoutez votre lien en suivant le même pattern que les existants.

### Modifier le schéma de base de données

**Ne jamais modifier une migration existante.** Ajoutez une nouvelle entrée dans `migrations.php` avec une clé unique :

```php
// Correct : nouvelle clé = nouveau numéro
'003_ma_fonctionnalite_add_status' => function(PDO $db): void {
    try { $db->exec("ALTER TABLE ma_table ADD COLUMN status TEXT NOT NULL DEFAULT 'active'"); }
    catch (\PDOException) {}
},

// Incorrect : modifier une migration déjà déployée
// '001_ma_fonctionnalite_table' => ... // ← NE PAS FAIRE
```

La table `schema_migrations` garde la liste de toutes les migrations appliquées. Une migration est exécutée **exactement une fois**, même sur une base de données en production existante.

### Mettre à jour l'application

1. Remplacez les fichiers sur le serveur (FTP, rsync, git pull…).
2. Accédez à n'importe quelle page de l'application.
3. Les nouvelles migrations s'appliquent automatiquement.

Aucune commande à exécuter, aucune interruption de service, aucune perte de données.

### Conventions

| Quoi | Convention |
|---|---|
| Noms de fonctions | `camelCase` — `getLibraryItems()`, `addCard()` |
| Routes GET | `snake_case` — `?action=group_orders` |
| Actions POST | `snake_case` — `?action=library_add` |
| Versions de migration | `NNN_module_description` — `001_board_cards` |
| Fonctions de vue | Préfixe `view` — `viewLibrary()`, `viewAdmin()` |
| Fonctions de rendu | Préfixe `render` — `renderCard()`, `renderGroupOrder()` |
| Échappement HTML | Toujours via `h()` — jamais `echo $variable` directement |
| Requêtes SQL | Toujours via PDO préparé — jamais d'interpolation de chaîne |

### Routes publiques

Par défaut, toutes les routes d'un module sont **protégées** (requièrent une session active). Pour rendre une route accessible sans connexion, déclarez-la dans `public_routes` :

```php
return [
    'public_routes' => ['ma_page_publique', 'autre_page_ouverte'],
    'routes' => [
        'ma_page_publique' => fn(?array $u) => viewMaPagePublique(),
        // ...
    ],
];
```

Seul le module `auth` a des routes publiques par défaut (`login`, `register`, `forgot_password`, `reset_password`, `privacy`).

---

## Licence

Usage libre pour habitat participatif, coopérative, association ou usage personnel.
