<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('session.use_cookies', '0');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Basic auth — volledig stateless, geen sessions
$user = $_SERVER['PHP_AUTH_USER'] ?? '';
$pass = $_SERVER['PHP_AUTH_PW']   ?? '';

if ($user !== ADMIN_USER || !password_verify($pass, ADMIN_PASS_HASH)) {
    header('WWW-Authenticate: Basic realm="Dutch Goose Moderatie"');
    http_response_code(401);
    echo '<!DOCTYPE html><html lang="nl"><body><h2>Toegang geweigerd</h2></body></html>';
    exit;
}

// Action token: sha256(ADMIN_PASS_HASH . 'moderatie-action')
// Alleen admins die al authenticated zijn kunnen dit token zien in de HTML.
$action_token = hash('sha256', ADMIN_PASS_HASH . 'moderatie-action');

$pdo = db_connect();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_token = $_POST['action_token'] ?? '';
    if (!hash_equals($action_token, $post_token)) {
        http_response_code(403);
        echo '<p>Ongeldige action token.</p>';
        exit;
    }

    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);

    if ($id > 0) {
        if ($action === 'hide') {
            $pdo->prepare("UPDATE ratings SET status='hidden' WHERE id=?")->execute([$id]);
        } elseif ($action === 'show') {
            $pdo->prepare("UPDATE ratings SET status='visible' WHERE id=?")->execute([$id]);
        } elseif ($action === 'delete') {
            $pdo->prepare("UPDATE ratings SET status='deleted' WHERE id=?")->execute([$id]);
        }
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Filters
$filter_status = $_GET['status'] ?? 'all';
$filter_recipe = $_GET['recipe'] ?? '';
$filter_search = $_GET['q']      ?? '';
$page          = max(1, (int) ($_GET['page'] ?? 1));
$per_page      = 50;
$offset        = ($page - 1) * $per_page;

$where  = [];
$params = [];

if ($filter_status !== 'all' && in_array($filter_status, ['visible', 'hidden', 'deleted'])) {
    $where[]  = 'status = ?';
    $params[] = $filter_status;
}
if ($filter_recipe !== '') {
    $where[]  = 'recipe_url = ?';
    $params[] = $filter_recipe;
}
if ($filter_search !== '') {
    $where[]  = '(name LIKE ? OR comment LIKE ?)';
    $params[] = '%' . $filter_search . '%';
    $params[] = '%' . $filter_search . '%';
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM ratings $where_sql");
$count_stmt->execute($params);
$total = (int) $count_stmt->fetchColumn();

$rows_stmt = $pdo->prepare("SELECT * FROM ratings $where_sql ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
$rows_stmt->execute($params);
$rows = $rows_stmt->fetchAll();

$recipes_stmt = $pdo->query("SELECT DISTINCT recipe_url, recipe_title FROM ratings ORDER BY recipe_url");
$recipes      = $recipes_stmt->fetchAll();

$stats = $pdo->query("
    SELECT
        COUNT(*) as total,
        ROUND(AVG(CASE WHEN status='visible' THEN stars END), 2) as avg_visible,
        SUM(CASE WHEN stars=5 THEN 1 ELSE 0 END) as s5,
        SUM(CASE WHEN stars=4 THEN 1 ELSE 0 END) as s4,
        SUM(CASE WHEN stars=3 THEN 1 ELSE 0 END) as s3,
        SUM(CASE WHEN stars=2 THEN 1 ELSE 0 END) as s2,
        SUM(CASE WHEN stars=1 THEN 1 ELSE 0 END) as s1
    FROM ratings
")->fetch();

$total_pages = (int) ceil($total / $per_page);

function stars_html(int $n): string {
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $out .= $i <= $n
            ? '<span style="color:#f59e0b">&#9733;</span>'
            : '<span style="color:#d1d5db">&#9733;</span>';
    }
    return $out;
}

function status_badge(string $s): string {
    $map = [
        'visible' => ['background:#d1fae5;color:#065f46', 'Zichtbaar'],
        'hidden'  => ['background:#fef3c7;color:#92400e', 'Verborgen'],
        'deleted' => ['background:#fee2e2;color:#991b1b', 'Verwijderd'],
    ];
    [$style, $label] = $map[$s] ?? ['background:#f3f4f6;color:#374151', $s];
    return "<span style=\"display:inline-block;padding:.15rem .5rem;border-radius:9999px;font-size:.72rem;font-weight:700;$style\">$label</span>";
}

function qp(array $extra = []): string {
    $p = array_merge($_GET, $extra);
    return '?' . http_build_query(array_filter($p, fn($v) => $v !== ''));
}

$base_url = strtok($_SERVER['REQUEST_URI'], '?');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dutch Goose Moderatie</title>
<style>
  :root{--t:#1a7c6b;--tl:#2a9d87;--tp:#e8f5f2;--tm:#c5e8e1;--cr:#faf8f4;--w:#fff;--tx:#1a2b28;--md:#4a6b64;--sf:#7a9b94;--bd:#d8ede8}
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:var(--cr);color:var(--tx);font-family:'Inter',system-ui,sans-serif;font-size:14px;line-height:1.5}
  header{background:var(--t);color:#fff;padding:.9rem 1.5rem;display:flex;align-items:center;gap:1rem}
  header h1{font-family:'Nunito',sans-serif;font-size:1.2rem;font-weight:900}
  header span{font-size:.8rem;opacity:.75}
  .container{max-width:1300px;margin:0 auto;padding:1.5rem}
  .stats-bar{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:.75rem;margin-bottom:1.5rem}
  .stat-card{background:var(--w);border:1px solid var(--bd);border-radius:10px;padding:.9rem 1rem;text-align:center}
  .stat-card .val{font-size:1.6rem;font-weight:800;color:var(--t);font-family:'Nunito',sans-serif}
  .stat-card .lbl{font-size:.72rem;color:var(--sf);margin-top:.1rem}
  .filters{background:var(--w);border:1px solid var(--bd);border-radius:10px;padding:1rem 1.2rem;margin-bottom:1rem;display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end}
  .filters label{display:flex;flex-direction:column;gap:.25rem;font-size:.75rem;font-weight:600;color:var(--md)}
  .filters select,.filters input{border:1px solid var(--bd);border-radius:7px;padding:.4rem .7rem;font-size:.82rem;color:var(--tx);background:var(--cr);outline:none}
  .filters select:focus,.filters input:focus{border-color:var(--t)}
  .filters .btn-filter{background:var(--t);color:#fff;border:none;border-radius:7px;padding:.45rem 1rem;font-size:.82rem;font-weight:700;cursor:pointer;font-family:'Nunito',sans-serif}
  table{width:100%;border-collapse:collapse;background:var(--w);border-radius:10px;overflow:hidden;border:1px solid var(--bd)}
  th{background:var(--tp);color:var(--t);font-family:'Nunito',sans-serif;font-weight:800;font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;padding:.7rem 1rem;text-align:left;border-bottom:2px solid var(--tm)}
  td{padding:.65rem 1rem;border-bottom:1px solid var(--bd);vertical-align:top;font-size:.82rem}
  tr:last-child td{border-bottom:none}
  tr:hover td{background:#f8fffe}
  .comment-cell{max-width:220px;word-break:break-word}
  .recipe-link{color:var(--t);text-decoration:none;font-weight:600}
  .recipe-link:hover{text-decoration:underline}
  form.action-form{display:inline}
  .btn-action{border:none;border-radius:6px;padding:.3rem .65rem;font-size:.75rem;font-weight:700;cursor:pointer;font-family:'Nunito',sans-serif;transition:opacity .15s}
  .btn-action:hover{opacity:.8}
  .btn-hide{background:#fef3c7;color:#92400e}
  .btn-show{background:#d1fae5;color:#065f46}
  .btn-del{background:#fee2e2;color:#991b1b}
  .pagination{display:flex;gap:.4rem;margin-top:1rem;flex-wrap:wrap}
  .pagination a,.pagination span{display:inline-block;padding:.35rem .75rem;border-radius:7px;border:1px solid var(--bd);color:var(--md);font-size:.8rem;text-decoration:none;background:var(--w)}
  .pagination a:hover{background:var(--tp);color:var(--t);border-color:var(--tm)}
  .pagination .cur{background:var(--t);color:#fff;border-color:var(--t)}
  .empty{text-align:center;padding:3rem;color:var(--sf)}
  @media(max-width:900px){.stats-bar{grid-template-columns:repeat(3,1fr)}table{font-size:.78rem}th,td{padding:.55rem .6rem}.comment-cell{max-width:130px}}
</style>
</head>
<body>
<header>
  <div>
    <h1>Dutch Goose Moderatie</h1>
    <span>Rating systeem beheer</span>
  </div>
  <div style="margin-left:auto;font-size:.78rem;opacity:.7">Ingelogd als <?= htmlspecialchars(ADMIN_USER) ?></div>
</header>

<div class="container">

  <!-- Stats -->
  <div class="stats-bar">
    <div class="stat-card"><div class="val"><?= $stats['total'] ?></div><div class="lbl">Totaal ratings</div></div>
    <div class="stat-card"><div class="val"><?= number_format((float)($stats['avg_visible'] ?? 0), 1, ',', '') ?></div><div class="lbl">Gem. score (zichtbaar)</div></div>
    <div class="stat-card"><div class="val"><?= $stats['s5'] ?></div><div class="lbl">5 sterren</div></div>
    <div class="stat-card"><div class="val"><?= $stats['s4'] ?></div><div class="lbl">4 sterren</div></div>
    <div class="stat-card"><div class="val"><?= $stats['s3'] ?></div><div class="lbl">3 sterren</div></div>
    <div class="stat-card"><div class="val"><?= $stats['s2'] ?></div><div class="lbl">2 sterren</div></div>
    <div class="stat-card"><div class="val"><?= $stats['s1'] ?></div><div class="lbl">1 ster</div></div>
  </div>

  <!-- Filters -->
  <form class="filters" method="get" action="<?= htmlspecialchars($base_url) ?>">
    <label>Status
      <select name="status">
        <option value="all"     <?= $filter_status === 'all'     ? 'selected' : '' ?>>Alle</option>
        <option value="visible" <?= $filter_status === 'visible' ? 'selected' : '' ?>>Zichtbaar</option>
        <option value="hidden"  <?= $filter_status === 'hidden'  ? 'selected' : '' ?>>Verborgen</option>
        <option value="deleted" <?= $filter_status === 'deleted' ? 'selected' : '' ?>>Verwijderd</option>
      </select>
    </label>
    <label>Recept
      <select name="recipe">
        <option value="">Alle recepten</option>
        <?php foreach ($recipes as $r): ?>
          <option value="<?= htmlspecialchars($r['recipe_url']) ?>"
            <?= $filter_recipe === $r['recipe_url'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($r['recipe_title']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Zoeken
      <input type="text" name="q" placeholder="Naam of comment..." value="<?= htmlspecialchars($filter_search) ?>">
    </label>
    <button type="submit" class="btn-filter">Filteren</button>
    <?php if ($filter_status !== 'all' || $filter_recipe || $filter_search): ?>
      <a href="<?= htmlspecialchars($base_url) ?>" style="align-self:flex-end;color:var(--sf);font-size:.78rem;text-decoration:none">Wis filters</a>
    <?php endif; ?>
  </form>

  <p style="font-size:.78rem;color:var(--sf);margin-bottom:.5rem"><?= $total ?> resultaten gevonden. Pagina <?= $page ?> van <?= max(1, $total_pages) ?>.</p>

  <!-- Table -->
  <?php if (empty($rows)): ?>
    <div class="empty">Geen ratings gevonden voor dit filter.</div>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>Datum</th>
        <th>Recept</th>
        <th>Sterren</th>
        <th>Naam</th>
        <th class="comment-cell">Comment</th>
        <th>IP</th>
        <th>Status</th>
        <th>Acties</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td style="white-space:nowrap"><?= date('d-m-Y H:i', $row['created_at']) ?></td>
        <td>
          <a class="recipe-link" href="https://dutchgoose.nl<?= htmlspecialchars($row['recipe_url']) ?>" target="_blank">
            <?= htmlspecialchars($row['recipe_title']) ?>
          </a>
        </td>
        <td style="white-space:nowrap"><?= stars_html((int)$row['stars']) ?></td>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td class="comment-cell"><?= htmlspecialchars($row['comment'] ?? '') ?></td>
        <td style="font-family:monospace;font-size:.72rem;color:var(--sf)"><?= htmlspecialchars(substr($row['ip_hash'], 0, 8)) ?></td>
        <td><?= status_badge($row['status']) ?></td>
        <td style="white-space:nowrap">
          <?php if ($row['status'] !== 'hidden'): ?>
            <form class="action-form" method="post">
              <input type="hidden" name="action_token" value="<?= htmlspecialchars($action_token) ?>">
              <input type="hidden" name="action" value="hide">
              <input type="hidden" name="id" value="<?= $row['id'] ?>">
              <button type="submit" class="btn-action btn-hide">Verbergen</button>
            </form>
          <?php endif; ?>
          <?php if ($row['status'] !== 'visible'): ?>
            <form class="action-form" method="post">
              <input type="hidden" name="action_token" value="<?= htmlspecialchars($action_token) ?>">
              <input type="hidden" name="action" value="show">
              <input type="hidden" name="id" value="<?= $row['id'] ?>">
              <button type="submit" class="btn-action btn-show">Tonen</button>
            </form>
          <?php endif; ?>
          <?php if ($row['status'] !== 'deleted'): ?>
            <form class="action-form" method="post">
              <input type="hidden" name="action_token" value="<?= htmlspecialchars($action_token) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $row['id'] ?>">
              <button type="submit" class="btn-action btn-del">Verwijderen</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="<?= htmlspecialchars($base_url . qp(['page' => $page - 1])) ?>">&laquo; Vorige</a>
    <?php endif; ?>
    <?php for ($p = max(1, $page - 3); $p <= min($total_pages, $page + 3); $p++): ?>
      <?php if ($p === $page): ?>
        <span class="cur"><?= $p ?></span>
      <?php else: ?>
        <a href="<?= htmlspecialchars($base_url . qp(['page' => $p])) ?>"><?= $p ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $total_pages): ?>
      <a href="<?= htmlspecialchars($base_url . qp(['page' => $page + 1])) ?>">Volgende &raquo;</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>

</div>
</body>
</html>
