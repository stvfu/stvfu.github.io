<?php
header('Content-Type: application/json; charset=utf-8');

$db = new PDO('sqlite:/var/www/database/live2D.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$action = $_GET['action'] ?? 'list';

// 需要登入的操作
$writeActions = ['create', 'update', 'delete', 'export'];
if (in_array($action, $writeActions)) {
    session_start();
    if (empty($_SESSION['authenticated'])) {
        http_response_code(401);
        echo json_encode(['error' => '未登入']);
        exit;
    }
}

switch ($action) {
    case 'list':
        $rows = $db->query("SELECT * FROM projects ORDER BY date ASC")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        break;

    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($row ?: ['error' => '找不到'], JSON_UNESCAPED_UNICODE);
        break;

    case 'create':
        $input = json_decode(file_get_contents('php://input'), true);
        $stmt = $db->prepare("INSERT INTO projects (date, client, unit, price, item, note, task, demo, show_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $input['date'] ?? '',
            $input['client'] ?? '',
            $input['unit'] ?? '',
            $input['price'] ?? '0',
            $input['item'] ?? '',
            $input['note'] ?? '',
            $input['task'] ?? '',
            $input['demo'] ?? '',
            $input['show_url'] ?? '',
        ]);
        echo json_encode(['ok' => true, 'id' => $db->lastInsertId()], JSON_UNESCAPED_UNICODE);
        break;

    case 'update':
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        $stmt = $db->prepare("UPDATE projects SET date=?, client=?, unit=?, price=?, item=?, note=?, task=?, demo=?, show_url=? WHERE id=?");
        $stmt->execute([
            $input['date'] ?? '',
            $input['client'] ?? '',
            $input['unit'] ?? '',
            $input['price'] ?? '0',
            $input['item'] ?? '',
            $input['note'] ?? '',
            $input['task'] ?? '',
            $input['demo'] ?? '',
            $input['show_url'] ?? '',
            $id,
        ]);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        break;

    case 'delete':
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        break;

    case 'export':
        $keyMap = [
            'date' => '日期', 'client' => '委託人', 'unit' => '單位',
            'price' => '價格', 'item' => '製作項目', 'note' => '備註',
            'task' => '任務', 'demo' => 'Demo影片', 'show_url' => '實際演出節目'
        ];
        $rows = $db->query("SELECT date, client, unit, price, item, note, task, demo, show_url FROM projects ORDER BY date ASC")->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            $mapped = [];
            foreach ($keyMap as $en => $zh) {
                $mapped[$zh] = $row[$en];
            }
            $out[] = $mapped;
        }
        $json = json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (file_put_contents(__DIR__ . '/live2D_case.txt', $json) !== false) {
            echo json_encode(['ok' => true, 'count' => count($out)], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['error' => '寫入失敗'], JSON_UNESCAPED_UNICODE);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => '未知操作']);
}
