<?php
require __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method not allowed']);
    exit;
}

$bearer = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (strpos($bearer, 'Bearer ') === 0) {
    $token = substr($bearer, 7);
} else {
    $token = '';
}

if (!$token || !hash_equals($config['sleep_api_token'], $token)) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$act = $input['action'] ?? '';

$validActions = ['bed' => '躺床', 'sleep' => '睡眠', 'lazy' => '賴床', 'wake' => '清醒'];
$db = get_db();

if ($act === 'checkin') {
    $type = $input['type'] ?? '';
    if (!isset($validActions[$type])) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid type', 'valid' => array_keys($validActions)]);
        exit;
    }
    $at = $input['at'] ?? null;
    if ($at) {
        $ts = strtotime($at);
        if (!$ts) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid datetime format']);
            exit;
        }
        $recorded = date('Ymd_His', $ts);
    } else {
        $recorded = date('Ymd_His');
    }
    $stmt = $db->prepare("INSERT INTO sleep_log (recorded_at, action) VALUES (?, ?)");
    $stmt->execute([$recorded, $validActions[$type]]);
    $id = $db->lastInsertId();
    echo json_encode(['ok' => true, 'id' => (int)$id, 'recorded_at' => $recorded, 'action' => $validActions[$type]]);

} elseif ($act === 'status') {
    $row = get_last_record();
    if (!$row) {
        echo json_encode(['action' => null, 'recorded_at' => null]);
    } else {
        $actionKey = array_search($row['action'], $validActions) ?: '';
        echo json_encode(['action' => $row['action'], 'action_key' => $actionKey, 'recorded_at' => $row['recorded_at']]);
    }

} elseif ($act === 'history') {
    $limit = min(100, max(1, (int)($input['limit'] ?? 20)));
    $rows = $db->query("SELECT id, recorded_at, action FROM sleep_log ORDER BY recorded_at DESC, id DESC LIMIT $limit")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['records' => $rows]);

} else {
    http_response_code(400);
    echo json_encode(['error' => 'unknown action', 'valid' => ['checkin', 'status', 'history']]);
}
