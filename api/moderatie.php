<?php
// Geen sessions, geen output buffering, alles inline
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Auth via GET params (basic auth werkt niet op deze Plesk/Nginx setup)
if (!isset($_GET['user']) || !isset($_GET['pass']) ||
    $_GET['user'] !== ADMIN_USER ||
    !password_verify($_GET['pass'], ADMIN_PASS_HASH)) {
    ?>
    <!DOCTYPE html><html lang="nl"><head><title>Login - Dutch Goose Moderatie</title><style>
    body{font-family:sans-serif;padding:40px;max-width:400px;margin:auto;background:#f7faf8;color:#1a3a30;}
    h2{margin-bottom:20px;}
    label{display:block;margin-bottom:4px;font-size:14px;}
    input{display:block;width:100%;padding:8px;margin-bottom:12px;box-sizing:border-box;border:1px solid #ccc;border-radius:4px;}
    button{padding:10px 20px;background:#1a3a30;color:#fff;border:0;cursor:pointer;border-radius:4px;font-size:14px;}
    </style></head><body>
    <h2>Moderatie Login</h2>
    <form method="get">
      <label>Gebruiker: <input type="text" name="user" value="admin"></label>
      <label>Wachtwoord: <input type="password" name="pass"></label>
      <button type="submit">Inloggen</button>
    </form>
    </body></html>
    <?php
    exit;
}

$auth_user = $_GET['user'];
$auth_pass = $_GET['pass'];

// Action token
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
    header('Location: moderatie.php?user=' . urlencode($auth_user) . '&pass=' . urlencode($auth_pass));
    exit;
}

// Filters
$filter_status = $_GET['status'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Auth query string om mee te sturen in links
$auth_qs = 'user=' . urlencode($auth_user) . '&pass=' . urlencode($auth_pass);

$pdo = db_connect();

$where = '';
$params = [];
if (in_array($filter_status, ['visible', 'hidden', 'deleted'])) {
    $where = 'WHERE status = ?';
    $params[] = $filter_status;
}

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM ratings $where");
$count_stmt->execute($params);
$total = (int)$count_stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT * FROM ratings $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats_stmt = $pdo->query("SELECT status, COUNT(*) as c FROM ratings GROUP BY status");
$stats = [];
foreach ($stats_stmt as $row) { $stats[$row['status']] = $row['c']; }

$total_pages = (int)ceil($total / $per_page);
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
.pagination { margin-top: 15px; }
.pagination a { margin-right: 5px; color: #1a3a30; text-decoration: none; padding: 4px 8px; background: #e8f0ec; border-radius: 3px; font-size: 13px; }
.pagination a.cur { background: #1a3a30; color: #fff; }
</style>
</head>
<body>
<h1>Moderatie Dutch Goose</h1>

<div class="filters">
  <a href="?<?= $auth_qs ?>&status=all" class="<?= $filter_status==='all'?'active':'' ?>">Alle (<?= array_sum($stats) ?>)</a>
  <a href="?<?= $auth_qs ?>&status=visible" class="<?= $filter_status==='visible'?'active':'' ?>">Zichtbaar (<?= $stats['visible'] ?? 0 ?>)</a>
  <a href="?<?= $auth_qs ?>&status=hidden" class="<?= $filter_status==='hidden'?'active':'' ?>">Verborgen (<?= $stats['hidden'] ?? 0 ?>)</a>
  <a href="?<?= $auth_qs ?>&status=deleted" class="<?= $filter_status==='deleted'?'active':'' ?>">Verwijderd (<?= $stats['deleted'] ?? 0 ?>)</a>
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
  <td><span class="stars"><?= str_repeat('★', (int)$r['stars']) ?><?= str_repeat('☆', 5 - (int)$r['stars']) ?></span></td>
  <td><?= htmlspecialchars($r['name']) ?></td>
  <td><?= htmlspecialchars($r['comment'] ?? '') ?></td>
  <td><?= htmlspecialchars(substr($r['ip_hash'], 0, 8)) ?></td>
  <td><span class="badge badge-<?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
  <td>
    <form method="post" action="?<?= $auth_qs ?>" style="display:inline">
      <input type="hidden" name="action_token" value="<?= htmlspecialchars($action_token) ?>">
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
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

<?php if ($total_pages > 1): ?>
<div class="pagination">
  <?php for ($p = 1; $p <= $total_pages; $p++): ?>
    <a href="?<?= $auth_qs ?>&status=<?= urlencode($filter_status) ?>&page=<?= $p ?>"
       class="<?= $p === $page ? 'cur' : '' ?>"><?= $p ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<div class="stats">
  <strong>Stats:</strong>
  Totaal: <?= array_sum($stats) ?> |
  Zichtbaar: <?= $stats['visible'] ?? 0 ?> |
  Verborgen: <?= $stats['hidden'] ?? 0 ?> |
  Verwijderd: <?= $stats['deleted'] ?? 0 ?>
</div>

</body>
</html>
