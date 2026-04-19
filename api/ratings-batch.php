<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: https://dutchgoose.nl');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST vereist']);
    exit;
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body) || empty($body['urls']) || !is_array($body['urls'])) {
    http_response_code(400);
    echo json_encode(['error' => 'urls array vereist']);
    exit;
}

$urls = array_slice($body['urls'], 0, 60);
$urls = array_filter($urls, function ($u) {
    return is_string($u) && preg_match('#^/recepten/.+\.html$#', $u);
});
$urls = array_values($urls);

if (empty($urls)) {
    echo json_encode(['results' => (object)[]]);
    exit;
}

$pdo         = db_connect();
$placeholders = implode(',', array_fill(0, count($urls), '?'));

$stmt = $pdo->prepare(
    "SELECT recipe_url,
            ROUND(AVG(stars), 1) AS average,
            COUNT(*) AS count
     FROM ratings
     WHERE status = 'visible'
       AND recipe_url IN ($placeholders)
     GROUP BY recipe_url"
);
$stmt->execute($urls);
$rows = $stmt->fetchAll();

$results = new stdClass();
foreach ($rows as $row) {
    $results->{$row['recipe_url']} = [
        'average' => (float) $row['average'],
        'count'   => (int)   $row['count'],
    ];
}

echo json_encode(['results' => $results]);
