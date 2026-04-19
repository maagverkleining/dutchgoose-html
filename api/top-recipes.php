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

define('SITE_ROOT', dirname(__DIR__));

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

    $base = [
        'title' => $row['recipe_title'],
        'url'   => $row['recipe_url'],
        'avg'   => (float) $row['avg'],
        'count' => (int)   $row['count'],
    ];

    $hub_data = enrich_from_hub($row['recipe_url'], $phase);
    return array_merge($base, $hub_data);
}

function enrich_from_hub(string $recipe_url, string $phase): array {
    $hub_files = [
        'vloeibaar'     => 'recepten/vloeibaar/index.html',
        'gepureerd'     => 'recepten/gepureerd/index.html',
        'vaste-voeding' => 'recepten/vaste-voeding/index.html',
    ];

    $hub_path = SITE_ROOT . '/' . ($hub_files[$phase] ?? '');
    if (!file_exists($hub_path)) return [];

    $filename = basename($recipe_url);

    $html = file_get_contents($hub_path);
    if ($html === false) return [];

    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);
    $cards = $xpath->query('//a[contains(@class,"recipe-card")]');

    foreach ($cards as $card) {
        $href = $card->getAttribute('href');
        if (basename($href) !== $filename) continue;

        $img_node  = $xpath->query('.//img[contains(@class,"recipe-card-img")]', $card)->item(0);
        $cat_node  = $xpath->query('.//*[contains(@class,"recipe-card-cat")]', $card)->item(0);
        $h3_node   = $xpath->query('.//h3', $card)->item(0);
        $p_node    = $xpath->query('.//p', $card)->item(0);
        $pill_nodes = $xpath->query('.//*[contains(@class,"macro-pill")]', $card);

        $image = '';
        if ($img_node) {
            $src = $img_node->getAttribute('src');
            if (preg_match('#assets/recepten/(.+)$#', $src, $m)) {
                $image = '/assets/recepten/' . $m[1];
            }
        }

        $macros = [];
        foreach ($pill_nodes as $pill) {
            $macros[] = trim($pill->textContent);
        }

        return [
            'image'       => $image,
            'cat'         => $cat_node  ? trim($cat_node->textContent)  : '',
            'short_title' => $h3_node   ? trim($h3_node->textContent)   : '',
            'description' => $p_node    ? trim($p_node->textContent)    : '',
            'macros'      => $macros,
        ];
    }

    return [];
}

$result = [
    'vloeibaar'     => top_for_phase($pdo, 'vloeibaar'),
    'gepureerd'     => top_for_phase($pdo, 'gepureerd'),
    'vaste-voeding' => top_for_phase($pdo, 'vaste-voeding'),
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
