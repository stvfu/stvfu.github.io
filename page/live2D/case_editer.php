<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['password'])) {
    require '/var/www/admin_credentials.php';
    if ($_POST['password'] == ADMIN_PASS) {
        $_SESSION['authenticated'] = true;
    } else {
        $error = "密碼錯誤，請重試。";
    }
}

if (empty($_SESSION['authenticated'])) {
?>
<!DOCTYPE html>
<html>
<head>
    <title>Live2D專案編輯器 - 登入</title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="page.css">
</head>
<body style="font-family:calibri" text="#fff" bgcolor="#343434" link="#CC6" vlink="#FC6" alink="#AA0">
    <div id="loginContainer">
        <h1>Live2D專案編輯器 - 登入</h1>
        <form method="post">
            <label for="password">請輸入密碼:</label><br>
            <input type="password" id="password" name="password"><br>
            <button type="submit">登入</button>
        </form>
        <?php if (isset($error)) echo "<p style='color:red'>$error</p>"; ?>
    </div>
</body>
</html>
<?php exit; } ?>

<!DOCTYPE html>
<html>
<head>
    <title>Live2D專案編輯器</title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="page.css">
    <style>.nav-link { float: right; margin-right: 30px; font-size: 14px; color: #CC6; }</style>
</head>
<body style="font-family:calibri" text="#fff" bgcolor="#343434" link="#CC6" vlink="#FC6" alink="#AA0">

    <div id="stickyHeader">
        <h1>
            Live2D專案編輯器
            <a href="index.php" class="nav-link">[瀏覽模式]</a>
        </h1>
        <hr>
        排序按鈕:<br>
        <button id="sortDate" onclick="sortTable(1,'date'); toggleBtn('sortDate','日期排序(舊->新)','日期排序(新->舊)')">日期排序(舊->新)</button>
        <button id="sortText1" onclick="sortTable(2,'text'); toggleBtn('sortText1','委託人排序(A->Z)','委託人排序(Z->A)')">委託人排序(A->Z)</button>
        <button id="sortNum" onclick="sortTable(4,'number'); toggleBtn('sortNum','價格排序(低->高)','價格排序(高->低)')">價格排序(低->高)</button>
        <button id="sortText2" onclick="sortTable(6,'text'); toggleBtn('sortText2','項目(A->Z)','項目(Z->A)')">項目(A->Z)</button>
        <hr>
        篩選欄位:
        <select id="filterColumn">
            <option value="1">日期</option>
            <option value="2">委託人</option>
            <option value="4">價格</option>
        </select>
        輸入關鍵字: <input type="text" id="filterValue">
        <button onclick="filterTable()">篩選</button>
        <br>以下為專案列表
        <button onclick="openModal()">新增</button>
        <button onclick="exportTxt()">匯出至 live2D_case.txt</button>
        <span id="exportMsg" style="color:#0f0;font-size:13px"></span>
        <br>
    </div>
<br>

    <div id="tableContainer">
        <table id="dataTable" border="1" cellpadding="1" cellspacing="1"></table>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <div id="editFormContainer"></div>
            <br><br><br><button onclick="closeModal()">關閉</button>
        </div>
    </div>

<hr>

<script>
var FIELDS = [
    {key: 'date', label: '日期'},
    {key: 'client', label: '委託人'},
    {key: 'unit', label: '單位'},
    {key: 'price', label: '價格'},
    {key: 'item', label: '製作項目'},
    {key: 'note', label: '備註'},
    {key: 'task', label: '任務'},
    {key: 'demo', label: 'Demo影片'},
    {key: 'show_url', label: '實際演出節目'}
];

var data = [];

fetch('api.php?action=list')
    .then(function(r) { return r.json(); })
    .then(function(d) {
        data = d;
        generateTable(data);
    });

function generateTable(data) {
    var table = document.getElementById("dataTable");
    table.innerHTML = '';

    var thead = document.createElement('thead');
    var hr = document.createElement('tr');
    var th = document.createElement('th');
    th.textContent = '操作';
    hr.appendChild(th);
    for (var i = 0; i < FIELDS.length; i++) {
        th = document.createElement('th');
        th.textContent = FIELDS[i].label;
        hr.appendChild(th);
    }
    thead.appendChild(hr);
    table.appendChild(thead);

    var tbody = document.createElement('tbody');
    for (var i = 0; i < data.length; i++) {
        var row = document.createElement('tr');
        var td = document.createElement('td');
        td.innerHTML = '<button onclick="openModal(' + data[i].id + ')">編輯</button>';
        row.appendChild(td);
        for (var j = 0; j < FIELDS.length; j++) {
            td = document.createElement('td');
            td.innerHTML = data[i][FIELDS[j].key] || '';
            row.appendChild(td);
        }
        tbody.appendChild(row);
    }
    table.appendChild(tbody);
}

function openModal(id) {
    var isNew = (id === undefined);
    var row = {};
    if (!isNew) {
        for (var i = 0; i < data.length; i++) {
            if (data[i].id == id) { row = data[i]; break; }
        }
    }

    var html = '<form id="editForm">';
    for (var i = 0; i < FIELDS.length; i++) {
        var f = FIELDS[i];
        html += '<label>' + f.label + ':</label><br>';
        html += '<input type="text" name="' + f.key + '" value="' + (row[f.key] || '').replace(/"/g, '&quot;') + '" style="width:100%"><br>';
    }
    html += '<br><button type="button" onclick="saveRow(' + (isNew ? 'null' : id) + ')">保存</button>';
    if (!isNew) {
        html += ' <button type="button" onclick="deleteRow(' + id + ')" style="color:red">刪除</button>';
    }
    html += '</form>';

    document.getElementById("editFormContainer").innerHTML = html;
    document.getElementById("editModal").style.display = "block";
}

function closeModal() {
    document.getElementById("editModal").style.display = "none";
}

function collectForm() {
    var form = document.getElementById("editForm");
    var obj = {};
    for (var i = 0; i < FIELDS.length; i++) {
        obj[FIELDS[i].key] = form.querySelector('[name="' + FIELDS[i].key + '"]').value;
    }
    return obj;
}

function saveRow(id) {
    var obj = collectForm();
    var isNew = (id === null);
    if (!isNew) obj.id = id;

    fetch('api.php?action=' + (isNew ? 'create' : 'update'), {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(obj)
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.ok) {
            alert('保存成功');
            location.reload();
        } else {
            alert('保存失敗: ' + (res.error || ''));
        }
    });
}

function deleteRow(id) {
    if (!confirm('確定要刪除這一行嗎？')) return;
    fetch('api.php?action=delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id})
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.ok) {
            alert('刪除成功');
            location.reload();
        } else {
            alert('刪除失敗: ' + (res.error || ''));
        }
    });
}

