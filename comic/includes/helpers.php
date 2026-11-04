<?php
require_once __DIR__ . '/../config.php';

function get_manga(string $id): ?array {
    global $MANGA;
    return $MANGA[$id] ?? null;
}

function get_all_manga(): array {
    global $MANGA;
    return $MANGA;
}

function get_chapter(string $manga_id, string $chapter_id): ?array {
    $manga = get_manga($manga_id);
    if (!$manga) return null;
    foreach ($manga['chapters'] as $ch) {
        if ($ch['id'] === $chapter_id) return $ch;
    }
    return null;
}

function get_chapter_pages(array $chapter): array {
    $dir = BASE_PATH . '/' . $chapter['dir'];
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!empty($chapter['order'])) {
        $order_file = BASE_PATH . '/' . $chapter['order'];
        if (file_exists($order_file)) {
            $data = json_decode(file_get_contents($order_file), true);
            if (is_array($data)) {
                return array_values(array_filter($data, fn($f) => file_exists($dir . '/' . $f)));
            }
        }
    }

    $files = [];
    foreach (glob($dir . '/*') as $path) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $files[] = basename($path);
        }
    }
    sort($files, SORT_NATURAL);
    return $files;
}

function get_adjacent_chapters(string $manga_id, string $chapter_id): array {
    $manga = get_manga($manga_id);
    if (!$manga) return ['prev' => null, 'next' => null];

    $chapters = $manga['chapters'];
    $index = null;
    foreach ($chapters as $i => $ch) {
        if ($ch['id'] === $chapter_id) { $index = $i; break; }
    }
    if ($index === null) return ['prev' => null, 'next' => null];

    return [
        'prev' => $index > 0 ? $chapters[$index - 1] : null,
        'next' => $index < count($chapters) - 1 ? $chapters[$index + 1] : null,
    ];
}
