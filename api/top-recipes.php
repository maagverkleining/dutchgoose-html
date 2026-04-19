<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: https://dutchgoose.nl');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Cache-Control: public, max-age=3600');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'GET vereist']);
    exit;
}

$pdo = db_connect();

function top_for_phase(PDO $pdo, string $phase): ?array {
    if ($phase === 'vaste-voeding') {
        $stmt = $pdo->prepare(
            "SELECT recipe_url, recipe_title,
                    ROUND(AVG(stars), 1) AS avg,
                    COUNT(*) AS count
             FROM ratings
             WHERE status = 'visible'
               AND (
                   recipe_url LIKE '/recepten/vaste-voeding/%'
                   OR (
                       recipe_url LIKE '/recepten/%.html'
                       AND recipe_url NOT LIKE '/recepten/vloeibaar/%'
                       AND recipe_url NOT LIKE '/recepten/gepureerd/%'
                       AND recipe_url NOT LIKE '/recepten/vaste-voeding/%'
                   )
               )
             GROUP BY recipe_url
             HAVING count >= 3
             ORDER BY avg DESC, count DESC, MAX(created_at) DESC
             LIMIT 1"
        );
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare(
            "SELECT recipe_url, recipe_title,
                    ROUND(AVG(stars), 1) AS avg,
                    COUNT(*) AS count
             FROM ratings
             WHERE status = 'visible'
               AND recipe_url LIKE :pat
             GROUP BY recipe_url
             HAVING count >= 3
             ORDER BY avg DESC, count DESC, MAX(created_at) DESC
             LIMIT 1"
        );
        $stmt->execute([':pat' => '/recepten/' . $phase . '/%']);
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    return [
        'title' => $row['recipe_title'],
        'url'   => $row['recipe_url'],
        'avg'   => (float) $row['avg'],
        'count' => (int)   $row['count'],
    ];
}

$result = [
    'vloeibaar'     => top_for_phase($pdo, 'vloeibaar'),
    'gepureerd'     => top_for_phase($pdo, 'gepureerd'),
    'vaste-voeding' => top_for_phase($pdo, 'vaste-voeding'),
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
