<?php
return [
    '001_auth_users' => function(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            display_name     TEXT    NOT NULL,
            password_hash    TEXT    NOT NULL,
            role             TEXT    NOT NULL DEFAULT 'member',
            household        TEXT,
            created_at       TEXT    NOT NULL DEFAULT (datetime('now')),
            last_login       TEXT,
            is_active        INTEGER NOT NULL DEFAULT 1,
            gdpr_consent_at  TEXT
        )");
    },
    '002_auth_login_attempts' => function(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            identifier   TEXT NOT NULL,
            attempted_at TEXT NOT NULL DEFAULT (datetime('now'))
        )");
    },
    '003_auth_password_reset' => function(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            token_hash TEXT    NOT NULL,
            created_at TEXT    NOT NULL DEFAULT (datetime('now')),
            expires_at TEXT    NOT NULL,
            used_at    TEXT
        )");
    },
];
