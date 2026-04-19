<?php
echo "Step 1: PHP draait\n"; flush();
echo "Step 2: config include\n"; flush();
require_once __DIR__ . '/config.php';
echo "Step 3: config OK, ADMIN_USER=" . ADMIN_USER . "\n"; flush();
echo "Step 4: db include\n"; flush();
require_once __DIR__ . '/db.php';
echo "Step 5: db OK\n"; flush();
echo "Step 6: PDO test\n"; flush();
$pdo = db_connect();
echo "Step 7: PDO OK\n"; flush();
echo "Step 8: query\n"; flush();
$stmt = $pdo->query("SELECT COUNT(*) FROM ratings");
echo "Step 9: rows=" . $stmt->fetchColumn() . "\n"; flush();
echo "Step 10: password_verify test start\n"; flush();
$testresult = password_verify('dummytest', ADMIN_PASS_HASH);
echo "Step 11: password_verify done, result=" . ($testresult ? 'true' : 'false') . "\n"; flush();
echo "ALL OK\n";
