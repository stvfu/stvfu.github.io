<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once '/var/www/data/comic/api_keys.php';
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== FCM_REGISTER_API_KEY) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$token = trim($_POST['token'] ?? '');
if (empty($token) || strlen($token) < 100 || strlen($token) > 300) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

require_once __DIR__ . '/db.php';

$stmt = $db->prepare("INSERT INTO fcm_tokens (token) VALUES (:token)
    ON CONFLICT(token) DO UPDATE SET last_seen = CURRENT_TIMESTAMP");
$stmt->bindValue(':token', $token, SQLITE3_TEXT);
$stmt->execute();

echo json_encode(['status' => 'ok']);
