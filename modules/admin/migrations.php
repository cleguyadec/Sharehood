<?php
return [
    '001_admin_settings' => function(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS settings (
            key   TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT ''
        )");
        $db->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('app_name', '')");
        $db->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('app_subtitle', '')");
    },
];
