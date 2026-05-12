<?php
// ═══════════════════════════════════════════════════════════
//  Panneau Vivant — Dispatcher
// ═══════════════════════════════════════════════════════════

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/settings.php';

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

// ── Chargement des modules
$modules = [];
foreach (glob(__DIR__ . '/modules/*/module.php') as $moduleFile) {
    $modules[] = require $moduleFile;
}

// ── Migrations versionnées
runMigrations($modules);

$action = preg_replace('/[^a-z_]/', '', $_GET['action'] ?? 'board');
$user   = currentUser();

// ── Construire les tables de dispatch
$routes       = [];
$actions      = [];
$publicRoutes = [];
foreach ($modules as $mod) {
    $routes       += $mod['routes']       ?? [];
    $actions      += $mod['actions']      ?? [];
    $publicRoutes  = array_merge($publicRoutes, $mod['public_routes'] ?? []);
}

// ── Guard d'authentification
if (in_array($action, $publicRoutes, true)) {
    // Pages publiques : rediriger l'utilisateur connecté hors de la page login
    if ($user && $action === 'login') {
        redirect('?action=board');
    }
} else {
    // Pages protégées : exiger l'authentification
    $user = requireAuth();
}

// ───────────────────────────────────────────────────────────
//  POST — traitement des formulaires
// ───────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify()) {
        flash('error', 'Requête invalide. Rechargez la page et réessayez.');
        redirect('?action=' . $action);
    }

    if (isset($actions[$action])) {
        ($actions[$action])($user);
    }

    redirect('?action=' . $action);
}

// ───────────────────────────────────────────────────────────
//  GET — dispatch vers les vues
// ───────────────────────────────────────────────────────────

if (isset($routes[$action])) {
    ($routes[$action])($user);
} else {
    redirect($user ? '?action=board' : '?action=login');
}
