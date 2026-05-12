<?php
// ═══════════════════════════════════════════════════════════
//  Panneau Vivant — Configuration
//  Modifier ce fichier selon votre installation
// ═══════════════════════════════════════════════════════════

// Nom et identité de l'espace
define('APP_NAME',     'Sharehood');
define('APP_SUBTITLE', "L'Étoile de Terre");

// Chemin vers la base de données SQLite
// Idéalement hors du dossier public web
define('DB_PATH', __DIR__ . '/data/panneau.sqlite');

// Code d'invitation pour créer un compte (à changer après installation)
define('INVITE_CODE', 'etoile');

// Durée de session en secondes (604800 = 7 jours)
define('SESSION_LIFETIME', 604800);

// Sécurité : tentatives max avant blocage temporaire du login
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

// Mode debug (mettre à false en production)
define('DEBUG', false);

// Nom du responsable de traitement (RGPD)
define('RGPD_RESPONSABLE', 'L\'Étoile de Terre');
define('RGPD_CONTACT',     'contact@etoiledterre.fr');
