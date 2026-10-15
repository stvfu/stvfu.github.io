<?php
require __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$db = get_db();
$rows = $db->query("SELECT recorded_at, action FROM sleep_log ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$lines = [];
foreach ($rows as $row) {
    $lines[] = $row['recorded_at'] . ':' . $row['action'];
}

echo json_encode(['data' => implode("\n", $lines)], JSON_UNESCAPED_UNICODE);
