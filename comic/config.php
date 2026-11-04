<?php
define('SITE_NAME', '忽田各作品集');
define('SITE_DESC', '同人誌社團「猛少女之拳」');
define('BASE_PATH', __DIR__);
define('BASE_URL', '/comic');
define('DATA_FILE', __DIR__ . '/data/manga.json');

$MANGA = [];
if (file_exists(DATA_FILE)) {
    $data = json_decode(file_get_contents(DATA_FILE), true);
    if (is_array($data)) $MANGA = $data;
}
