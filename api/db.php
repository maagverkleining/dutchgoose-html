<?php
require_once __DIR__ . '/config.php';

function db_connect(): PDO {
    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ratings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            recipe_url TEXT NOT NULL,
            recipe_title TEXT NOT NULL,
            stars INTEGER NOT NULL CHECK(stars >= 1 AND stars <= 5),
            name TEXT NOT NULL DEFAULT 'Anoniem',
            comment TEXT,
            ip_hash TEXT NOT NULL,
            user_agent TEXT,
            status TEXT NOT NULL DEFAULT 'visible' CHECK(status IN ('visible','hidden','deleted')),
            created_at INTEGER NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_recipe_url ON ratings(recipe_url);
        CREATE INDEX IF NOT EXISTS idx_status ON ratings(status);
        CREATE INDEX IF NOT EXISTS idx_created_at ON ratings(created_at);
    ");

    return $pdo;
}

function human_date_nl(int $ts): string {
    $now  = time();
    $diff = $now - $ts;
    $days = (int) floor($diff / 86400);

    if ($days === 0)  return 'vandaag';
    if ($days === 1)  return 'gisteren';
    if ($days < 7)    return $days . ' dagen geleden';
    if ($days < 14)   return '1 week geleden';
    if ($days < 30)   return (int) floor($days / 7) . ' weken geleden';
    if ($days < 60)   return 'vorige maand';
    $months = (int) floor($days / 30);
    return $months . ' maanden geleden';
}
