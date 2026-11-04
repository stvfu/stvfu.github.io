<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$token = trim($_POST['token'] ?? '');
if (empty($token)) {
    http_response_code(400);
    echo json_encode(['error' => 'Token required']);
    exit;
}

require_once __DIR__ . '/db.php';

$stmt = $db->prepare("INSERT INTO fcm_tokens (token) VALUES (:token)
    ON CONFLICT(token) DO UPDATE SET last_seen = CURRENT_TIMESTAMP");
$stmt->bindValue(':token', $token, SQLITE3_TEXT);
$stmt->execute();

echo json_encode(['status' => 'ok']);
