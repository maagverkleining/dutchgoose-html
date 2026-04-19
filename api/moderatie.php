<?php
// Geen sessions, geen output buffering, alles inline
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Basic auth check
if (!isset($_SERVER['PHP_AUTH_USER']) ||
    $_SERVER['PHP_AUTH_USER'] !== ADMIN_USER ||
    !password_verify($_SERVER['PHP_AUTH_PW'] ?? '', ADMIN_PASS_HASH)) {
    header('WWW-Authenticate: Basic realm="Dutch Goose Moderatie"');
    header('HTTP/1.1 401 Unauthorized');
    echo 'Inloggen vereist';
    exit;
}

// Action token voor verberg/toon/verwijder
$action_token = hash('sha256', ADMIN_PASS_HASH . 'moderatie-action');

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action_token'] ?? '') !== $action_token) {
        http_response_code(403);
        exit('Invalid token');
    }
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $pdo = db_connect();
    if ($action === 'hide') {
        $pdo->prepare("UPDATE ratings SET status='hidden' WHERE id=?")->execute([$id]);
    } elseif ($action === 'show') {
        $pdo->prepare("UPDATE ratings SET status='visible' WHERE id=?")->execute([$id]);
    } elseif ($action === 'delete') {
        $pdo->prepare("UPDATE ratings SET status='deleted' WHERE id=?")->execute([$id]);
    }
    header('Location: moderatie.php');
    exit;
}

// Get filters
$filter_status = $_GET['status'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

$pdo = db_connect();

// Build query
$where = '';
$params = [];
if (in_array($filter_status, ['visible','hidden','deleted'])) {
    $where = 'WHERE status = ?';
    $params[] = $filter_status;
}

// Total count
$count_sql = "SELECT COUNT(*) FROM ratings $where";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total = (int)$count_stmt->fetchColumn();

// Fetch ratings
$sql = "SELECT * FROM ratings $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$stats_stmt = $pdo->query("SELECT status, COUNT(*) as c FROM ratings GROUP BY status");
$stats = [];
foreach ($stats_stmt as $row) { $stats[$row['status']] = $row['c']; }
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<title>Moderatie - Dutch Goose</title>
<style>
body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; margin: 20px; color: #1a3a30; background: #f7faf8; }
h1 { color: #1a3a30; }
.filters { margin-bottom: 20px; }
.filters a { margin-right: 10px; color: #4a6b64; text-decoration: none; padding: 5px 10px; background: #e8f0ec; border-radius: 4px; }
.filters a.active { background: #1a3a30; color: #fff; }
table { width: 100%; border-collapse: collapse; background: #fff; }
th, td { padding: 8px; text-align: left; border-bottom: 1px solid #e0e8e4; font-size: 13px; vertical-align: top; }
th { background: #1a3a30; color: #fff; }
.badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 11px; }
.badge-visible { background: #d4edda; color: #155724; }
.badge-hidden { background: #fff3cd; color: #856404; }
.badge-deleted { background: #f8d7da; color: #721c24; }
.stars { color: #f5a623; }
button { padding: 4px 8px; font-size: 11px; cursor: pointer; border: 1px solid #ccc; background: #fff; margin-right: 2px; }
button.danger { background: #d9534f; color: #fff; border-color: #d9534f; }
.stats { background: #fff; padding: 10px; margin-top: 20px; border-radius: 4px; }
</style>
</head>
<body>
<h1>Moderatie Dutch Goose</h1>

<div class="filters">
  <a href="?status=all" class="<?= $filter_status==='all'?'active':'' ?>">Alle (<?= array_sum($stats) ?>)</a>
  <a href="?status=visible" class="<?= $filter_status==='visible'?'active':'' ?>">Zichtbaar (<?= $stats['visible'] ?? 0 ?>)</a>
  <a href="?status=hidden" class="<?= $filter_status==='hidden'?'active':'' ?>">Verborgen (<?= $stats['hidden'] ?? 0 ?>)</a>
  <a href="?status=deleted" class="<?= $filter_status==='deleted'?'active':'' ?>">Verwijderd (<?= $stats['deleted'] ?? 0 ?>)</a>
</div>

<p><?= count($ratings) ?> van <?= $total ?> ratings getoond</p>

<table>
<thead>
<tr><th>Datum</th><th>Recept</th><th>Sterren</th><th>Naam</th><th>Comment</th><th>IP</th><th>Status</th><th>Actie</th></tr>
</thead>
<tbody>
<?php foreach ($ratings as $r): ?>
<tr>
  <td><?= date('Y-m-d H:i', $r['created_at']) ?></td>
  <td><a href="https://dutchgoose.nl<?= htmlspecialchars($r['recipe_url']) ?>" target="_blank"><?= htmlspecialchars(substr($r['recipe_title'], 0, 40)) ?></a></td>
  <td><span class="stars"><?= str_repeat('★', $r['stars']) ?><?= str_repeat('☆', 5 - $r['stars']) ?></span></td>
  <td><?= htmlspecialchars($r['name']) ?></td>
  <td><?= htmlspecialchars($r['comment'] ?? '') ?></td>
  <td><?= substr($r['ip_hash'], 0, 8) ?></td>
  <td><span class="badge badge-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
  <td>
    <form method="post" style="display:inline">
      <input type="hidden" name="action_token" value="<?= $action_token ?>">
      <input type="hidden" name="id" value="<?= $r['id'] ?>">
      <?php if ($r['status'] !== 'hidden'): ?>
        <button name="action" value="hide">Verberg</button>
      <?php endif; ?>
      <?php if ($r['status'] !== 'visible'): ?>
        <button name="action" value="show">Toon</button>
      <?php endif; ?>
      <button name="action" value="delete" class="danger" onclick="return confirm('Verwijderen?')">Verwijder</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<div class="stats">
  <strong>Stats:</strong>
  Totaal: <?= array_sum($stats) ?> |
  Zichtbaar: <?= $stats['visible'] ?? 0 ?> |
  Verborgen: <?= $stats['hidden'] ?? 0 ?> |
  Verwijderd: <?= $stats['deleted'] ?? 0 ?>
</div>

</body>
</html>