function filterTable() {
    var col = parseInt(document.getElementById("filterColumn").value);
    var val = document.getElementById("filterValue").value.toLowerCase();
    var rows = document.getElementById("dataTable").getElementsByTagName("tr");
    for (var i = 1; i < rows.length; i++) {
        var cell = rows[i].getElementsByTagName("TD")[col];
        if (cell) {
            var text = (cell.innerText || cell.textContent).toLowerCase();
            rows[i].style.display = text.indexOf(val) > -1 ? "" : "none";
        }
    }
}

var sortDirection = {1:true, 2:true, 4:true, 6:true};

function sortTable(n, type) {
    var table = document.getElementById("dataTable");
    var switching = true;
    while (switching) {
        switching = false;
        var rows = table.rows;
        for (var i = 1; i < rows.length - 1; i++) {
            var x = rows[i].getElementsByTagName("TD")[n];
            var y = rows[i+1].getElementsByTagName("TD")[n];
            var cmp;
            if (type === 'number') cmp = Number(x.innerHTML) - Number(y.innerHTML);
            else cmp = x.innerHTML.toLowerCase().localeCompare(y.innerHTML.toLowerCase());
            if ((sortDirection[n] ? 1 : -1) * cmp > 0) {
                rows[i].parentNode.insertBefore(rows[i+1], rows[i]);
                switching = true;
                break;
            }
        }
    }
    sortDirection[n] = !sortDirection[n];
}

function toggleBtn(id, a, b) {
    var btn = document.getElementById(id);
    btn.textContent = btn.textContent === a ? b : a;
}

function exportTxt() {
    fetch('api.php?action=export', { method: 'POST' })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            var msg = document.getElementById('exportMsg');
            if (res.ok) {
                msg.textContent = '匯出成功（' + res.count + ' 筆）';
            } else {
                msg.style.color = '#f00';
                msg.textContent = '匯出失敗: ' + (res.error || '');
            }
            setTimeout(function() { msg.textContent = ''; }, 3000);
        });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>
</body>
</html>
