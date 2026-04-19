<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (($_GET['token'] ?? '') !== SEED_TOKEN) {
    http_response_code(403);
    exit('Invalid token');
}

$data  = require __DIR__ . '/seed-data.php';
$david = $data['david_template'];
$pools = $david['comment_pools'];

$pdo = db_connect();

// 1. Delete all David ratings
$del = $pdo->prepare("DELETE FROM ratings WHERE name = ?");
$del->execute([$david['name']]);
$deleted = $del->rowCount();

// 2. Get all unique recipe_urls from remaining ratings
$stmt = $pdo->prepare("SELECT DISTINCT recipe_url, recipe_title FROM ratings WHERE name != ?");
$stmt->execute([$david['name']]);
$recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$added  = 0;
$insert = $pdo->prepare(
    "INSERT INTO ratings (recipe_url, recipe_title, stars, name, comment, ip_hash, user_agent, status, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, 'visible', ?)"
);

foreach ($recipes as $r) {
    $url   = $r['recipe_url'];
    $title = $r['recipe_title'];

    if (strpos($url, '/vloeibaar/') !== false)     $phase = 'vloeibaar';
    elseif (strpos($url, '/gepureerd/') !== false) $phase = 'gepureerd';
    else                                            $phase = 'vaste-voeding';

    $pool    = $pools[$phase];
    $comment = $pool[array_rand($pool)];

    $days_ago = rand(60, 120);
    $created  = time() - ($days_ago * 86400);

    $ip_hash = hash('sha256', 'seed-david-' . $url . '-' . IP_SALT);

    $insert->execute([$url, $title, $david['stars'], $david['name'], $comment, $ip_hash, 'seed', $created]);
    $added++;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<title>Reseed David - Dutch Goose</title>
<style>
body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; padding: 40px; max-width: 600px; margin: auto; background: #f7faf8; color: #1a3a30; }
h1 { color: #1a3a30; }
p { margin: 10px 0; }
a { color: #1a7c6b; }
</style>
</head>
<body>
<h1>Reseed David voltooid</h1>
<p>Verwijderd: <strong><?= $deleted ?></strong> David-ratings</p>
<p>Nieuw toegevoegd: <strong><?= $added ?></strong> David-ratings met variatie uit pool van <?= count($pools['vaste-voeding']) ?> comments per fase</p>
<p><a href="moderatie.php">Naar moderatie &rarr;</a></p>
</body>
</html>
