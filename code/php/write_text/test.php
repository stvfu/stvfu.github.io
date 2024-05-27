<?php
// 檢查是否有數據被提交
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["inputText"])) {
    // 獲取輸入框的內容
    $inputText = $_POST["inputText"];
    
    // 將內容寫入test.txt文件中
    file_put_contents("test.txt", $inputText);
    
    // 可選：顯示一個消息或重定向用戶
    echo "內容已保存到test.txt文件中。";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Save to File</title>
</head>
<body>
    <!-- 表單用於提交用戶輸入 -->
    <form action="test.php" method="post">
        <input type="text" name="inputText" required>
        <button type="submit">保存</button>
    </form>
</body>
</html>
