<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Token check
$token = $_GET['token'] ?? '';
if (!hash_equals(SEED_TOKEN, $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

$pdo  = db_connect();
$data = require __DIR__ . '/seed-data.php';

// Parse sitemap
$sitemap_path = dirname(__DIR__) . '/sitemap.xml';
$sitemap_xml  = file_get_contents($sitemap_path);
if (!$sitemap_xml) {
    http_response_code(500);
    echo 'Kan sitemap.xml niet lezen';
    exit;
}

preg_match_all('#https?://[^/]+(/recepten/[^<]+\.html)#', $sitemap_xml, $m);
$recipe_urls = array_unique($m[1]);

if (empty($recipe_urls)) {
    http_response_code(500);
    echo 'Geen recepten gevonden in sitemap.xml';
    exit;
}

function detect_phase(string $url): string {
    if (strpos($url, '/vloeibaar/')     !== false) return 'vloeibaar';
    if (strpos($url, '/gepureerd/')     !== false) return 'gepureerd';
    if (strpos($url, '/vaste-voeding/') !== false) return 'vaste-voeding';
    return 'vaste-voeding';
}

function get_recipe_title(string $url): string {
    $path     = dirname(__DIR__) . $url;
    $html     = @file_get_contents($path);
    if (!$html) return basename($url, '.html');
    if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
        $title = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Strip " | Dutch Goose" suffix
        $title = preg_replace('/\s*\|.*$/', '', $title);
        return trim($title);
    }
    return basename($url, '.html');
}

function pick_community_ratings(array $pool, int $count, string $recipe_url): array {
    $comments = $pool['comments'];
    $names    = $pool['names'];

    // Separate by star level
    $by_stars = [5 => [], 4 => [], 3 => []];
    foreach ($comments as $c) {
        $s = $c['stars'];
        if (isset($by_stars[$s])) $by_stars[$s][] = $c;
    }

    // Target distribution: 60% five, 25% four, 15% three
    $dist = [];
    if ($count === 2) {
        $dist = [5 => 1, 4 => 1, 3 => 0];
        // Occasionally give a 3-star instead of a 4
        if (mt_rand(1, 100) <= 15) $dist = [5 => 1, 4 => 0, 3 => 1];
    } else {
        // count === 3
        $dist = [5 => 2, 4 => 1, 3 => 0];
        if (mt_rand(1, 100) <= 15) $dist = [5 => 2, 4 => 0, 3 => 1];
    }

    $selected_comments = [];
    $selected_names    = [];
    $url_seed          = crc32($recipe_url);

    foreach ($dist as $star => $n) {
        $pool_s  = $by_stars[$star];
        $indices = array_keys($pool_s);
        shuffle($indices);
        $picked  = 0;
        foreach ($indices as $idx) {
            if ($picked >= $n) break;
            $c = $pool_s[$idx];
            // Avoid duplicate text within this recipe
            if (in_array($c['text'], array_column($selected_comments, 'text'), true)) continue;
            $selected_comments[] = $c;
            $picked++;
        }
    }

    // Pick unique names
    $available_names = $names;
    shuffle($available_names);
    $result = [];
    foreach ($selected_comments as $i => $c) {
        $name = $available_names[$i % count($available_names)];
        $result[] = ['stars' => $c['stars'], 'text' => $c['text'], 'name' => $name];
    }

    return $result;
}

$added   = 0;
$skipped = 0;
$by_phase   = ['vloeibaar' => 0, 'gepureerd' => 0, 'vaste-voeding' => 0];
$by_stars   = [5 => 0, 4 => 0, 3 => 0];
$now        = time();

$insert_stmt = $pdo->prepare(
    "INSERT INTO ratings (recipe_url, recipe_title, stars, name, comment, ip_hash, user_agent, status, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, 'visible', ?)"
);

$exists_stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM ratings WHERE ip_hash = ? AND recipe_url = ?"
);

