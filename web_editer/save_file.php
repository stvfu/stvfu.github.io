<?php
    $parentDir = realpath(dirname(__FILE__) . '/..'); // Get the parent directory
    $filename = $_POST['filename'];
    $filePath = realpath($parentDir . '/' . $filename);

    // Security check: Ensure that the file is within the parent directory
    if (strpos($filePath, $parentDir) === 0) {
        if (file_put_contents($filePath, $_POST['content']) !== false) {
            echo "File saved successfully.";
        } else {
            echo "Failed to save the file.";
        }
    } else {
        echo "Access denied.";
    }
?>
