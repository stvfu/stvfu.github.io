<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>取得網頁原始碼</title>
</head>
<body>
    <h1>取得網頁原始碼</h1>
    <form method="post">
        <label for="url">輸入 URL：</label><br>
        <input type="text" id="url" name="url" style="width: 100%;"><br><br>
        <button type="submit">取得 HTML</button>
    </form>
    <br>
    <label for="html">HTML 原始碼：</label><br>
    <textarea id="html" name="html" rows="20" style="width: 100%;" readonly>
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['url'])) {
    $url = $_POST['url'];
    // 驗證 URL 是否有效
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        // 嘗試取得網頁內容
        try {
            $html = file_get_contents($url);
            echo htmlspecialchars($html); // 將 HTML 特殊字元轉換為實體以避免破壞頁面
        } catch (Exception $e) {
            echo "無法取得該 URL 的內容：" . htmlspecialchars($e->getMessage());
        }
    } else {
        echo "請輸入有效的 URL。";
    }
}
?>
    </textarea>
</body>
</html>