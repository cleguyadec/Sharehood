<?php
return [
    '001_group_orders' => function(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS group_orders (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            title       TEXT    NOT NULL,
            description TEXT,
            deadline    TEXT,
            status      TEXT    NOT NULL DEFAULT 'open',
            creator_id  INTEGER,
            created_at  TEXT    NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE SET NULL
        )");
    },
    '002_group_order_products' => function(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS group_order_products (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id     INTEGER NOT NULL,
            name         TEXT    NOT NULL,
            unit         TEXT    NOT NULL DEFAULT 'unité',
            unit_price   REAL    NOT NULL DEFAULT 0,
            created_at   TEXT    NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (order_id) REFERENCES group_orders(id) ON DELETE CASCADE
        )");
    },
    '003_group_order_products_conditioning' => function(PDO $db): void {
        try {
            $db->exec("ALTER TABLE group_order_products ADD COLUMN conditioning REAL");
        } catch (\PDOException) { /* colonne déjà présente */ }
    },
    '004_group_order_requests' => function(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS group_order_requests (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id  INTEGER NOT NULL,
            user_id     INTEGER NOT NULL,
            quantity    REAL    NOT NULL DEFAULT 0,
            paid        INTEGER NOT NULL DEFAULT 0,
            dispatched  INTEGER NOT NULL DEFAULT 0,
            created_at  TEXT    NOT NULL DEFAULT (datetime('now')),
            UNIQUE(product_id, user_id),
            FOREIGN KEY (product_id) REFERENCES group_order_products(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE CASCADE
        )");
    },
];
