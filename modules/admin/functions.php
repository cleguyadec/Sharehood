<?php
// ═══════════════════════════════════════════════════════════
//  Module Admin — Fonctions métier
// ═══════════════════════════════════════════════════════════

function getAllUsers(): array
{
    return getDB()->query('
        SELECT id, display_name, role, household, created_at, last_login, is_active, gdpr_consent_at
        FROM   users
        ORDER  BY created_at ASC
    ')->fetchAll();
}

function toggleUserActive(int $id): void
{
    getDB()->prepare('UPDATE users SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id = ?')
           ->execute([$id]);
}

function setUserRole(int $id, string $role): void
{
    if (!in_array($role, ['admin', 'member', 'external'], true)) {
        return;
    }
    getDB()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $id]);
}
