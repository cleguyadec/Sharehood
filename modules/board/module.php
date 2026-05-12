<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/views.php';

return [
    'migrations' => require __DIR__ . '/migrations.php',
    'routes' => [
        'board' => fn(?array $u) => viewBoard($u),
    ],
    'actions' => [
        'card_add' => function(?array $u): void {
            $user = requireAuth();
            if (empty(trim($_POST['title'] ?? ''))) {
                flash('error', 'Le titre est obligatoire.');
            } else {
                addCard($user, $_POST);
                flash('success', 'Carte ajoutée !');
            }
            redirect('?action=board');
        },
        'card_move' => function(?array $u): void {
            $user = requireAuth();
            moveCard((int)($_POST['card_id'] ?? 0), (int)($_POST['to_col'] ?? 0), $user);
            redirect('?action=board');
        },
        'card_delete' => function(?array $u): void {
            $user = requireAuth();
            deleteCard((int)($_POST['card_id'] ?? 0), $user);
            redirect('?action=board');
        },
        'interest_toggle' => function(?array $u): void {
            $user = requireAuth();
            toggleInterest((int)($_POST['card_id'] ?? 0), $user['id']);
            redirect('?action=board');
        },
        'date_poll_add' => function(?array $u): void {
            $user = requireAuth();
            if (!addDatePoll((int)($_POST['card_id'] ?? 0), trim($_POST['proposed_date'] ?? ''), $user['id'])) {
                flash('error', 'Date invalide ou déjà proposée.');
            }
            redirect('?action=board');
        },
        'date_poll_delete' => function(?array $u): void {
            $user = requireAuth();
            deleteDatePoll((int)($_POST['poll_id'] ?? 0), $user);
            redirect('?action=board');
        },
        'date_poll_vote' => function(?array $u): void {
            $user = requireAuth();
            toggleDatePollVote((int)($_POST['poll_id'] ?? 0), $user['id']);
            redirect('?action=board');
        },
        'card_confirm_date' => function(?array $u): void {
            $user = requireAuth();
            if (!confirmCardDate((int)($_POST['card_id'] ?? 0), trim($_POST['event_date'] ?? ''), $user)) {
                flash('error', 'Impossible de confirmer la date.');
            } else {
                flash('success', 'Date confirmée ! Les membres peuvent confirmer leur présence.');
            }
            redirect('?action=board');
        },
        'card_status_update' => function(?array $u): void {
            $user = requireAuth();
            updateCardStatus((int)($_POST['card_id'] ?? 0), trim($_POST['status'] ?? ''), $user);
            redirect('?action=board');
        },
        'presence_toggle' => function(?array $u): void {
            $user = requireAuth();
            togglePresence((int)($_POST['card_id'] ?? 0), $user['id'], (int)($_POST['attending'] ?? 1));
            redirect('?action=board');
        },
        'comment_add' => function(?array $u): void {
            $user = requireAuth();
            if (!addComment((int)($_POST['card_id'] ?? 0), $user['id'], trim($_POST['body'] ?? ''))) {
                flash('error', 'Commentaire vide.');
            }
            redirect('?action=board');
        },
        'comment_delete' => function(?array $u): void {
            $user = requireAuth();
            deleteComment((int)($_POST['comment_id'] ?? 0), $user);
            redirect('?action=board');
        },
    ],
];
