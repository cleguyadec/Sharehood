<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/views.php';

return [
    'migrations' => require __DIR__ . '/migrations.php',
    'routes' => [
        'login'          => fn(?array $u) => viewLogin(),
        'register'       => fn(?array $u) => viewRegister(),
        'forgot_password' => fn(?array $u) => viewForgotPassword(),
        'reset_password'  => fn(?array $u) => viewResetPassword($_GET['token'] ?? ''),
        'privacy'        => fn(?array $u) => viewPrivacy(),
    ],
    'actions' => [
        'login' => function(?array $u): void {
            $err = doLogin(
                trim($_POST['display_name'] ?? ''),
                $_POST['password'] ?? ''
            );
            if ($err) {
                flash('error', $err);
                redirect('?action=login');
            }
            redirect('?action=board');
        },
        'register' => function(?array $u): void {
            $err = doRegister(
                trim($_POST['display_name'] ?? ''),
                $_POST['password'] ?? '',
                trim($_POST['invite_code'] ?? ''),
                trim($_POST['household']   ?? ''),
                !empty($_POST['gdpr_consent'])
            );
            if ($err) {
                flash('error', $err);
                redirect('?action=register');
            }
            redirect('?action=board');
        },
        'logout' => function(?array $u): void {
            doLogout();
        },
        'forgot_password' => function(?array $u): void {
            createPasswordResetToken(trim($_POST['display_name'] ?? ''));
            flash('success', 'Demande enregistrée. Contactez un administrateur pour recevoir votre lien de réinitialisation.');
            redirect('?action=forgot_password');
        },
        'reset_password' => function(?array $u): void {
            $err = consumePasswordResetToken($_POST['token'] ?? '', $_POST['new_password'] ?? '');
            if ($err) {
                flash('error', $err);
                redirect('?action=reset_password&token=' . urlencode($_POST['token'] ?? ''));
            }
            flash('success', 'Mot de passe modifié. Vous pouvez maintenant vous connecter.');
            redirect('?action=login');
        },
    ],
];
