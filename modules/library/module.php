<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/views.php';

return [
    'migrations' => require __DIR__ . '/migrations.php',
    'routes' => [
        'library' => fn(?array $u) => viewLibrary($u),
    ],
    'actions' => [
        'library_condition' => function(?array $u): void {
            $user = requireAuth();
            setItemCondition((int)($_POST['item_id'] ?? 0), trim($_POST['condition'] ?? ''), $user);
            redirect('?action=library');
        },
        'library_edit' => function(?array $u): void {
            $user = requireAuth();
            if (empty(trim($_POST['title'] ?? ''))) {
                flash('error', 'Le titre est obligatoire.');
            } elseif (!updateLibraryItem((int)($_POST['item_id'] ?? 0), $_POST, $user)) {
                flash('error', 'Modification non autorisée.');
            } else {
                flash('success', 'Fiche mise à jour.');
            }
            redirect('?action=library');
        },
        'library_add' => function(?array $u): void {
            $user = requireAuth();
            if (empty(trim($_POST['title'] ?? ''))) {
                flash('error', 'Le titre est obligatoire.');
            } else {
                addLibraryItem($user, $_POST);
                flash('success', 'Objet ajouté à la prêt-o-thèque !');
            }
            redirect('?action=library');
        },
        'library_borrow' => function(?array $u): void {
            $user = requireAuth();
            if (!borrowItem((int)($_POST['item_id'] ?? 0), $user['id'], $_POST['due_date'] ?? null)) {
                flash('error', 'Emprunt impossible (objet indisponible ?).');
            }
            redirect('?action=library');
        },
        'library_return' => function(?array $u): void {
            $user = requireAuth();
            returnItem((int)($_POST['loan_id'] ?? 0), $user);
            redirect('?action=library');
        },
        'library_delete' => function(?array $u): void {
            $user = requireAuth();
            deleteLibraryItem((int)($_POST['item_id'] ?? 0), $user);
            redirect('?action=library');
        },
    ],
];
