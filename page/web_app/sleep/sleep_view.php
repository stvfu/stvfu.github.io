<?php
require __DIR__ . '/auth.php';

if (!is_logged_in()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<title>睡眠視覺化展示</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<style>
:root { --bg: #222; --panel: #2a2a2a; --border: #555; --accent: #c96442; --gold: #ffd700; }
* { box-sizing: border-box; }
body { background: var(--bg); color: #fff; font-family: -apple-system, "Segoe UI", "Noto Sans TC", "Microsoft JhengHei", sans-serif; margin: 0; padding: 16px; }
.container { max-width: 1100px; margin: 0 auto; }
.top-bar { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; }
.top-bar h2 { margin: 0; font-size: 1.2rem; }
.top-bar a { color: var(--accent); text-decoration: none; font-size: 1rem; }
.top-bar a:hover { text-decoration: underline; }

.date-range-bar { margin-top: 16px; display: flex; gap: 10px; align-items: end; flex-wrap: wrap; }
.date-range-bar label { display: flex; flex-direction: column; gap: 4px; font-size: 0.85rem; color: #aaa; }
input[type="date"] { background: #333; color: var(--gold); border: 1px solid var(--border); border-radius: 6px; padding: 6px 8px; font-size: 0.95rem; }
button { background: #27ae60; color: #fff; border: none; border-radius: 6px; padding: 8px 18px; font-size: 0.95rem; cursor: pointer; }
button:hover { background: #219150; }

.legend { margin-top: 16px; font-size: 0.9rem; line-height: 1.8; }
.legend-item { display: inline-flex; align-items: center; gap: 6px; margin-right: 16px; }
.legend-dot { display: inline-block; width: 14px; height: 14px; border-radius: 3px; }

/* Desktop table */
.sleep-table { margin-top: 20px; border-collapse: collapse; width: 100%; }
.sleep-table th, .sleep-table td { border: 1px solid var(--border); padding: 6px 10px; text-align: center; }
.sleep-table th { background: #333; color: var(--gold); font-size: 0.85rem; }
.sleep-table td.date { text-align: right; font-family: monospace; white-space: nowrap; font-size: 0.9rem; }
.sleep-table td.bar-cell { padding: 0; background: var(--bg); }
.sleep-table td.stat { font-family: monospace; font-size: 0.9rem; }

.bar-area-wrap { position: relative; width: 100%; height: 28px; }
.bar-area { position: absolute; left: 0; top: 0; width: 100%; height: 28px; }
.bar { position: absolute; top: 0; height: 28px; }
.grid-area { position: absolute; left: 0; top: 0; width: 100%; height: 28px; pointer-events: none; }
.grid-line { position: absolute; top: 0; height: 100%; border-left: 1px solid #444; }
.grid-line.major { border-left: 1.5px solid #e74c3c; }

.time-header { display: flex; justify-content: space-between; font-size: 0.75rem; color: #888; padding: 0 0 2px; }

.loading { color: #aaa; margin-top: 2em; text-align: center; }
.error { color: #e74c3c; margin-top: 2em; text-align: center; }

/* Mobile cards */
.card-list { display: none; margin-top: 20px; }
.card {
    background: var(--panel); border: 1px solid var(--border); border-radius: 10px;
    margin-bottom: 10px; padding: 12px; overflow: hidden;
}
.card-date { font-family: monospace; font-size: 0.9rem; color: var(--gold); margin-bottom: 8px; }
.card-bar { width: 100%; height: 24px; position: relative; border-radius: 4px; overflow: hidden; }
.card-stats { display: flex; gap: 0; margin-top: 8px; }
.card-stat { flex: 1; text-align: center; font-size: 0.8rem; line-height: 1.4; }
.card-stat .val { font-family: monospace; font-size: 1rem; font-weight: 600; }
.card-stat .lbl { color: #888; }
.stat-sleep .val { color: #b388d9; }
.stat-waste .val { color: #bbb; }
.stat-active .val { color: #fff; }

@media (max-width: 768px) {
    body { padding: 12px; }
    .sleep-table { display: none; }
    .card-list { display: block; }
    .date-range-bar { flex-direction: column; align-items: stretch; }
    .date-range-bar label { width: 100%; }
    .date-range-bar input[type="date"] { width: 100%; }
    .date-range-bar button { width: 100%; }
    .legend { display: flex; flex-wrap: wrap; gap: 4px 12px; }
}
</style>
</head>
<body>
<div class="container">
    <div class="top-bar">
        <h2>睡眠視覺化</h2>
        <a href="index.php">首頁</a>
        <a href="admin.php">管理</a>
    </div>
    <div class="date-range-bar">
        <label>開始日期 <input type="date" id="dateStart"></label>
        <label>結束日期 <input type="date" id="dateEnd"></label>
        <button onclick="drawChart()">更新圖表</button>
    </div>
    <div class="legend">
        <span class="legend-item"><span class="legend-dot" style="background:#fff"></span>清醒</span>
        <span class="legend-item"><span class="legend-dot" style="background:#999"></span>浪費（躺床/賴床）</span>
        <span class="legend-item"><span class="legend-dot" style="background:#8e44ad"></span>睡眠</span>
    </div>

    <!-- Desktop -->
    <table class="sleep-table">
        <thead>
            <tr>
                <th>日期</th>
                <th id="bar-th">
                    <div class="time-header" id="time-labels"></div>
                </th>
                <th>睡眠</th>
                <th>浪費</th>
                <th>可用</th>
            </tr>
        </thead>
        <tbody id="sleep-tbody"></tbody>
    </table>

    <!-- Mobile -->
    <div class="card-list">
        <div class="time-header" id="time-labels-m" style="margin-bottom:4px;"></div>
        <div id="sleep-cards"></div>
    </div>

    <div id="msg" class="loading">載入資料中...</div>
</div>
<script>
function pad2(n) { return n<10 ? '0'+n : n; }
let allEvents = [], minDate = '', maxDate = '';

function buildTimeLabels() {
    var labels = [0,3,6,9,12,15,18,21,24];
    var html = labels.map(function(h) {
        return '<span>' + pad2(h) + '</span>';
    }).join('');
    document.getElementById('time-labels').innerHTML = html;
    document.getElementById('time-labels-m').innerHTML = html;
}

async function fetchData() {
    try {
        var response = await fetch('./api.php');
        if (!response.ok) {
            if (response.status === 401) { location.href = 'index.php'; return; }
            throw new Error();
        }
        var json = await response.json();
        document.getElementById('msg').style.display = 'none';
        processData(json.data);
        setDefaultDateRange();
        buildTimeLabels();
        drawChart();
    } catch(e) {
        document.getElementById('msg').className = 'error';
        document.getElementById('msg').innerText = '無法載入資料';
    }
}

function processData(raw) {
    raw = raw.trim();
    if (!raw) return;
    var lines = raw.split('\n').map(function(l){return l.trim()}).filter(function(l){return l});
    var events = [];
    for (var line of lines) {
        var parts = line.split(':');
        var dt = parts[0], state = parts[1];
        if (!dt || !state) continue;
        var y = dt.substr(0,4), m = dt.substr(4,2), d = dt.substr(6,2);
        var h = dt.substr(9,2), i = dt.substr(11,2), s = dt.substr(13,2);
        var date = y+'-'+m+'-'+d;
        var time = parseInt(h)*3600 + parseInt(i)*60 + parseInt(s);
        events.push({date:date, time:time, state:state, dt:dt});
    }
    events.sort(function(a,b){return a.dt.localeCompare(b.dt)});
    allEvents = events;
    if (events.length) {
        minDate = events[0].date;
        maxDate = events[events.length-1].date;
    }
}

function setDefaultDateRange() {
    if (!maxDate) return;
    var today = new Date();
    var todayStr = today.getFullYear() + '-' + pad2(today.getMonth()+1) + '-' + pad2(today.getDate());
    var end = (maxDate < todayStr) ? maxDate : todayStr;
    var endDate = new Date(end);
    var startDate = new Date(endDate);
    startDate.setDate(startDate.getDate() - 6);
    var start = startDate.getFullYear() + '-' + pad2(startDate.getMonth()+1) + '-' + pad2(startDate.getDate());
    if (start < minDate) start = minDate;
    document.getElementById('dateStart').value = start;
    document.getElementById('dateEnd').value = end;
}

function getColor(from, to) {
    if (from==='清醒' && (to==='躺床'||to==='睡眠')) return '#fff';
    if (from==='躺床' && (to==='睡眠'||to==='清醒')) return '#999';
    if (from==='賴床' && (to==='清醒'||to==='睡眠')) return '#999';
    if (from==='睡眠' && (to==='清醒'||to==='賴床')) return '#8e44ad';
    return '#fff';
}

function getType(from, to) {
    if (from==='清醒' && (to==='躺床'||to==='睡眠')) return 'active';
    if (from==='躺床' && (to==='睡眠'||to==='清醒')) return 'waste';
    if (from==='賴床' && (to==='清醒'||to==='睡眠')) return 'waste';
    if (from==='睡眠' && (to==='清醒'||to==='賴床')) return 'sleep';
    return 'active';
}

function computeDays() {
    var dateStart = document.getElementById('dateStart').value;
    var dateEnd = document.getElementById('dateEnd').value;
    if (!dateStart || !dateEnd) return [];
    var showDates = [];
    var d0 = new Date(dateStart), d1 = new Date(dateEnd);
    while (d0 <= d1) {
        showDates.push(d0.getFullYear()+'-'+pad2(d0.getMonth()+1)+'-'+pad2(d0.getDate()));
        d0.setDate(d0.getDate()+1);
    }

    var prevState = '清醒', eventIdx = 0;
    var events = allEvents;
    var firstIdx = events.findIndex(function(e){return e.date >= showDates[0]});
    if (firstIdx > 0) { prevState = events[firstIdx-1].state; eventIdx = firstIdx; }
    else { prevState = '清醒'; eventIdx = 0; }

    var days = [];
    for (var date of showDates) {
        var curTime = 0, curState = prevState;
        var todayEvents = [];
        while (eventIdx < events.length && events[eventIdx].date === date) todayEvents.push(events[eventIdx++]);
        var sleepSec=0, wasteSec=0, activeSec=0, segs=[];
        for (var e of todayEvents) {
            var type = getType(curState, e.state), segLen = e.time - curTime;
            if (type==='sleep') sleepSec+=segLen; else if (type==='waste') wasteSec+=segLen; else activeSec+=segLen;
            segs.push({from:curTime, to:e.time, color:getColor(curState, e.state)});
            curTime = e.time; curState = e.state;
        }
        var endType = getType(curState, 'END'), endLen = 86400 - curTime;
        if (endType==='sleep') sleepSec+=endLen; else if (endType==='waste') wasteSec+=endLen; else activeSec+=endLen;
        segs.push({from:curTime, to:86400, color:getColor(curState, 'END')});
        prevState = curState;
        days.push({date:date, segs:segs, sleep:(sleepSec/3600).toFixed(1), waste:(wasteSec/3600).toFixed(1), active:(activeSec/3600).toFixed(1)});
    }
    return days;
}

function buildBars(segs) {
    var html = '';
    for (var seg of segs) {
        var left = (seg.from/86400*100).toFixed(3);
        var w = ((seg.to-seg.from)/86400*100).toFixed(3);
        if (parseFloat(w) < 0.1) continue;
        html += '<span class="bar" style="left:'+left+'%;width:'+w+'%;background:'+seg.color+';"></span>';
    }
    return html;
}

function buildGrid() {
    var html = '';
    for (var h=0; h<=24; h++) {
        var left = (h/24*100).toFixed(3);
        var cls = (h%3===0) ? 'grid-line major' : 'grid-line';
        html += '<span class="'+cls+'" style="left:'+left+'%"></span>';
    }
    return html;
}

function drawChart() {
    var days = computeDays();
    if (!days.length) return;
    var gridHtml = buildGrid();

    // Desktop table
    var tbodyHtml = '';
    for (var day of days) {
        tbodyHtml += '<tr>' +
            '<td class="date">' + day.date.replace(/-/g,'/') + '</td>' +
            '<td class="bar-cell"><div class="bar-area-wrap"><span class="bar-area">' + buildBars(day.segs) + '</span><span class="grid-area">' + gridHtml + '</span></div></td>' +
            '<td class="stat">' + day.sleep + '</td>' +
            '<td class="stat">' + day.waste + '</td>' +
            '<td class="stat">' + day.active + '</td>' +
            '</tr>';
    }
    document.getElementById('sleep-tbody').innerHTML = tbodyHtml;

    // Mobile cards
    var cardsHtml = '';
    for (var day of days) {
        cardsHtml += '<div class="card">' +
            '<div class="card-date">' + day.date.replace(/-/g,'/') + '</div>' +
            '<div class="card-bar"><span class="bar-area" style="position:absolute;left:0;top:0;width:100%;height:100%">' + buildBars(day.segs) + '</span><span class="grid-area" style="position:absolute;left:0;top:0;width:100%;height:100%">' + gridHtml + '</span></div>' +
            '<div class="card-stats">' +
                '<div class="card-stat stat-sleep"><div class="val">' + day.sleep + 'h</div><div class="lbl">睡眠</div></div>' +
                '<div class="card-stat stat-waste"><div class="val">' + day.waste + 'h</div><div class="lbl">浪費</div></div>' +
                '<div class="card-stat stat-active"><div class="val">' + day.active + 'h</div><div class="lbl">可用</div></div>' +
            '</div></div>';
    }
    document.getElementById('sleep-cards').innerHTML = cardsHtml;
}

window.addEventListener('load', fetchData);
window.addEventListener('resize', function() { if (allEvents.length) drawChart(); });
</script>
</body>
</html>
