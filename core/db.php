<?php
// ═══════════════════════════════════════════════════════════
//  Panneau Vivant — Base de données & migrations
// ═══════════════════════════════════════════════════════════

function getDB(): PDO
{
    static $db = null;
    if ($db === null) {
        $dir = dirname(DB_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('PRAGMA journal_mode = WAL');
        $db->exec('PRAGMA foreign_keys = ON');
    }
    return $db;
}

function runMigrations(array $modules): void
{
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        version    TEXT PRIMARY KEY,
        applied_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");
    $applied = array_flip($db->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN));

    foreach ($modules as $module) {
        foreach ($module['migrations'] ?? [] as $version => $fn) {
            if (!isset($applied[$version])) {
                $fn($db);
                $db->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
            }
        }
    }
}
