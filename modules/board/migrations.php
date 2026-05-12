<?php
return [
    '001_board_cards' => function(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS cards (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            column_id    INTEGER NOT NULL DEFAULT 0,
            tag          TEXT    NOT NULL DEFAULT 'autre',
            title        TEXT    NOT NULL,
            body         TEXT,
            author_id    INTEGER,
            audience     TEXT    DEFAULT 'adultes',
            event_date   TEXT,
            status       TEXT    NOT NULL DEFAULT 'idea',
            created_at   TEXT    NOT NULL DEFAULT (datetime('now')),
            updated_at   TEXT,
            FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
        )");
    },
    '002_board_cards_status' => function(PDO $db): void {
        try {
            $db->exec("ALTER TABLE cards ADD COLUMN status TEXT NOT NULL DEFAULT 'idea'");
            $db->exec("UPDATE cards SET status = 'planifiee'   WHERE column_id = 1 AND event_date IS NOT NULL AND status = 'idea'");
            $db->exec("UPDATE cards SET status = 'a_planifier' WHERE column_id = 1 AND event_date IS NULL     AND status = 'idea'");
        } catch (\PDOException) { /* colonne déjà présente */ }
    },
    '003_board_interests' => function(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS interests (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            card_id    INTEGER NOT NULL,
            user_id    INTEGER NOT NULL,
            created_at TEXT    NOT NULL DEFAULT (datetime('now')),
            UNIQUE(card_id, user_id),
            FOREIGN KEY (card_id)  REFERENCES cards(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE
        )");
    },
    '004_board_date_polls' => function(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS date_polls (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            card_id       INTEGER NOT NULL,
            proposed_date TEXT    NOT NULL,
            created_by    INTEGER,
            created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (card_id)    REFERENCES cards(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        )");
    },
    '005_board_date_poll_votes' => function(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS date_poll_votes (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            poll_id    INTEGER NOT NULL,
            user_id    INTEGER NOT NULL,
            created_at TEXT    NOT NULL DEFAULT (datetime('now')),
            UNIQUE(poll_id, user_id),
            FOREIGN KEY (poll_id) REFERENCES date_polls(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id)      ON DELETE CASCADE
        )");
    },
    '006_board_presences' => function(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS presences (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            card_id    INTEGER NOT NULL,
            user_id    INTEGER NOT NULL,
            attending  INTEGER NOT NULL DEFAULT 1,
            created_at TEXT    NOT NULL DEFAULT (datetime('now')),
            UNIQUE(card_id, user_id),
            FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
    },
    '007_board_comments' => function(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS comments (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            card_id    INTEGER NOT NULL,
            user_id    INTEGER NOT NULL,
            body       TEXT    NOT NULL,
            created_at TEXT    NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
    },
];
