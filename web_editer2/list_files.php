<?php
    $baseDir = realpath(dirname(__FILE__) . '/..'); // Base directory
    $dir = isset($_POST['dir']) ? realpath($baseDir . '/' . $_POST['dir']) : $baseDir; // Get the directory from POST or default to base directory

    if ($dir === false || strpos($dir, $baseDir) !== 0) {
        echo json_encode(['error' => 'Invalid directory']);
        exit;
    }

    $files = scandir($dir);
    $result = [];
    foreach ($files as $file) {
        if ($file === '.') continue;
        $filePath = $dir . DIRECTORY_SEPARATOR . $file;
        $relativePath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $filePath);
        $result[] = [
            'name' => $file,
            'isDir' => is_dir($filePath),
            'path' => $relativePath
        ];
    }
    echo json_encode($result);
?>