foreach ($recipe_urls as $recipe_url) {
    $phase        = detect_phase($recipe_url);
    $pool         = $data['pools'][$phase];
    $recipe_title = get_recipe_title($recipe_url);

    // 1. David's rating
    $david_comment = $data['david_template']['comments'][$phase] ?? '';
    $david_ts      = $now - mt_rand(60 * 86400, 120 * 86400);
    $david_hash    = hash('sha256', 'seed-' . $recipe_url . 'David Gans (oprichter)' . IP_SALT);

    $exists_stmt->execute([$david_hash, $recipe_url]);
    if ($exists_stmt->fetchColumn() == 0) {
        $insert_stmt->execute([
            $recipe_url,
            $recipe_title,
            5,
            'David Gans (oprichter)',
            $david_comment ?: null,
            $david_hash,
            'Dutch Goose Seed/1.0',
            $david_ts,
        ]);
        $added++;
        $by_phase[$phase]++;
        $by_stars[5] = ($by_stars[5] ?? 0) + 1;
    } else {
        $skipped++;
    }

    // 2. Community ratings (2 or 3)
    $count   = mt_rand(2, 3);
    $ratings = pick_community_ratings($pool, $count, $recipe_url);

    foreach ($ratings as $r) {
        $comm_ts   = $now - mt_rand(5 * 86400, 60 * 86400);
        $comm_hash = hash('sha256', 'seed-' . $recipe_url . $r['name'] . IP_SALT);

        $exists_stmt->execute([$comm_hash, $recipe_url]);
        if ($exists_stmt->fetchColumn() == 0) {
            $insert_stmt->execute([
                $recipe_url,
                $recipe_title,
                $r['stars'],
                $r['name'],
                $r['text'] !== '' ? $r['text'] : null,
                $comm_hash,
                'Dutch Goose Seed/1.0',
                $comm_ts,
            ]);
            $added++;
            $by_phase[$phase]++;
            $s = $r['stars'];
            $by_stars[$s] = ($by_stars[$s] ?? 0) + 1;
        } else {
            $skipped++;
        }
    }
}

$total_stars = array_sum($by_stars);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Seed resultaat - Dutch Goose</title>
<style>
  :root{--t:#1a7c6b;--tl:#2a9d87;--tp:#e8f5f2;--tm:#c5e8e1;--cr:#faf8f4;--w:#fff;--tx:#1a2b28;--md:#4a6b64;--bd:#d8ede8}
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:var(--cr);color:var(--tx);font-family:'Inter',system-ui,sans-serif;padding:2rem 1rem}
  h1{font-family:'Nunito',sans-serif;color:var(--t);font-size:1.5rem;margin-bottom:1.5rem}
  .card{background:var(--w);border:1px solid var(--bd);border-radius:12px;padding:1.5rem;margin-bottom:1rem;max-width:600px}
  .big{font-size:2rem;font-weight:900;color:var(--t);font-family:'Nunito',sans-serif}
  .row{display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid var(--bd);font-size:.9rem}
  .row:last-child{border-bottom:none}
  .lbl{color:var(--md)}
  .val{font-weight:700}
  a{color:var(--t);text-decoration:none;font-weight:600}
  a:hover{text-decoration:underline}
  .badge{display:inline-block;background:var(--tp);color:var(--t);border:1px solid var(--tm);border-radius:999px;padding:.15rem .6rem;font-size:.75rem;font-weight:700}
</style>
</head>
<body>
<h1>Seed resultaat</h1>

<div class="card">
  <div class="big"><?= $added ?> toegevoegd</div>
  <p style="color:var(--md);margin-top:.3rem"><?= $skipped ?> overgeslagen (al bestaand)</p>
</div>

<div class="card">
  <p style="font-weight:700;margin-bottom:.75rem">Per fase</p>
  <?php foreach ($by_phase as $fase => $n): ?>
    <div class="row"><span class="lbl"><?= htmlspecialchars($fase) ?></span><span class="val"><?= $n ?></span></div>
  <?php endforeach; ?>
</div>

<div class="card">
  <p style="font-weight:700;margin-bottom:.75rem">Verdeling sterren</p>
  <?php foreach ([5,4,3,2,1] as $s): ?>
    <div class="row">
      <span class="lbl"><?= $s ?> ster<?= $s !== 1 ? 'ren' : '' ?></span>
      <span class="val">
        <?= $by_stars[$s] ?? 0 ?>
        <?php if ($total_stars > 0): ?>
          <span style="color:var(--md);font-weight:400;font-size:.8rem">(<?= round((($by_stars[$s] ?? 0) / $total_stars) * 100) ?>%)</span>
        <?php endif; ?>
      </span>
    </div>
  <?php endforeach; ?>
</div>

<p style="margin-top:1rem"><a href="moderatie.php">Naar moderatie-pagina &rarr;</a></p>
</body>
</html>
