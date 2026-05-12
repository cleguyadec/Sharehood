<?php
// ═══════════════════════════════════════════════════════════
//  Panneau Vivant — Paramètres de l'application
// ═══════════════════════════════════════════════════════════

function getSetting(string $key, string $default = ''): string
{
    static $cache = [];
    if (!isset($cache[$key])) {
        $stmt = getDB()->prepare('SELECT value FROM settings WHERE key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $cache[$key] = $row !== false ? $row['value'] : $default;
    }
    return $cache[$key] ?: $default;
}

function setSetting(string $key, string $value): void
{
    getDB()->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)')->execute([$key, $value]);
}
