<?php
require_once __DIR__ . '/config/db.php';
$stmt = $pdo->query("SELECT id, timer_last_started, UNIX_TIMESTAMP(timer_last_started) as ts, UNIX_TIMESTAMP() as now_ts, (UNIX_TIMESTAMP() - UNIX_TIMESTAMP(timer_last_started)) as elapsed FROM tasks WHERE id = 3");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
