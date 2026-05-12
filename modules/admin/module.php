<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/views.php';

return [
    'migrations' => require __DIR__ . '/migrations.php',
    'routes' => [
        'admin' => fn(?array $u) => viewAdmin(requireAdmin()),
    ],
    'actions' => [
        'admin_settings' => function(?array $u): void {
            $user = requireAdmin();
            $name = trim($_POST['app_name'] ?? '');
            if ($name === '') {
                flash('error', 'Le nom de l\'application est obligatoire.');
            } else {
                setSetting('app_name', $name);
                setSetting('app_subtitle', trim($_POST['app_subtitle'] ?? ''));
                flash('success', 'Paramètres enregistrés.');
            }
            redirect('?action=admin#identity');
        },
        'admin_lib_cat_add' => function(?array $u): void {
            $user = requireAdmin();
            $err = addLibCategory(
                trim($_POST['slug'] ?? ''),
                trim($_POST['emoji'] ?? '📦'),
                trim($_POST['label'] ?? '')
            );
            if ($err) flash('error', $err);
            else      flash('success', 'Catégorie ajoutée.');
            redirect('?action=admin#lib-cats');
        },
        'admin_lib_cat_edit' => function(?array $u): void {
            $user = requireAdmin();
            $err = editLibCategory(
                (int)($_POST['cat_id'] ?? 0),
                trim($_POST['emoji'] ?? '📦'),
                trim($_POST['label'] ?? '')
            );
            if ($err) flash('error', $err);
            else      flash('success', 'Catégorie mise à jour.');
            redirect('?action=admin#lib-cats');
        },
        'admin_lib_cat_delete' => function(?array $u): void {
            $user = requireAdmin();
            $err = deleteLibCategory((int)($_POST['cat_id'] ?? 0));
            if ($err) flash('error', $err);
            else      flash('success', 'Catégorie supprimée.');
            redirect('?action=admin#lib-cats');
        },
        'admin_toggle_user' => function(?array $u): void {
            $user = requireAdmin();
            toggleUserActive((int)($_POST['user_id'] ?? 0));
            redirect('?action=admin');
        },
        'admin_set_role' => function(?array $u): void {
            $user = requireAdmin();
            setUserRole((int)($_POST['user_id'] ?? 0), $_POST['role'] ?? '');
            redirect('?action=admin');
        },
        'admin_delete_user' => function(?array $u): void {
            $user = requireAdmin();
            $targetId = (int)($_POST['user_id'] ?? 0);
            if ($targetId === $user['id']) {
                flash('error', 'Vous ne pouvez pas supprimer votre propre compte ici.');
            } else {
                deleteAccount($targetId);
                flash('success', 'Compte supprimé.');
            }
            redirect('?action=admin');
        },
        'admin_reset_password' => function(?array $u): void {
            $user = requireAdmin();
            $pw = $_POST['new_password'] ?? '';
            $err = adminResetPassword((int)($_POST['user_id'] ?? 0), $pw);
            if ($err) flash('error', $err);
            else      flash('success', 'Mot de passe réinitialisé.');
            redirect('?action=admin');
        },
        'admin_generate_reset' => function(?array $u): void {
            $user = requireAdmin();
            $token = adminGenerateResetToken((int)($_POST['user_id'] ?? 0));
            if (!$token) {
                flash('error', 'Utilisateur introuvable.');
                redirect('?action=admin');
                return;
            }
            $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
                     . strtok($_SERVER['REQUEST_URI'], '?');
            $url = $baseUrl . '?action=reset_password&token=' . rawurlencode($token);
            flash('success', 'reset_url:' . $url);
            redirect('?action=admin');
        },
    ],
];
