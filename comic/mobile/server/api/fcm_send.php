<?php
function getAccessToken() {
    require_once __DIR__ . '/../config.php';
    $keyFile = FIREBASE_KEY_PATH;
    $sa = json_decode(file_get_contents($keyFile), true);

    $now = time();
    $header = base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $payload = base64url_encode(json_encode([
        'iss' => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => $sa['token_uri'],
        'iat' => $now,
        'exp' => $now + 3600,
    ]));

    $signInput = "$header.$payload";
    openssl_sign($signInput, $signature, $sa['private_key'], OPENSSL_ALGO_SHA256);
    $jwt = "$signInput." . base64url_encode($signature);

    $ch = curl_init($sa['token_uri']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]),
    ]);
    $resp = json_decode(curl_exec($ch), true);
    curl_close($ch);

    return $resp['access_token'] ?? null;
}

function sendFcmNotification($token, $title, $body) {
    $accessToken = getAccessToken();
    if (!$accessToken) return false;

    $projectId = FIREBASE_PROJECT_ID;
    $url = "https://fcm.googleapis.com/v1/projects/$projectId/messages:send";

    $message = [
        'message' => [
            'token' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
        ],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $accessToken",
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($message),
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 404 || $code === 400) {
        $respData = json_decode($resp, true);
        $status = $respData['error']['status'] ?? '';
        if ($code === 404 || $status === 'NOT_FOUND' || $status === 'UNREGISTERED') {
            removeToken($token);
        }
    }

    return $code === 200;
}

function removeToken($token) {
    global $db;
    $stmt = $db->prepare("DELETE FROM fcm_tokens WHERE token = :token");
    $stmt->bindValue(':token', $token, SQLITE3_TEXT);
    $stmt->execute();
}

function sendToAllDevices($title, $body) {
    global $db;

    $results = $db->query("SELECT token FROM fcm_tokens");
    $sent = 0;
    $failed = 0;
    $cleaned = 0;

    $tokensBefore = $db->querySingle("SELECT COUNT(*) FROM fcm_tokens");

    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        if (sendFcmNotification($row['token'], $title, $body)) {
            $sent++;
        } else {
            $failed++;
        }
    }

    $tokensAfter = $db->querySingle("SELECT COUNT(*) FROM fcm_tokens");
    $cleaned = $tokensBefore - $tokensAfter;

    return ['sent' => $sent, 'failed' => $failed, 'cleaned' => $cleaned];
}

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
