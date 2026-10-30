<?php
$pageDir = __DIR__ . '/page';
if (!is_dir($pageDir)) mkdir($pageDir, 0755, true);

if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'];

    if ($action === 'list') {
        $files = glob($pageDir . '/*.json');
        $result = [];
        foreach ($files as $f) {
            $name = basename($f, '.json');
            $result[] = ['name' => $name, 'mtime' => filemtime($f)];
        }
        usort($result, fn($a, $b) => $b['mtime'] - $a['mtime']);
        echo json_encode($result);
        exit;
    }

    if ($action === 'load' && isset($_GET['file'])) {
        $name = basename($_GET['file'], '.json');
        $path = $pageDir . '/' . $name . '.json';
        if (file_exists($path)) {
            readfile($path);
        } else {
            http_response_code(404);
            echo json_encode(['error' => '檔案不存在']);
        }
        exit;
    }

    if ($action === 'save' && isset($_GET['file'])) {
        $name = basename($_GET['file'], '.json');
        if (!preg_match('/^[a-zA-Z0-9_\-\x{4e00}-\x{9fff}]+$/u', $name)) {
            http_response_code(400);
            echo json_encode(['error' => '檔名含有不允許的字元']);
            exit;
        }
        $raw = file_get_contents('php://input');
        json_decode($raw);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['error' => 'JSON 格式錯誤']);
            exit;
        }
        file_put_contents($pageDir . '/' . $name . '.json', $raw);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'delete' && isset($_GET['file'])) {
        $name = basename($_GET['file'], '.json');
        $path = $pageDir . '/' . $name . '.json';
        if (file_exists($path)) {
            unlink($path);
            echo json_encode(['ok' => true]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => '檔案不存在']);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => '未知操作']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <title>漫畫分鏡 Layout 設計器</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:"微軟正黑體","Noto Sans TC",Arial,sans-serif;background:#eee;padding:12px}
    button{border:1px solid #ccc;border-radius:4px;background:#f8f8f8;cursor:pointer;font-family:inherit;font-size:.9em;padding:5px 10px}
    button:hover{background:#e0e0e0}
    .btn-s{background:#28a745;color:#fff;border-color:#28a745}
    .btn-s:hover{background:#1e7e34}
    .btn-d{background:#dc3545;color:#fff;border-color:#dc3545}
    .btn-d:hover{background:#bd2130}

    .page-nav{display:flex;align-items:center;gap:8px;margin-bottom:12px;padding:8px 12px;background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.1);width:fit-content}
    .page-nav button{padding:6px 14px}
    .page-nav button:disabled{opacity:.35;cursor:default}
    .page-nav .pi{font-size:1.05em;min-width:90px;text-align:center}
    .page-nav .ap{background:#d4edda;border-color:#28a745}
    .page-nav .dp{background:#f8d7da;border-color:#dc3545}

    .main{display:flex;gap:16px;align-items:flex-start}
    .canvas-wrap{flex-shrink:0}
    #canvas{display:block;border:2px solid #223366;background:#fff;cursor:crosshair}
    .hint{text-align:center;color:#999;font-size:.82em;margin-top:6px;min-height:1.3em}

    .panel{flex:1;min-width:300px;max-width:420px;display:flex;flex-direction:column;gap:10px}
    .card{background:#fff;border-radius:8px;padding:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
    .card-t{font-weight:bold;font-size:1.02em;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid #eee}
    .card-sub{color:#999;font-size:.8em;margin-top:4px}
    .prop-card{border-left:3px solid #007bff}
    .prop-card .card-t{color:#007bff}

    #newText{width:100%;height:55px;resize:vertical;font-size:1em;padding:6px;border:1px solid #ccc;border-radius:4px;font-family:inherit}
    .pr{display:flex;align-items:center;gap:6px;margin-top:6px;flex-wrap:wrap}
    .pr label{font-size:.88em;color:#555}
    .pr input[type="number"]{width:58px;padding:4px;border:1px solid #ccc;border-radius:4px}
    .prop-ta{width:100%;height:50px;margin-top:6px;font-size:.95em;padding:5px;border:1px solid #ccc;border-radius:4px;font-family:inherit;resize:vertical}

    .il{max-height:140px;overflow-y:auto}
    .il-item{display:flex;align-items:center;padding:4px 8px;border-radius:4px;cursor:pointer;font-size:.85em;margin-bottom:2px;gap:6px}
    .il-item:hover{background:#f0f7ff}
    .il-item.act{background:#cce5ff}
    .il-item span{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

    .fl{list-style:none;max-height:140px;overflow-y:auto;margin:6px 0}
    .fl li{display:flex;align-items:center;justify-content:space-between;padding:5px 8px;border:1px solid #eee;border-radius:4px;margin-bottom:2px;cursor:pointer;font-size:.88em}
    .fl li:hover{background:#f0f7ff}
    .fl li.act{background:#cce5ff;border-color:#007bff}
    .fl .fd{color:#dc3545;cursor:pointer;font-weight:bold;padding:0 4px}
    .sr{display:flex;gap:6px;margin-top:6px}
    .sr input[type="text"]{flex:1;padding:5px;border:1px solid #ccc;border-radius:4px;font-size:.95em}
    .msg{font-size:.82em;color:#28a745;min-height:1.1em;margin-top:4px}
    .fb{display:flex;gap:6px;margin-top:8px}
  </style>
</head>
<body>
  <div class="page-nav">
    <button onclick="prevPage()" id="prevBtn">◀ 上一頁</button>
    <span class="pi" id="pageInfo">1 / 1</span>
    <button onclick="nextPage()" id="nextBtn">下一頁 ▶</button>
    <button class="ap" onclick="addPage()">＋ 新增頁</button>
    <button class="dp" onclick="delPage()" id="delBtn">✕ 刪除頁</button>
  </div>
  <div class="main">
    <div class="canvas-wrap">
      <svg id="canvas" width="500" height="750"></svg>
      <div class="hint" id="hint"></div>
    </div>
    <div class="panel">
      <div class="card">
        <div class="card-t">新增台詞</div>
        <textarea id="newText" placeholder="輸入台詞內容…" oninput="renderCanvas()"></textarea>
        <div class="pr">
          <label>字體</label><input type="number" id="newTextSize" value="40" min="8" max="120">
        </div>
        <div class="card-sub">輸入後在畫布上點擊放置</div>
      </div>
      <div class="card">
        <div class="card-t">快速加線</div>
        <div style="display:flex;gap:6px">
          <button onclick="addHLine()">━ 水平線</button>
          <button onclick="addVLine()">┃ 垂直線</button>
        </div>
        <div class="card-sub">或在畫布空白處拖曳畫線（Shift 鎖定方向）</div>
      </div>
      <div class="card prop-card" id="propPanel" style="display:none"></div>
      <div class="card">
        <div class="card-t">本頁元素</div>
        <div style="font-size:.82em;color:#888;margin-bottom:3px">台詞</div>
        <div class="il" id="textList"></div>
        <div style="font-size:.82em;color:#888;margin:8px 0 3px">分割線</div>
        <div class="il" id="lineList"></div>
      </div>
      <div class="card">
        <div class="card-t">伺服器檔案</div>
        <ul class="fl" id="fileList"></ul>
        <div class="sr">
          <input type="text" id="fileName" placeholder="輸入檔名…">
          <button class="btn-s" onclick="serverSave()">儲存</button>
        </div>
        <div class="msg" id="serverMsg"></div>
        <div class="fb">
          <button onclick="importJson()">從電腦匯入</button>
          <button onclick="exportJson()">下載 JSON</button>
          <input type="file" id="jsonFile" style="display:none" accept=".json">
        </div>
      </div>
    </div>
  </div>
<script>
// ===== State =====
let pages = [{texts:[], lines:[]}];
let pageIdx = 0;
let currentFileName = '';
let sel = {type:null, idx:-1};
let inter = null; // interaction state
let mousePos = null;
const W = 500, H = 750;

function cur(){ return pages[pageIdx]; }
function esc(s){ return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function svgPt(e){
  const svg = document.getElementById('canvas');
  const pt = svg.createSVGPoint();
  pt.x = e.clientX; pt.y = e.clientY;
  return pt.matrixTransform(svg.getScreenCTM().inverse());
}

function findTarget(el){
  const svg = document.getElementById('canvas');
  while(el && el !== svg){
    if(el.dataset && el.dataset.t) return el;
    el = el.parentElement;
  }
  return null;
}

function showMsg(t, err){
  const el = document.getElementById('serverMsg');
  el.textContent = t;
  el.style.color = err ? '#dc3545' : '#28a745';
  setTimeout(()=>{ el.textContent=''; }, 3000);
}

// ===== SVG Helpers =====
const NS = "http://www.w3.org/2000/svg";
function mkLine(x1,y1,x2,y2,attrs){
  const el = document.createElementNS(NS,"line");
  el.setAttribute("x1",x1); el.setAttribute("y1",y1);
  el.setAttribute("x2",x2); el.setAttribute("y2",y2);
  for(const[k,v] of Object.entries(attrs||{})) el.setAttribute(k,v);
  return el;
}
function mkCircle(cx,cy,r,attrs){
  const el = document.createElementNS(NS,"circle");
  el.setAttribute("cx",cx); el.setAttribute("cy",cy); el.setAttribute("r",r);
  for(const[k,v] of Object.entries(attrs||{})) el.setAttribute(k,v);
  return el;
}
function mkVertText(svg,x,y,size,color,text,attrs){
  const el = document.createElementNS(NS,"text");
  el.setAttribute("font-size",size);
  el.setAttribute("fill",color);
  el.setAttribute("writing-mode","vertical-rl");
  el.setAttribute("font-family",'"微軟正黑體","Noto Sans TC",sans-serif');
  text.replace(/\\n/g,'\n').split('\n').forEach((ln,i)=>{
    const ts = document.createElementNS(NS,"tspan");
    ts.setAttribute("x", x - i * size);
    ts.setAttribute("y", y);
    ts.textContent = ln;
    el.appendChild(ts);
  });
  for(const[k,v] of Object.entries(attrs||{})) el.setAttribute(k,v);
  svg.appendChild(el);
}

// ===== Rendering =====
function renderCanvas(){
  const svg = document.getElementById('canvas');
  svg.innerHTML = '';
  const page = cur();

  // Background
  const bg = document.createElementNS(NS,"rect");
  bg.setAttribute("width",W); bg.setAttribute("height",H);
  bg.setAttribute("fill","white");
  svg.appendChild(bg);

  // Lines - visual
  page.lines.forEach((l,i)=>{
    const isSel = sel.type==='line' && sel.idx===i;
    svg.appendChild(mkLine(l.x1,l.y1,l.x2,l.y2,{
      stroke: isSel?'#ff3333':'#00a8e8',
      'stroke-width': l.width,
      'stroke-linecap':'round',
      'pointer-events':'none'
    }));
  });
  // Lines - hit area
  page.lines.forEach((l,i)=>{
    svg.appendChild(mkLine(l.x1,l.y1,l.x2,l.y2,{
      stroke:'transparent',
      'stroke-width': Math.max(l.width+8, 14),
      'data-t':'line', 'data-i':i,
      style:'cursor:pointer'
    }));
  });
  // Texts
  page.texts.forEach((t,i)=>{
    const isSel = sel.type==='text' && sel.idx===i;
    mkVertText(svg, t.x, t.y, t.size, isSel?'#ff3333':'#222', t.text,
      {'data-t':'text','data-i':i, style:'cursor:move'});
  });
  // Endpoint handles
  if(sel.type==='line' && page.lines[sel.idx]){
    const l = page.lines[sel.idx];
    svg.appendChild(mkCircle(l.x1,l.y1,7,{fill:'#ff3333',stroke:'#fff','stroke-width':2,'data-t':'ep','data-i':sel.idx,'data-w':'start',style:'cursor:move'}));
    svg.appendChild(mkCircle(l.x2,l.y2,7,{fill:'#ff3333',stroke:'#fff','stroke-width':2,'data-t':'ep','data-i':sel.idx,'data-w':'end',style:'cursor:move'}));
  }
  // Ghost text preview
  if(mousePos && !inter){
    const txt = document.getElementById('newText').value;
    if(txt.trim()){
      const sz = parseInt(document.getElementById('newTextSize').value)||40;
      mkVertText(svg, Math.round(mousePos.x), Math.round(mousePos.y), sz,
        'rgba(0,140,220,0.35)', txt, {'pointer-events':'none'});
    }
  }
  // Rubber band line
  if(inter && inter.t==='draw' && mousePos){
    svg.appendChild(mkLine(inter.x1,inter.y1, Math.round(mousePos.x),Math.round(mousePos.y),{
      stroke:'#ff3333','stroke-width':3,'stroke-dasharray':'8,4','stroke-linecap':'round','pointer-events':'none'
    }));
  }
  updateHint();
}

function renderPanel(){
  updatePropPanel();
  updateItemLists();
  updatePageNav();
}

function render(){ renderCanvas(); renderPanel(); }

// ===== Hint =====
function updateHint(){
  const h = document.getElementById('hint');
  const hasText = document.getElementById('newText').value.trim();
  if(inter){
    if(inter.t==='draw') h.textContent='放開滑鼠完成畫線（Shift 鎖定水平/垂直）';
    else h.textContent='拖曳中…放開確定位置';
  } else if(sel.type){
    h.textContent = (sel.type==='text'?'拖曳移動':'拖曳端點調整')+' ∣ Delete 刪除 ∣ Esc 取消';
  } else if(hasText){
    h.textContent='在畫布上點擊放置台詞';
  } else {
    h.textContent='點擊選取 ∣ 拖曳空白處畫線 ∣ Shift 鎖定方向';
  }
}

// ===== Canvas Events =====
const svgEl = document.getElementById('canvas');

svgEl.addEventListener('mousedown', e=>{
  e.preventDefault();
  const p = svgPt(e);
  const tgt = findTarget(e.target);

  if(tgt){
    const type = tgt.dataset.t;
    const idx = parseInt(tgt.dataset.i);
    if(type==='ep'){
      sel = {type:'line', idx};
      inter = {t:'ep', idx, w:tgt.dataset.w};
    } else if(type==='text'){
      sel = {type:'text', idx};
      const txt = cur().texts[idx];
      inter = {t:'drag', idx, ox:p.x-txt.x, oy:p.y-txt.y};
      renderPanel();
    } else if(type==='line'){
      sel = {type:'line', idx};
      renderPanel();
    }
  } else {
    inter = {t:'down', x:p.x, y:p.y};
  }
  renderCanvas();
});

document.addEventListener('mousemove', e=>{
  const p = svgPt(e);
  const r = svgEl.getBoundingClientRect();
  const over = e.clientX>=r.left && e.clientX<=r.right && e.clientY>=r.top && e.clientY<=r.bottom;
  mousePos = over ? p : null;

  if(!inter){ renderCanvas(); return; }

  if(inter.t==='drag'){
    const txt = cur().texts[inter.idx];
    txt.x = Math.round(Math.max(0, Math.min(W, p.x - inter.ox)));
    txt.y = Math.round(Math.max(0, Math.min(H, p.y - inter.oy)));
    renderCanvas();
  } else if(inter.t==='ep'){
    const l = cur().lines[inter.idx];
    let nx = Math.round(Math.max(0,Math.min(W,p.x)));
    let ny = Math.round(Math.max(0,Math.min(H,p.y)));
    if(e.shiftKey){
      const ox = inter.w==='start'?l.x2:l.x1;
      const oy = inter.w==='start'?l.y2:l.y1;
      if(Math.abs(nx-ox)>Math.abs(ny-oy)) ny=oy; else nx=ox;
    }
    if(inter.w==='start'){l.x1=nx;l.y1=ny;} else {l.x2=nx;l.y2=ny;}
    renderCanvas();
  } else if(inter.t==='down'){
    const dist = Math.hypot(p.x-inter.x, p.y-inter.y);
    if(dist>5 && !document.getElementById('newText').value.trim()){
      inter = {t:'draw', x1:Math.round(inter.x), y1:Math.round(inter.y)};
    }
  } else if(inter.t==='draw'){
    if(e.shiftKey){
      const dx=Math.abs(p.x-inter.x1), dy=Math.abs(p.y-inter.y1);
      if(dx>dy) mousePos={x:p.x, y:inter.y1}; else mousePos={x:inter.x1, y:p.y};
    }
    renderCanvas();
  }
});

document.addEventListener('mouseup', e=>{
  if(!inter) return;
  const p = svgPt(e);

  if(inter.t==='down'){
    const txt = document.getElementById('newText');
    if(txt.value.trim()){
      const sz = parseInt(document.getElementById('newTextSize').value)||40;
      cur().texts.push({x:Math.round(inter.x), y:Math.round(inter.y), size:sz, text:txt.value});
      sel = {type:'text', idx:cur().texts.length-1};
      txt.value='';
    } else {
      sel = {type:null, idx:-1};
    }
  } else if(inter.t==='draw'){
    let ex = Math.round(Math.max(0,Math.min(W,p.x)));
    let ey = Math.round(Math.max(0,Math.min(H,p.y)));
    if(e.shiftKey){
      if(Math.abs(ex-inter.x1)>Math.abs(ey-inter.y1)) ey=inter.y1; else ex=inter.x1;
    }
    if(Math.hypot(ex-inter.x1, ey-inter.y1)>10){
      cur().lines.push({x1:inter.x1, y1:inter.y1, x2:ex, y2:ey, width:6});
      sel = {type:'line', idx:cur().lines.length-1};
    }
  }
  inter = null;
  render();
});

// ===== Keyboard =====
document.addEventListener('keydown', e=>{
  if(e.target.tagName==='INPUT'||e.target.tagName==='TEXTAREA') return;
  if((e.key==='Delete'||e.key==='Backspace') && sel.type){
    if(sel.type==='text') cur().texts.splice(sel.idx,1);
    else cur().lines.splice(sel.idx,1);
    sel={type:null,idx:-1};
    render();
    e.preventDefault();
  } else if(e.key==='Escape'){
    sel={type:null,idx:-1}; inter=null;
    render();
  }
});

// ===== Property Panel =====
function updatePropPanel(){
  const p = document.getElementById('propPanel');
  if(!sel.type || (sel.type==='text'&&!cur().texts[sel.idx]) || (sel.type==='line'&&!cur().lines[sel.idx])){
    p.style.display='none'; return;
  }
  p.style.display='';
  if(sel.type==='text'){
    const t = cur().texts[sel.idx];
    p.innerHTML=`<div class="card-t">台詞屬性</div>
      <div class="pr"><label>X</label><input type="number" value="${t.x}" oninput="updST('x',this.value)">
      <label>Y</label><input type="number" value="${t.y}" oninput="updST('y',this.value)">
      <label>字體</label><input type="number" value="${t.size}" oninput="updST('size',this.value)"></div>
      <textarea class="prop-ta" oninput="updST('text',this.value)">${esc(t.text)}</textarea>
      <div style="margin-top:8px"><button class="btn-d" onclick="delSel()">刪除此台詞</button></div>`;
  } else {
    const l = cur().lines[sel.idx];
    p.innerHTML=`<div class="card-t">線條屬性</div>
      <div class="pr"><label>起 X</label><input type="number" value="${l.x1}" oninput="updSL('x1',this.value)">
      <label>Y</label><input type="number" value="${l.y1}" oninput="updSL('y1',this.value)"></div>
      <div class="pr"><label>終 X</label><input type="number" value="${l.x2}" oninput="updSL('x2',this.value)">
      <label>Y</label><input type="number" value="${l.y2}" oninput="updSL('y2',this.value)"></div>
      <div class="pr"><label>寬度</label><input type="number" value="${l.width}" oninput="updSL('width',this.value)" min="1" max="30"></div>
      <div style="margin-top:8px"><button class="btn-d" onclick="delSel()">刪除此線條</button></div>`;
  }
}

function updST(k,v){
  const t = cur().texts[sel.idx]; if(!t) return;
  t[k] = k==='text'? v : (parseInt(v)||0);
  renderCanvas();
}
function updSL(k,v){
  const l = cur().lines[sel.idx]; if(!l) return;
  l[k] = parseInt(v)||0;
  renderCanvas();
}
function delSel(){
  if(sel.type==='text') cur().texts.splice(sel.idx,1);
  else if(sel.type==='line') cur().lines.splice(sel.idx,1);
  sel={type:null,idx:-1};
  render();
}

// ===== Item Lists =====
function updateItemLists(){
  const tl = document.getElementById('textList');
  tl.innerHTML = cur().texts.map((t,i)=>{
    const preview = t.text.replace(/\n/g,' ');
    const label = preview.length>12 ? preview.substring(0,12)+'…' : preview;
    return `<div class="il-item${sel.type==='text'&&sel.idx===i?' act':''}" onclick="sel={type:'text',idx:${i}};render()">
      <span>${esc(label)}</span><span style="color:#888;font-size:.8em">(${t.x},${t.y})</span></div>`;
  }).join('')||'<div style="color:#ccc;font-size:.82em;padding:4px">無</div>';

  const ll = document.getElementById('lineList');
  ll.innerHTML = cur().lines.map((l,i)=>{
    return `<div class="il-item${sel.type==='line'&&sel.idx===i?' act':''}" onclick="sel={type:'line',idx:${i}};render()">
      <span>(${l.x1},${l.y1}) → (${l.x2},${l.y2})</span></div>`;
  }).join('')||'<div style="color:#ccc;font-size:.82em;padding:4px">無</div>';
}

// ===== Quick Lines =====
function addHLine(){
  cur().lines.push({x1:0, y1:Math.round(H/2), x2:W, y2:Math.round(H/2), width:6});
  sel={type:'line', idx:cur().lines.length-1};
  render();
}
function addVLine(){
  cur().lines.push({x1:Math.round(W/2), y1:0, x2:Math.round(W/2), y2:H, width:6});
  sel={type:'line', idx:cur().lines.length-1};
  render();
}

// ===== Page Navigation =====
function updatePageNav(){
  document.getElementById('pageInfo').textContent=`${pageIdx+1} / ${pages.length}`;
  document.getElementById('prevBtn').disabled = pageIdx===0;
  document.getElementById('nextBtn').disabled = pageIdx===pages.length-1;
  document.getElementById('delBtn').disabled = pages.length<=1;
}
function switchPage(i){ sel={type:null,idx:-1}; inter=null; pageIdx=i; render(); }
function prevPage(){ if(pageIdx>0) switchPage(pageIdx-1); }
function nextPage(){ if(pageIdx<pages.length-1) switchPage(pageIdx+1); }
function addPage(){ pages.push({texts:[],lines:[]}); switchPage(pages.length-1); }
function delPage(){
  if(pages.length<=1) return;
  if(!confirm(`確定刪除第 ${pageIdx+1} 頁？`)) return;
  pages.splice(pageIdx,1);
  if(pageIdx>=pages.length) pageIdx=pages.length-1;
  switchPage(pageIdx);
}

// ===== Server Files =====
async function loadFileList(){
  try{
    const res = await fetch('?action=list');
    const list = await res.json();
    const ul = document.getElementById('fileList');
    ul.innerHTML='';
    list.forEach(f=>{
      const li = document.createElement('li');
      li.className = f.name===currentFileName?'act':'';
      li.innerHTML=`<span>${esc(f.name)}</span><span class="fd" title="刪除">✕</span>`;
      li.querySelector('span').addEventListener('click',()=>serverLoad(f.name));
      li.querySelector('.fd').addEventListener('click',e=>{e.stopPropagation();serverDel(f.name);});
      ul.appendChild(li);
    });
  }catch(e){}
}

async function serverLoad(name){
  try{
    const res = await fetch(`?action=load&file=${encodeURIComponent(name)}`);
    if(!res.ok){showMsg('載入失敗',1);return;}
    applyData(await res.json());
    currentFileName=name;
    document.getElementById('fileName').value=name;
    loadFileList();
    showMsg(`已載入「${name}」`);
  }catch(e){showMsg('載入失敗',1);}
}

async function serverSave(){
  const name=document.getElementById('fileName').value.trim();
  if(!name){showMsg('請輸入檔名',1);return;}
  try{
    const res=await fetch(`?action=save&file=${encodeURIComponent(name)}`,{
      method:'POST', headers:{'Content-Type':'application/json'},
      body:JSON.stringify({pages})
    });
    const r=await res.json();
    if(r.ok){currentFileName=name;loadFileList();showMsg(`已儲存「${name}」`);}
    else showMsg(r.error||'儲存失敗',1);
  }catch(e){showMsg('儲存失敗',1);}
}

async function serverDel(name){
  if(!confirm(`確定刪除「${name}」？`))return;
  try{
    const r=await(await fetch(`?action=delete&file=${encodeURIComponent(name)}`)).json();
    if(r.ok){if(currentFileName===name)currentFileName='';loadFileList();showMsg(`已刪除「${name}」`);}
    else showMsg(r.error||'刪除失敗',1);
  }catch(e){showMsg('刪除失敗',1);}
}

// ===== JSON Import/Export =====
function exportJson(){
  const blob=new Blob([JSON.stringify({pages},null,2)],{type:'application/json'});
  const a=document.createElement('a');
  a.href=URL.createObjectURL(blob);
  a.download=(currentFileName||'comic_layout')+'.json';
  a.click(); URL.revokeObjectURL(a.href);
}
function importJson(){ document.getElementById('jsonFile').click(); }

function convertComicJson(d){
  return d.pages.map(p=>{
    const pw=parseFloat(p.page_W)||18.8, ph=parseFloat(p.page_H)||26.7;
    const sx=W/pw, sy=H/ph;
    return {
      texts:(p.text||[]).map(t=>({x:Math.round(t.X*sx),y:Math.round(t.Y*sy),size:parseInt(t.size)||20,text:t.content.source})),
      lines:(p.Line||[]).map(l=>({x1:Math.round(l.StartX*sx),y1:Math.round(l.StartY*sy),x2:Math.round(l.EndX*sx),y2:Math.round(l.EndY*sy),width:6}))
    };
  });
}

function applyData(data){
  if(data.pages && data.pages[0] && data.pages[0].page_W) pages=convertComicJson(data);
  else if(data.pages) pages=data.pages;
  else if(data.texts||data.lines) pages=[{texts:data.texts||[],lines:data.lines||[]}];
  else{alert('無法辨識的 JSON 格式');return;}
  if(!pages.length) pages=[{texts:[],lines:[]}];
  pageIdx=0; sel={type:null,idx:-1}; inter=null;
  render();
}

document.getElementById('jsonFile').addEventListener('change',function(e){
  const f=e.target.files[0]; if(!f)return;
  const reader=new FileReader();
  reader.onload=function(evt){
    try{
      applyData(JSON.parse(evt.target.result));
      currentFileName=f.name.replace(/\.json$/i,'');
      document.getElementById('fileName').value=currentFileName;
      loadFileList();
    }catch(err){alert('JSON 格式錯誤');}
  };
  reader.readAsText(f);
  e.target.value='';
});

// ===== Init =====
render();
loadFileList();
</script>
</body>
</html>
