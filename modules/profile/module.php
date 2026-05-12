<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/views.php';

return [
    'migrations' => require __DIR__ . '/migrations.php',
    'routes' => [
        'dashboard' => fn(?array $u) => viewDashboard(requireAuth()),
        'my_data'   => fn(?array $u) => viewMyData(requireAuth()),
    ],
    'actions' => [
        'change_password' => function(?array $u): void {
            $user = requireAuth();
            $err = changePassword($user, $_POST['old_password'] ?? '', $_POST['new_password'] ?? '');
            if ($err) flash('error', $err);
            else      flash('success', 'Mot de passe modifié.');
            redirect('?action=my_data');
        },
        'delete_account' => function(?array $u): void {
            $user = requireAuth();
            if (($_POST['confirm'] ?? '') !== $user['display_name']) {
                flash('error', 'Confirmation incorrecte.');
                redirect('?action=my_data');
                return;
            }
            deleteAccount($user['id']);
            doLogout();
            redirect('?action=login');
        },
    ],
];
