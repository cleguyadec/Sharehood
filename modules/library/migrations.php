<?php
return [
    '001_library_items' => function(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS library_items (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            category      TEXT    NOT NULL DEFAULT 'autre',
            title         TEXT    NOT NULL,
            subtitle      TEXT,
            description   TEXT,
            owner_id      INTEGER,
            available     INTEGER NOT NULL DEFAULT 1,
            condition     TEXT    NOT NULL DEFAULT 'ok',
            url           TEXT,
            game_duration TEXT,
            age_min       INTEGER,
            player_min    INTEGER,
            player_max    INTEGER,
            book_genre    TEXT,
            created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
        )");
    },
    '002_library_items_columns' => function(PDO $db): void {
        foreach ([
            "ALTER TABLE library_items ADD COLUMN condition     TEXT    NOT NULL DEFAULT 'ok'",
            "ALTER TABLE library_items ADD COLUMN url           TEXT",
            "ALTER TABLE library_items ADD COLUMN game_duration TEXT",
            "ALTER TABLE library_items ADD COLUMN age_min       INTEGER",
            "ALTER TABLE library_items ADD COLUMN player_min    INTEGER",
            "ALTER TABLE library_items ADD COLUMN player_max    INTEGER",
            "ALTER TABLE library_items ADD COLUMN book_genre    TEXT",
        ] as $sql) {
            try { $db->exec($sql); } catch (\PDOException) { /* colonne déjà présente */ }
        }
    },
    '003_library_loans' => function(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS loans (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            item_id      INTEGER NOT NULL,
            borrower_id  INTEGER NOT NULL,
            loaned_at    TEXT    NOT NULL DEFAULT (datetime('now')),
            due_date     TEXT,
            returned_at  TEXT,
            notes        TEXT,
            FOREIGN KEY (item_id)     REFERENCES library_items(id) ON DELETE CASCADE,
            FOREIGN KEY (borrower_id) REFERENCES users(id)
        )");
    },
    '004_library_categories' => function(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS lib_categories (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            slug       TEXT    NOT NULL UNIQUE,
            emoji      TEXT    NOT NULL DEFAULT '📦',
            label      TEXT    NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0
        )");
        $count = (int) $db->query('SELECT COUNT(*) FROM lib_categories')->fetchColumn();
        if ($count === 0) {
            $stmt = $db->prepare('INSERT OR IGNORE INTO lib_categories (slug, emoji, label, sort_order) VALUES (?, ?, ?, ?)');
            foreach ([
                ['livre', '📚', 'Livres', 0],
                ['outil', '🔧', 'Outils', 1],
                ['jeu',   '🎲', 'Jeux',   2],
                ['autre', '📦', 'Autre',  99],
            ] as [$s, $e, $l, $o]) {
                $stmt->execute([$s, $e, $l, $o]);
            }
        }
    },
];
