<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/notify.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: https://dutchgoose.nl');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$pdo = db_connect();

// GET: fetch ratings for a recipe
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $recipe = $_GET['recipe'] ?? '';
    if (!$recipe || !preg_match('#^/recepten/.+\.html$#', $recipe)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ongeldige recipe_url']);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT id, stars, name, comment, created_at
         FROM ratings
         WHERE recipe_url = ? AND status = 'visible'
         ORDER BY created_at DESC"
    );
    $stmt->execute([$recipe]);
    $rows = $stmt->fetchAll();

    $count   = count($rows);
    $average = $count > 0 ? round(array_sum(array_column($rows, 'stars')) / $count, 1) : 0;

    $ratings = array_map(function ($r) {
        return [
            'id'              => (int) $r['id'],
            'stars'           => (int) $r['stars'],
            'name'            => $r['name'],
            'comment'         => $r['comment'] ?? '',
            'created_at_human'=> human_date_nl((int) $r['created_at']),
        ];
    }, $rows);

    echo json_encode(['average' => $average, 'count' => $count, 'ratings' => $ratings]);
    exit;
}

// POST: submit a rating
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ongeldige JSON']);
        exit;
    }

    // Honeypot
    $website = trim($data['website'] ?? '');
    if ($website !== '') {
        // Mislead bots: pretend success
        echo json_encode(['success' => true, 'message' => 'Bedankt voor je beoordeling!', 'rating' => null]);
        exit;
    }

    $recipe_url   = trim($data['recipe_url'] ?? '');
    $recipe_title = trim($data['recipe_title'] ?? '');
    $stars        = isset($data['stars']) ? (int) $data['stars'] : 0;
    $name         = trim($data['name'] ?? '') ?: 'Anoniem';
    $name         = mb_substr($name, 0, 50);
    $comment      = mb_substr(trim($data['comment'] ?? ''), 0, 1000);

    // Validate recipe_url
    if (!preg_match('#^/recepten/.+\.html$#', $recipe_url)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ongeldige recipe_url']);
        exit;
    }

    if ($stars < 1 || $stars > 5) {
        http_response_code(400);
        echo json_encode(['error' => 'Sterren moeten 1 tot 5 zijn']);
        exit;
    }

    if ($recipe_title === '') {
        http_response_code(400);
        echo json_encode(['error' => 'recipe_title ontbreekt']);
        exit;
    }

    $ip_hash   = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . IP_SALT);
    $ua        = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300);
    $now       = time();
    $hour_ago  = $now - 3600;

    // Rate limit
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM ratings
         WHERE ip_hash = ? AND recipe_url = ? AND created_at >= ?"
    );
    $stmt->execute([$ip_hash, $recipe_url, $hour_ago]);
    $recent = (int) $stmt->fetchColumn();

    if ($recent >= MAX_RATINGS_PER_IP_PER_HOUR_PER_RECIPE) {
        http_response_code(429);
        echo json_encode(['error' => 'Te veel beoordelingen. Probeer het later opnieuw.']);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO ratings (recipe_url, recipe_title, stars, name, comment, ip_hash, user_agent, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'visible', ?)"
    );
    $stmt->execute([$recipe_url, $recipe_title, $stars, $name, $comment ?: null, $ip_hash, $ua, $now]);
    $id = (int) $pdo->lastInsertId();

    $rating = [
        'id'               => $id,
        'stars'            => $stars,
        'name'             => $name,
        'comment'          => $comment,
        'created_at_human' => human_date_nl($now),
    ];

    send_rating_notify([
        'recipe_url'   => $recipe_url,
        'recipe_title' => $recipe_title,
        'stars'        => $stars,
        'name'         => $name,
        'comment'      => $comment,
        'ip_hash'      => $ip_hash,
        'created_at'   => $now,
    ]);

    echo json_encode(['success' => true, 'message' => 'Bedankt voor je beoordeling!', 'rating' => $rating]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Methode niet toegestaan']);
