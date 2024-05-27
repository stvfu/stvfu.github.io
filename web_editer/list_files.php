<?php
    $parentDir = realpath(dirname(__FILE__) . '/..'); // Get the parent directory
    $files = scandir($parentDir);
    echo json_encode($files);
?>
