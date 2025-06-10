<html>
    <head>
        <title>忽田軟體</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta http-equiv="keywords" content="忽田各, 猛少女之拳, 6n0, 鬼滅之拳, Live2D, 聯發科, 晨星">
        <style>
            #text {font-family:"微軟正黑體";font-size:16px;}
            #point_Y {font-family:"微軟正黑體";color:#FFFF00;font-size:20px;}
        </style>
        <script type="text/JavaScript" src="../../flot.js"></script>    
    </head>

    <body
        style= "font-family: calibri"     
        text="#ffffff" 
        bgcolor="#343434" 
        text=#FFFFFF 
        link=#CCCC66 
        vlink=#FFCC66 
        alink=#AAAA00
    >
    <!--onLoad="init()"-->
        
<h1><a href = "../../main.html">回首頁</a></h1>
<br>大家安安oωO! 這裡是忽田各!! 一個興趣使然的工程師
<br>聯絡方式: stvfu.fu@gmail.com
<hr><!------------------------------------------------------------------------------------------------------->

<?php
    // 讀取 tool_list.txt 文件
    $file = fopen("tool_list.txt", "r");
    if ($file) {
        echo '<table border="1" cellspacing="0" style="width:600px">';
        echo '<tbody>';
        
        // 讀取文件的每一行
        while (($line = fgets($file)) !== false) {
            $columns = explode('|', trim($line));
            $tool_info = explode(';', $columns[2]);
            $tool_name = $tool_info[0];
            $tool_link = $tool_info[1];
            echo '<tr>';
            echo '<td>' . $columns[0] . '</td>'; // 分類
            echo '<td>' . $columns[1] . '</td>'; // 縮圖
            echo '<td><a href="' . $tool_link . '">' . $tool_name . '</a></td>'; // 工具名稱和連結
            echo '<td>' . $columns[3] . '</td>'; // 發布日期
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        fclose($file);
    } else {
        echo "無法讀取文件";
    }
?>

<hr><!------------------------------------------------------------------------------------------------------->

<h1>其他雜項</h1>

    </body>
</html>