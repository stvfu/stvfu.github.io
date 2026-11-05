<?php
require_once __DIR__ . '/includes/helpers.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

if ($action === 'chapter_images') {
    $manga_id = $_GET['id'] ?? '';
    $chapter_id = $_GET['ch'] ?? '';
    $manga = get_manga($manga_id);
    if (!$manga) {
        http_response_code(404);
        echo json_encode(['error' => 'manga not found']);
        exit;
    }
    $chapter = get_chapter($manga_id, $chapter_id);
    if (!$chapter) {
        http_response_code(404);
        echo json_encode(['error' => 'chapter not found']);
        exit;
    }
    $pages = get_chapter_pages($chapter);
    $image_urls = array_map(
        fn($f) => $chapter['dir'] . '/' . rawurlencode($f),
        $pages
    );
    echo json_encode([
        'manga_id' => $manga_id,
        'manga_title' => $manga['title'],
        'chapter_id' => $chapter_id,
        'chapter_title' => $chapter['title'],
        'pages' => array_values($image_urls),
        'total' => count($image_urls),
    ]);
    exit;
}

if ($action === 'app_version') {
    $file = __DIR__ . '/data/app_version.json';
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)) {
            echo json_encode($data);
            exit;
        }
    }
    echo json_encode(['version_code' => 0]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'unknown action']);
