<?php
    $parentDir = realpath(dirname(__FILE__) . '/..'); // Get the parent directory
    $filename = $_POST['filename'];
    $filePath = realpath($parentDir . '/' . $filename);

    // Security check: Ensure that the file is within the parent directory
    if (strpos($filePath, $parentDir) === 0 && file_exists($filePath)) {
        echo file_get_contents($filePath);
    } else {
        echo "File not found or access denied.";
    }
?>
