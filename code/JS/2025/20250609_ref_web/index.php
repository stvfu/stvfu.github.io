<?php
if (isset($_SERVER['HTTP_REFERER'])) {
    $referer = $_SERVER['HTTP_REFERER'];
    echo "訪客是從這個網址來的：$referer";
} else {
    echo "沒有偵測到來源網址（可能是直接輸入網址或瀏覽器隱私設定）";
}
?>