<!DOCTYPE html>
<html>
<head>
    <title>Live2D專案紀錄</title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="page.css">
    <style>
        body { font-family: calibri, sans-serif; color: #fff; background: #343434; }
        a:link { color: #CC6; } a:visited { color: #FC6; } a:active { color: #AA0; }
        .nav-link { float: right; margin-right: 30px; font-size: 14px; color: #CC6; }
    </style>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/jquery.dataTables.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.js"></script>
</head>
<body>

    <div id="stickyHeader">
        <h1>
            Live2D專案紀錄
            <a href="case_editer.php" class="nav-link">[編輯模式]</a>
        </h1>
        <hr>
        <button id="togglePricing" onclick="togglePricing()">計價方式 [展開]</button>
        <div id="pricingContent" class="hidden">
            時薪500 x 製作時數 x 緊急倍率 - 製作心情折扣XD<br>
            倍率計算: 一個月內:(x1)倍, 兩周內急件 (x1.5), 三天內超急件 (x3)<br>
            熟人/製作有興趣的怪東西通常會有折扣<br>
            <br>
            委託方式: 推特私訊估價XD 平日在線時間 23:00~03:00
        </div>
        <hr>
        排序按鈕:<br>
        <button id="sortDate" onclick="sortTable(0,'date'); toggleBtn('sortDate','日期排序(舊->新)','日期排序(新->舊)')">日期排序(舊->新)</button>
        <button id="sortText1" onclick="sortTable(1,'text'); toggleBtn('sortText1','委託人排序(A->Z)','委託人排序(Z->A)')">委託人排序(A->Z)</button>
        <button id="sortNum" onclick="sortTable(3,'number'); toggleBtn('sortNum','價格排序(低->高)','價格排序(高->低)')">價格排序(低->高)</button>
        <button id="sortText2" onclick="sortTable(6,'text'); toggleBtn('sortText2','項目(A->Z)','項目(Z->A)')">項目(A->Z)</button>
        <hr>
        篩選欄位:
        <select id="filterColumn">
            <option value="0">日期</option>
            <option value="1">委託人</option>
            <option value="3">價格</option>
        </select>
        輸入關鍵字: <input type="text" id="filterValue">
        <button onclick="filterTable()">篩選</button>
        <br>以下為專案列表
        <br>
    </div>
<br>

    <div id="tableContainer">
        <table id="dataTable" border="1" cellpadding="1" cellspacing="1"></table>
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

fetch('api.php?action=list')
    .then(function(r) { return r.json(); })
    .then(function(data) {
        generateTable(data);
        $('#dataTable').DataTable({
            paging: false, searching: true, ordering: true, info: false
        });
    });

function generateTable(data) {
    var table = document.getElementById("dataTable");
    var thead = document.createElement('thead');
    var hr = document.createElement('tr');
    for (var i = 0; i < FIELDS.length; i++) {
        var th = document.createElement('th');
        th.textContent = FIELDS[i].label;
        hr.appendChild(th);
    }
    thead.appendChild(hr);
    table.appendChild(thead);

    var tbody = document.createElement('tbody');
    for (var i = 0; i < data.length; i++) {
        var row = document.createElement('tr');
        for (var j = 0; j < FIELDS.length; j++) {
            var td = document.createElement('td');
            var key = FIELDS[j].key;
            var val = data[i][key] || '';
            if ((key === 'show_url' || key === 'demo') && val.includes('youtube')) {
                td.innerHTML = '<a href="' + val + '" target="_blank">[影片連結]</a>';
            } else {
                td.innerHTML = val;
            }
            row.appendChild(td);
        }
        tbody.appendChild(row);
    }
    table.appendChild(tbody);
}

function togglePricing() {
    var el = document.getElementById("pricingContent");
    var btn = document.getElementById("togglePricing");
    var hidden = el.classList.toggle("hidden");
    btn.textContent = "計價方式 [" + (hidden ? "展開" : "收合") + "]";
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

var sortDirection = {0:true, 1:true, 3:true, 6:true};

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
</script>
</body>
</html>
