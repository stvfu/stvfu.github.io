<?php
session_start();
require '/var/www/admin_credentials.php';

if (isset($_POST['logout'])) {
    unset($_SESSION['ai_reading_auth']);
    header('Location: AI_storyboard.php');
    exit;
}
if (isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASS) {
        $_SESSION['ai_reading_auth'] = true;
    } else {
        $login_error = '密碼錯誤';
    }
}
if (empty($_SESSION['ai_reading_auth'])):
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>RE:TADC 分鏡草稿 — 登入</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #0d1117; color: #c9d1d9; font-family: 'Noto Sans TC', 'Microsoft JhengHei', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
.login-box { background: #161b22; border: 1px solid #21262d; border-radius: 12px; padding: 40px; width: 340px; text-align: center; }
.login-box h1 { font-size: 1.3em; color: #e94560; margin-bottom: 8px; }
.login-box p { font-size: .85em; color: #8b949e; margin-bottom: 24px; }
.login-box input[type=password] { width: 100%; padding: 10px 14px; border: 1px solid #30363d; border-radius: 6px; background: #0d1117; color: #c9d1d9; font-size: 1em; margin-bottom: 12px; outline: none; }
.login-box input[type=password]:focus { border-color: #e94560; }
.login-box button { width: 100%; padding: 10px; border: none; border-radius: 6px; background: #e94560; color: #fff; font-size: 1em; cursor: pointer; }
.login-box button:hover { background: #d63851; }
.error { color: #f85149; font-size: .85em; margin-bottom: 12px; }
</style>
</head>
<body>
<form class="login-box" method="post">
    <h1>RE:TADC</h1>
    <p>分鏡草稿</p>
    <?php if (!empty($login_error)): ?>
        <div class="error"><?= htmlspecialchars($login_error) ?></div>
    <?php endif; ?>
    <input type="password" name="password" placeholder="請輸入密碼" autofocus>
    <button type="submit">進入</button>
</form>
</body>
</html>
<?php exit; endif; ?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>RE:TADC EP1 結局延伸 — 分鏡草稿</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #111; color: #ddd; font-family: 'Noto Sans TC', 'Microsoft JhengHei', sans-serif; line-height: 1.6; }

.container { max-width: 800px; margin: 0 auto; padding: 20px; }

header { text-align: center; padding: 30px 0 10px; }
header h1 { font-size: 1.5em; color: #e94560; }
header p { color: #8b949e; font-size: .85em; margin-top: 6px; }

.logout-btn { position: fixed; top: 12px; right: 16px; z-index: 999; }
.logout-btn button { padding: 4px 14px; border: 1px solid #333; border-radius: 6px; background: transparent; color: #888; cursor: pointer; font-size: .8em; }
.logout-btn button:hover { background: #222; color: #ccc; }

.back-link { text-align: center; margin-bottom: 20px; }
.back-link a { color: #58a6ff; text-decoration: none; font-size: .9em; }
.back-link a:hover { text-decoration: underline; }

/* Page card */
.page-card { background: #1a1a1a; border: 1px solid #333; border-radius: 10px; margin-bottom: 30px; overflow: hidden; }
.page-header { background: #1e1e2e; padding: 12px 20px; border-bottom: 1px solid #333; display: flex; justify-content: space-between; align-items: center; }
.page-header h2 { font-size: 1.1em; color: #e94560; margin: 0; }
.page-header .tag { font-size: .75em; padding: 3px 10px; border-radius: 10px; background: #e9456022; color: #e94560; }

/* Manga page canvas */
.manga-page { background: #fff; color: #111; margin: 16px; border-radius: 4px; overflow: hidden; }

/* Panel grid */
.panels { display: flex; flex-direction: column; }
.panel { border-bottom: 3px solid #111; position: relative; min-height: 160px; padding: 16px; display: flex; align-items: center; gap: 16px; }
.panel:last-child { border-bottom: none; }
.panel.tall { min-height: 300px; }
.panel.hero { min-height: 450px; }
.panel.narrow { min-height: 100px; }

/* Speech bubbles */
.bubble { background: #fff; border: 2px solid #111; border-radius: 18px; padding: 8px 14px; font-size: .9em; position: relative; max-width: 220px; line-height: 1.4; }
.bubble.thought { border-style: dashed; border-color: #888; color: #666; font-size: .8em; }
.bubble.shout { border-width: 3px; font-weight: bold; font-size: 1em; border-radius: 8px; background: #fff; }
.bubble.huge { font-size: 1.6em; font-weight: 900; text-align: center; max-width: 100%; padding: 12px 20px; }
.bubble.narration { background: #111; color: #fff; border: none; border-radius: 4px; font-size: .85em; }

.bubble-group { display: flex; flex-direction: column; gap: 8px; flex: 1; }

/* SVG character area */
.char-area { flex-shrink: 0; }

/* Direction text */
.direction { background: #f5f5f5; color: #666; font-size: .78em; padding: 8px 14px; border-top: 1px dashed #ccc; font-style: italic; line-height: 1.5; }

/* Full page panel */
.full-page { min-height: 500px; padding: 24px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; position: relative; }
.full-page .scene-desc { background: #f8f8f8; border: 1px solid #ddd; border-radius: 8px; padding: 16px; font-size: .85em; color: #555; max-width: 500px; margin: 16px 0; text-align: left; line-height: 1.6; }

/* Reference image */
.ref-img { width: 100%; display: block; }

/* Freeze frame overlay */
.freeze { position: relative; }
.freeze::after { content: '— 畫面定格 —'; position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,.7); color: #fff; padding: 4px 20px; font-size: .85em; border-radius: 4px; letter-spacing: 2px; }

/* End mark */
.end-mark { text-align: center; font-size: 1.2em; font-weight: bold; padding: 16px; color: #111; letter-spacing: 3px; border-top: 3px solid #111; }

/* Door visual */
.door-frame { width: 200px; height: 280px; border: 6px solid #333; border-radius: 6px; position: relative; background: linear-gradient(135deg, #fff9 0%, #ffe08855 100%); margin: 0 auto; }
.door-crack { position: absolute; top: 10%; left: 15%; width: 70%; height: 80%; }
.door-shard { position: absolute; background: #ccc; border: 2px solid #888; transform: rotate(var(--r)); }

@media (max-width: 600px) {
    .panel { flex-direction: column; text-align: center; }
    .bubble { max-width: 100%; }
}
</style>
</head>
<body>
<form class="logout-btn" method="post"><button name="logout" value="1">登出</button></form>

<div class="container">

<header>
    <h1>RE:TADC EP1 結局延伸</h1>
    <p>分鏡草稿 — P33（參考）→ P34–P37（擴充）</p>
</header>

<div class="back-link"><a href="AI_reading.php">&larr; 回到分析報告</a></div>

<!-- ========== P33 參考 ========== -->
<div class="page-card">
    <div class="page-header">
        <h2>P33（現有結尾・參考）</h2>
        <span class="tag">67006.jpg</span>
    </div>
    <img class="ref-img" src="images/67006.jpg" alt="P33 - 現有結尾">
</div>

<!-- ========== P34 ========== -->
<div class="page-card">
    <div class="page-header">
        <h2>P34 —「回頭看看吧」</h2>
        <span class="tag">轉向</span>
    </div>
    <div class="manga-page">
        <div class="panels">

            <!-- Panel 1: Leroy behind Jax, hands on shoulders -->
            <div class="panel">
                <div class="char-area">
                    <svg width="180" height="140" viewBox="0 0 180 140">
                        <!-- Leroy (behind, hands on Jax shoulders) -->
                        <circle cx="130" cy="30" r="18" fill="#ddd" stroke="#333" stroke-width="2"/>
                        <rect x="115" y="50" width="30" height="40" rx="4" fill="#888" stroke="#333" stroke-width="2"/>
                        <line x1="115" y1="60" x2="85" y2="75" stroke="#333" stroke-width="3" stroke-linecap="round"/>
                        <line x1="145" y1="60" x2="85" y2="55" stroke="#333" stroke-width="3" stroke-linecap="round"/>
                        <text x="130" y="35" text-anchor="middle" font-size="8" fill="#333">李羅伊</text>
                        <!-- Jax (front, abstracted dark) -->
                        <circle cx="65" cy="35" r="20" fill="#2a2a2a" stroke="#111" stroke-width="2"/>
                        <line x1="55" y1="15" x2="50" y2="0" stroke="#111" stroke-width="3"/>
                        <line x1="75" y1="15" x2="80" y2="0" stroke="#111" stroke-width="3"/>
                        <rect x="48" y="58" width="34" height="45" rx="4" fill="#1a1a1a" stroke="#111" stroke-width="2"/>
                        <text x="65" y="40" text-anchor="middle" font-size="7" fill="#aaa">賈克斯</text>
                        <!-- Hands on shoulders -->
                        <circle cx="85" cy="75" r="5" fill="#ddd" stroke="#333" stroke-width="1.5"/>
                        <circle cx="85" cy="55" r="5" fill="#ddd" stroke="#333" stroke-width="1.5"/>
                    </svg>
                </div>
                <div class="bubble-group">
                    <div class="bubble">「我一個人能說的也就到這了」</div>
                    <div class="bubble">「剩下的話——你得自己去聽」</div>
                </div>
            </div>

            <!-- Panel 2: Leroy pushes shoulder to turn -->
            <div class="panel">
                <div class="char-area">
                    <svg width="160" height="120" viewBox="0 0 160 120">
                        <!-- Leroy's hand pushing -->
                        <rect x="10" y="30" width="60" height="50" rx="4" fill="#888" stroke="#333" stroke-width="2"/>
                        <line x1="70" y1="45" x2="110" y2="50" stroke="#333" stroke-width="4" stroke-linecap="round"/>
                        <circle cx="110" cy="50" r="7" fill="#ddd" stroke="#333" stroke-width="2"/>
                        <!-- Push arrow -->
                        <path d="M 115,50 L 140,50" stroke="#e94560" stroke-width="2" stroke-dasharray="5,3" marker-end="url(#arrow)"/>
                        <defs><marker id="arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto"><path d="M 0 0 L 10 5 L 0 10 z" fill="#e94560"/></marker></defs>
                        <!-- Jax shoulder fragment -->
                        <rect x="120" y="35" width="30" height="40" rx="4" fill="#1a1a1a" stroke="#111" stroke-width="2"/>
                        <text x="80" y="95" text-anchor="middle" font-size="9" fill="#999">（推肩轉身）</text>
                    </svg>
                </div>
                <div class="bubble-group">
                    <div class="bubble shout">「回頭看看吧」</div>
                </div>
            </div>

            <!-- Panel 3: Jax's back, trembling, not fully turned -->
            <div class="panel" style="background:#f5f5f5;">
                <div class="char-area">
                    <svg width="160" height="130" viewBox="0 0 160 130">
                        <!-- Jax back view, head slightly turned -->
                        <circle cx="80" cy="40" r="22" fill="#1a1a1a" stroke="#111" stroke-width="2"/>
                        <line x1="68" y1="18" x2="63" y2="0" stroke="#111" stroke-width="3"/>
                        <line x1="92" y1="18" x2="97" y2="0" stroke="#111" stroke-width="3"/>
                        <rect x="62" y="64" width="36" height="50" rx="4" fill="#1a1a1a" stroke="#111" stroke-width="2"/>
                        <!-- Tremble lines -->
                        <line x1="45" y1="55" x2="40" y2="50" stroke="#aaa" stroke-width="1.5"/>
                        <line x1="43" y1="65" x2="38" y2="62" stroke="#aaa" stroke-width="1.5"/>
                        <line x1="115" y1="55" x2="120" y2="50" stroke="#aaa" stroke-width="1.5"/>
                        <line x1="117" y1="65" x2="122" y2="62" stroke="#aaa" stroke-width="1.5"/>
                        <!-- Slight head turn indicator -->
                        <circle cx="95" cy="36" r="3" fill="#555"/>
                        <text x="80" y="125" text-anchor="middle" font-size="8" fill="#999">（背影・微微偏頭・發抖）</text>
                    </svg>
                </div>
                <div class="bubble-group">
                    <div class="bubble">「……」</div>
                    <div class="bubble thought">「他們還在嗎…」</div>
                </div>
            </div>
        </div>
        <div class="direction">演出指示：不要讓他立刻轉過去——恐懼需要停留一拍。顫抖的線條要明顯。</div>
    </div>
</div>

<!-- ========== P35 ========== -->
<div class="page-card">
    <div class="page-header">
        <h2>P35 —「門已經被打破了」</h2>
        <span class="tag">全頁・破門</span>
    </div>
    <div class="manga-page">
        <div class="panels">
            <div class="full-page" style="background: linear-gradient(180deg, #111 0%, #111 35%, #fff9 60%, #ffe8 100%); min-height:550px;">

                <!-- Narration at top -->
                <div class="bubble narration" style="position:absolute; top:20px; right:20px; max-width:260px; font-size:.95em; line-height:1.5;">
                    李羅伊（畫外音）：<br>「你的朋友們啊——」<br>「早就幫你把那扇門打破了唷」
                </div>

                <!-- Scene layout -->
                <svg width="500" height="420" viewBox="0 0 500 420" style="margin-top:30px;">
                    <!-- Dark side (Jax's side) -->
                    <rect x="0" y="0" width="500" height="420" fill="#1a1a1a" rx="4"/>

                    <!-- Broken door frame -->
                    <rect x="150" y="20" width="200" height="350" rx="4" fill="none" stroke="#666" stroke-width="6"/>
                    <!-- Light through door -->
                    <rect x="156" y="26" width="188" height="338" fill="#fff" opacity="0.85"/>
                    <rect x="156" y="26" width="188" height="338" fill="url(#lightGrad)"/>
                    <defs>
                        <linearGradient id="lightGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#fffbe6" stop-opacity="0.9"/>
                            <stop offset="100%" stop-color="#fff" stop-opacity="0.95"/>
                        </linearGradient>
                    </defs>

                    <!-- Door shards scattered -->
                    <rect x="120" y="300" width="35" height="50" rx="2" fill="#999" stroke="#666" stroke-width="2" transform="rotate(-15 137 325)"/>
                    <rect x="340" y="280" width="40" height="30" rx="2" fill="#aaa" stroke="#666" stroke-width="2" transform="rotate(20 360 295)"/>
                    <rect x="100" y="180" width="25" height="45" rx="2" fill="#bbb" stroke="#666" stroke-width="2" transform="rotate(-30 112 202)"/>
                    <rect x="360" y="150" width="30" height="25" rx="2" fill="#aaa" stroke="#666" stroke-width="2" transform="rotate(12 375 162)"/>
                    <rect x="180" y="370" width="45" height="20" rx="2" fill="#999" stroke="#666" stroke-width="2" transform="rotate(-5 202 380)"/>
                    <!-- Punch marks on shards -->
                    <circle cx="137" cy="315" r="4" fill="none" stroke="#e94560" stroke-width="1.5"/>
                    <circle cx="370" cy="290" r="5" fill="none" stroke="#e94560" stroke-width="1.5"/>

                    <!-- EXIT text on a shard (callback to P1-P2) -->
                    <text x="355" y="168" font-size="8" fill="#888" transform="rotate(12 375 162)" font-weight="bold">EXIT</text>

                    <!-- Chain fragments -->
                    <circle cx="148" cy="100" r="8" fill="none" stroke="#555" stroke-width="3"/>
                    <circle cx="160" cy="108" r="8" fill="none" stroke="#555" stroke-width="3"/>
                    <circle cx="345" cy="90" r="8" fill="none" stroke="#555" stroke-width="3"/>

                    <!-- Characters through the door (in the light) -->
                    <!-- Pomni (center front) -->
                    <circle cx="250" cy="150" r="18" fill="#ffe0e0" stroke="#333" stroke-width="2"/>
                    <polygon points="240,132 250,110 260,132" fill="#e94560" stroke="#333" stroke-width="1.5"/>
                    <rect x="235" y="170" width="30" height="38" rx="4" fill="#e94560" stroke="#333" stroke-width="2"/>
                    <line x1="235" y1="180" x2="218" y2="195" stroke="#333" stroke-width="3" stroke-linecap="round"/>
                    <line x1="265" y1="180" x2="282" y2="195" stroke="#333" stroke-width="3" stroke-linecap="round"/>
                    <circle cx="244" cy="148" r="3" fill="#333"/>
                    <circle cx="256" cy="148" r="3" fill="#333"/>
                    <path d="M 243,158 Q 250,163 257,158" stroke="#333" stroke-width="1.5" fill="none"/>
                    <text x="250" y="225" text-anchor="middle" font-size="9" fill="#666">帕姆尼</text>

                    <!-- Rhubarb (left back) -->
                    <circle cx="200" cy="200" r="15" fill="#e0e0ff" stroke="#333" stroke-width="2"/>
                    <rect x="188" y="218" width="24" height="32" rx="4" fill="#6666cc" stroke="#333" stroke-width="2"/>
                    <line x1="188" y1="225" x2="178" y2="218" stroke="#333" stroke-width="2.5" stroke-linecap="round"/>
                    <line x1="212" y1="225" x2="222" y2="218" stroke="#333" stroke-width="2.5" stroke-linecap="round"/>
                    <text x="200" y="265" text-anchor="middle" font-size="8" fill="#666">瑞比特</text>
                    <!-- Arms crossed pose hint -->
                    <line x1="178" y1="218" x2="198" y2="230" stroke="#333" stroke-width="2.5" stroke-linecap="round"/>

                    <!-- Kaufmo (right back) -->
                    <circle cx="300" cy="195" r="16" fill="#fff5e0" stroke="#333" stroke-width="2"/>
                    <rect x="290" y="190" width="20" height="8" rx="2" fill="#f0883e" stroke="#333" stroke-width="1.5"/>
                    <rect x="286" y="214" width="28" height="34" rx="4" fill="#f0883e" stroke="#333" stroke-width="2"/>
                    <line x1="314" y1="222" x2="330" y2="210" stroke="#333" stroke-width="2.5" stroke-linecap="round"/>
                    <text x="300" y="263" text-anchor="middle" font-size="8" fill="#666">卡夫莫</text>
                    <!-- Waving hand -->
                    <circle cx="330" cy="208" r="5" fill="#fff5e0" stroke="#333" stroke-width="1.5"/>

                    <!-- Jax over-shoulder (foreground, dark) -->
                    <circle cx="70" cy="300" r="30" fill="#1a1a1a" stroke="#111" stroke-width="3"/>
                    <line x1="55" y1="270" x2="48" y2="240" stroke="#111" stroke-width="4"/>
                    <line x1="85" y1="270" x2="92" y2="240" stroke="#111" stroke-width="4"/>
                    <rect x="45" y="333" width="50" height="60" rx="4" fill="#1a1a1a" stroke="#111" stroke-width="3"/>
                    <!-- One eye visible, wide -->
                    <circle cx="82" cy="296" r="6" fill="#fff" stroke="#333" stroke-width="1.5"/>
                    <circle cx="83" cy="296" r="3" fill="#333"/>
                    <text x="70" y="410" text-anchor="middle" font-size="9" fill="#888">賈克斯（背影）</text>
                </svg>

                <div class="scene-desc">
                    <strong>構圖重點：</strong><br>
                    ・賈克斯在畫面左下前景（over-shoulder），佔約 1/4 面積，全黑剪影<br>
                    ・正中是被砸碎的門框，碎片散落，鏈條斷裂<br>
                    ・門板碎片上殘留 EXIT 字樣（呼應 P1–P2）<br>
                    ・碎片上有拳痕（呼應 P25）、鋸痕（呼應 P21 金戈電鋸）<br>
                    ・門外是光，三人站在光中<br>
                    ・帕姆尼雙手叉腰、瑞比特抱胸別臉、卡夫莫揮手<br>
                    ・<strong>門內是黑暗，門外是光——賈克斯站在門檻上</strong>
                </div>
            </div>
        </div>
        <div class="direction">唯一台詞是李羅伊的畫外音。其餘全靠視覺。被打碎的門＝出口。呼應 P1–P2 找不到的 EXIT，這次是朋友們直接打出來的。</div>
    </div>
</div>

<!-- ========== P36 ========== -->
<div class="page-card">
    <div class="page-header">
        <h2>P36 —「…吵死了」</h2>
        <span class="tag">崩塌與接受</span>
    </div>
    <div class="manga-page">
        <div class="panels">

            <!-- Panel 1: Eye close-up, shell cracking -->
            <div class="panel narrow" style="background:#eee;">
                <div class="char-area">
                    <svg width="300" height="70" viewBox="0 0 300 70">
                        <!-- Wide eye close-up -->
                        <ellipse cx="150" cy="35" rx="50" ry="28" fill="#1a1a1a" stroke="#111" stroke-width="3"/>
                        <ellipse cx="150" cy="35" rx="22" ry="18" fill="#fff" stroke="#333" stroke-width="2"/>
                        <circle cx="152" cy="34" r="8" fill="#333"/>
                        <circle cx="156" cy="30" r="3" fill="#fff"/>
                        <!-- Crack lines -->
                        <line x1="100" y1="20" x2="115" y2="35" stroke="#fff" stroke-width="2"/>
                        <line x1="115" y1="35" x2="105" y2="50" stroke="#fff" stroke-width="1.5"/>
                        <line x1="115" y1="35" x2="125" y2="28" stroke="#fff" stroke-width="1.5"/>
                        <line x1="195" y1="15" x2="185" y2="30" stroke="#fff" stroke-width="2"/>
                        <line x1="185" y1="30" x2="200" y2="45" stroke="#fff" stroke-width="1.5"/>
                        <!-- Light through cracks -->
                        <line x1="100" y1="20" x2="115" y2="35" stroke="#ffe066" stroke-width="1" opacity="0.6"/>
                        <text x="260" y="40" font-size="8" fill="#999">殼裂開了</text>
                    </svg>
                </div>
            </div>

            <!-- Panel 2: Jax head down, fists trembling -->
            <div class="panel">
                <div class="char-area">
                    <svg width="150" height="140" viewBox="0 0 150 140">
                        <!-- Jax, cracking shell, head down -->
                        <circle cx="75" cy="45" r="24" fill="#1a1a1a" stroke="#111" stroke-width="2"/>
                        <line x1="62" y1="21" x2="57" y2="5" stroke="#111" stroke-width="3"/>
                        <line x1="88" y1="21" x2="93" y2="5" stroke="#111" stroke-width="3"/>
                        <rect x="56" y="72" width="38" height="48" rx="4" fill="#1a1a1a" stroke="#111" stroke-width="2"/>
                        <!-- Cracks with light -->
                        <line x1="60" y1="35" x2="55" y2="50" stroke="#ffe066" stroke-width="2"/>
                        <line x1="90" y1="30" x2="95" y2="48" stroke="#ffe066" stroke-width="2"/>
                        <line x1="70" y1="70" x2="65" y2="85" stroke="#ffe066" stroke-width="1.5"/>
                        <!-- Floating shards upward -->
                        <rect x="40" y="15" width="8" height="6" rx="1" fill="#444" opacity="0.5" transform="rotate(-20 44 18)"/>
                        <rect x="100" y="10" width="6" height="8" rx="1" fill="#444" opacity="0.4" transform="rotate(15 103 14)"/>
                        <rect x="55" y="5" width="5" height="5" rx="1" fill="#555" opacity="0.3"/>
                        <!-- Fists clenched -->
                        <circle cx="45" cy="105" r="8" fill="#1a1a1a" stroke="#111" stroke-width="2"/>
                        <circle cx="105" cy="105" r="8" fill="#1a1a1a" stroke="#111" stroke-width="2"/>
                        <!-- Tremble -->
                        <line x1="30" y1="100" x2="25" y2="97" stroke="#aaa" stroke-width="1"/>
                        <line x1="120" y1="100" x2="125" y2="97" stroke="#aaa" stroke-width="1"/>
                    </svg>
                </div>
                <div class="bubble-group">
                    <div class="bubble">「…吵死了」</div>
                    <div class="bubble">「一群…白痴」</div>
                </div>
            </div>

            <!-- Panel 3: One step forward, Leroy behind smiling -->
            <div class="panel tall" style="background:#f8f8f0;">
                <div class="char-area">
                    <svg width="280" height="200" viewBox="0 0 280 200">
                        <!-- Jax walking forward (one step) -->
                        <circle cx="120" cy="55" r="22" fill="#2a2a2a" stroke="#333" stroke-width="2"/>
                        <line x1="108" y1="33" x2="103" y2="15" stroke="#333" stroke-width="3"/>
                        <line x1="132" y1="33" x2="137" y2="15" stroke="#333" stroke-width="3"/>
                        <rect x="104" y="80" width="32" height="45" rx="4" fill="#2a2a2a" stroke="#333" stroke-width="2"/>
                        <!-- One leg forward -->
                        <line x1="112" y1="125" x2="100" y2="165" stroke="#333" stroke-width="3" stroke-linecap="round"/>
                        <line x1="128" y1="125" x2="135" y2="165" stroke="#333" stroke-width="3" stroke-linecap="round"/>
                        <!-- Floating fragments like petals -->
                        <rect x="80" y="30" width="6" height="5" rx="1" fill="#999" opacity="0.4" transform="rotate(-25 83 32)"/>
                        <rect x="155" y="20" width="7" height="5" rx="1" fill="#888" opacity="0.35" transform="rotate(10 158 22)"/>
                        <rect x="95" y="10" width="5" height="6" rx="1" fill="#aaa" opacity="0.3" transform="rotate(-15 97 13)"/>
                        <rect x="140" y="45" width="5" height="4" rx="1" fill="#999" opacity="0.3"/>

                        <!-- Leroy behind, hands in pockets, watching -->
                        <circle cx="230" cy="65" r="16" fill="#ddd" stroke="#333" stroke-width="2"/>
                        <rect x="218" y="84" width="24" height="35" rx="4" fill="#888" stroke="#333" stroke-width="2"/>
                        <path d="M 243,158 Q 250,163 257,158" stroke="#333" stroke-width="1.5" fill="none" transform="translate(-20,-100)"/>
                        <!-- Smile -->
                        <path d="M 224,70 Q 230,76 236,70" stroke="#333" stroke-width="1.5" fill="none"/>
                        <text x="230" y="135" text-anchor="middle" font-size="8" fill="#999">（微笑目送）</text>

                        <!-- Direction arrow for the step -->
                        <path d="M 120,170 L 120,185" stroke="#e94560" stroke-width="1.5" stroke-dasharray="3,2" marker-end="url(#arrow)"/>
                        <text x="120" y="198" text-anchor="middle" font-size="8" fill="#e94560">一步</text>
                    </svg>
                </div>
                <div class="bubble-group">
                    <div class="bubble narration" style="background:#333;">（無對白。碎片飄散如花瓣。）</div>
                </div>
            </div>

            <!-- Panel 4: Leroy's warning -->
            <div class="panel">
                <div class="char-area">
                    <svg width="120" height="100" viewBox="0 0 120 100">
                        <!-- Leroy smirking -->
                        <circle cx="60" cy="35" r="20" fill="#ddd" stroke="#333" stroke-width="2"/>
                        <rect x="46" y="58" width="28" height="32" rx="4" fill="#888" stroke="#333" stroke-width="2"/>
                        <!-- Smirk -->
                        <circle cx="53" cy="32" r="2.5" fill="#333"/>
                        <circle cx="67" cy="32" r="2.5" fill="#333"/>
                        <path d="M 52,42 Q 60,48 68,40" stroke="#333" stroke-width="1.5" fill="none"/>
                    </svg>
                </div>
                <div class="bubble-group">
                    <div class="bubble">「對了——」</div>
                    <div class="bubble">「你可是害他們吃了不少苦頭」</div>
                    <div class="bubble shout">「你會被痛扁一頓的哦」</div>
                    <div class="bubble">「保重阿」</div>
                </div>
            </div>

        </div>
        <div class="direction">李羅伊的語氣是笑著說的。他太了解自己了——知道欠的帳沒那麼容易一筆勾銷。「保重阿」= 你自求多福吧。</div>
    </div>
</div>

<!-- ========== P37 ========== -->
<div class="page-card">
    <div class="page-header">
        <h2>P37 —「歡迎回來啊混蛋！！！」</h2>
        <span class="tag">定格收尾・EP1 END</span>
    </div>
    <div class="manga-page">
        <div class="panels">

            <!-- Panel 1: Jax's face - fear -->
            <div class="panel narrow" style="background:#f5f5f5;">
                <div class="char-area">
                    <svg width="160" height="70" viewBox="0 0 160 70">
                        <circle cx="80" cy="35" r="28" fill="#2a2a2a" stroke="#111" stroke-width="2"/>
                        <line x1="65" y1="7" x2="60" y2="-10" stroke="#111" stroke-width="3"/>
                        <line x1="95" y1="7" x2="100" y2="-10" stroke="#111" stroke-width="3"/>
                        <!-- Scared eyes -->
                        <ellipse cx="70" cy="30" rx="7" ry="9" fill="#fff" stroke="#333" stroke-width="1.5"/>
                        <circle cx="70" cy="32" r="3" fill="#333"/>
                        <ellipse cx="90" cy="30" rx="7" ry="9" fill="#fff" stroke="#333" stroke-width="1.5"/>
                        <circle cx="90" cy="32" r="3" fill="#333"/>
                        <!-- Sweat -->
                        <ellipse cx="55" cy="25" rx="3" ry="5" fill="#aaddff" opacity="0.7"/>
                        <ellipse cx="108" cy="20" rx="2.5" ry="4" fill="#aaddff" opacity="0.7"/>
                        <!-- Mouth slightly up (hidden smile) -->
                        <path d="M 72,45 Q 80,50 88,45" stroke="#555" stroke-width="1.5" fill="none"/>
                    </svg>
                </div>
                <div class="bubble-group">
                    <div class="bubble shout">「……蛤？」</div>
                </div>
            </div>

            <!-- Panel 2: Everyone's expressions changed -->
            <div class="panel">
                <div class="char-area">
                    <svg width="260" height="120" viewBox="0 0 260 120">
                        <!-- Pomni rolling sleeves -->
                        <circle cx="60" cy="40" r="18" fill="#ffe0e0" stroke="#333" stroke-width="2"/>
                        <polygon points="50,22 60,4 70,22" fill="#e94560" stroke="#333" stroke-width="1.5"/>
                        <rect x="45" y="60" width="30" height="35" rx="4" fill="#e94560" stroke="#333" stroke-width="2"/>
                        <circle cx="54" cy="38" r="3" fill="#333"/>
                        <circle cx="66" cy="38" r="3" fill="#333"/>
                        <path d="M 53,48 Q 60,55 67,48" stroke="#333" stroke-width="2" fill="none"/>
                        <!-- Sleeve rolling motion -->
                        <line x1="75" y1="68" x2="90" y2="60" stroke="#333" stroke-width="2.5" stroke-linecap="round"/>

                        <!-- Rhubarb cracking knuckles -->
                        <circle cx="150" cy="40" r="16" fill="#e0e0ff" stroke="#333" stroke-width="2"/>
                        <rect x="138" y="58" width="24" height="32" rx="4" fill="#6666cc" stroke="#333" stroke-width="2"/>
                        <circle cx="144" cy="38" r="2.5" fill="#333"/>
                        <circle cx="156" cy="38" r="2.5" fill="#333"/>
                        <path d="M 144,48 Q 150,52 156,48" stroke="#333" stroke-width="1.5" fill="none"/>
                        <!-- Fist -->
                        <circle cx="128" cy="70" r="7" fill="#e0e0ff" stroke="#333" stroke-width="2"/>
                        <text x="122" y="85" font-size="7" fill="#999">咔咔</text>

                        <!-- Kaufmo running cheerfully -->
                        <circle cx="230" cy="42" r="15" fill="#fff5e0" stroke="#333" stroke-width="2"/>
                        <rect x="221" y="38" width="18" height="7" rx="2" fill="#f0883e" stroke="#333" stroke-width="1.5"/>
                        <rect x="218" y="60" width="24" height="30" rx="4" fill="#f0883e" stroke="#333" stroke-width="2"/>
                        <circle cx="225" cy="43" r="2" fill="#333"/>
                        <circle cx="235" cy="43" r="2" fill="#333"/>
                        <path d="M 225,51 Q 230,56 235,51" stroke="#333" stroke-width="1.5" fill="none"/>
                        <!-- Raised fist -->
                        <line x1="242" y1="65" x2="255" y2="50" stroke="#333" stroke-width="2.5" stroke-linecap="round"/>
                        <circle cx="255" cy="48" r="6" fill="#fff5e0" stroke="#333" stroke-width="1.5"/>
                    </svg>
                </div>
                <div class="bubble-group">
                    <div class="bubble">帕姆尼：「賈・克・斯——♪」</div>
                    <div class="bubble">瑞比特：「你知道我等這一刻多久了嗎」</div>
                    <div class="bubble" style="font-size:.85em">卡夫莫：「歡迎回來啊朋友！先吃我一拳！」</div>
                </div>
            </div>

            <!-- Panel 3: FREEZE FRAME - everyone flying at Jax -->
            <div class="panel hero freeze" style="background: linear-gradient(180deg, #fff 0%, #f0f0f0 100%);">
                <div style="width:100%; text-align:center;">
                    <svg width="480" height="320" viewBox="0 0 480 320">
                        <!-- Speed lines radiating from center -->
                        <g opacity="0.15">
                            <line x1="240" y1="160" x2="0" y2="0" stroke="#111" stroke-width="1"/>
                            <line x1="240" y1="160" x2="480" y2="0" stroke="#111" stroke-width="1"/>
                            <line x1="240" y1="160" x2="0" y2="320" stroke="#111" stroke-width="1"/>
                            <line x1="240" y1="160" x2="480" y2="320" stroke="#111" stroke-width="1"/>
                            <line x1="240" y1="160" x2="0" y2="160" stroke="#111" stroke-width="1"/>
                            <line x1="240" y1="160" x2="480" y2="160" stroke="#111" stroke-width="1"/>
                            <line x1="240" y1="160" x2="120" y2="0" stroke="#111" stroke-width="1"/>
                            <line x1="240" y1="160" x2="360" y2="0" stroke="#111" stroke-width="1"/>
                            <line x1="240" y1="160" x2="120" y2="320" stroke="#111" stroke-width="1"/>
                            <line x1="240" y1="160" x2="360" y2="320" stroke="#111" stroke-width="1"/>
                            <line x1="240" y1="160" x2="240" y2="0" stroke="#111" stroke-width="1"/>
                            <line x1="240" y1="160" x2="240" y2="320" stroke="#111" stroke-width="1"/>
                        </g>

                        <!-- Jax at bottom center, leaning back in terror -->
                        <g transform="translate(220, 230)">
                            <circle cx="20" cy="20" r="24" fill="#2a2a2a" stroke="#111" stroke-width="2"/>
                            <line x1="8" y1="-4" x2="3" y2="-22" stroke="#111" stroke-width="3"/>
                            <line x1="32" y1="-4" x2="37" y2="-22" stroke="#111" stroke-width="3"/>
                            <rect x="3" y="46" width="34" height="42" rx="4" fill="#2a2a2a" stroke="#111" stroke-width="2"/>
                            <!-- Terrified but smiling eyes -->
                            <ellipse cx="12" cy="16" rx="6" ry="8" fill="#fff" stroke="#333" stroke-width="1.5"/>
                            <circle cx="12" cy="18" r="3" fill="#333"/>
                            <ellipse cx="28" cy="16" rx="6" ry="8" fill="#fff" stroke="#333" stroke-width="1.5"/>
                            <circle cx="28" cy="18" r="3" fill="#333"/>
                            <!-- THE KEY: mouth is smiling even though scared -->
                            <path d="M 10,32 Q 20,40 30,32" stroke="#333" stroke-width="2" fill="none"/>
                            <!-- Sweat -->
                            <ellipse cx="-5" cy="10" rx="3" ry="5" fill="#aaddff" opacity="0.6"/>
                            <ellipse cx="46" cy="5" rx="2.5" ry="4.5" fill="#aaddff" opacity="0.6"/>
                            <!-- Hands up defensive -->
                            <line x1="3" y1="55" x2="-15" y2="35" stroke="#333" stroke-width="3" stroke-linecap="round"/>
                            <line x1="37" y1="55" x2="55" y2="35" stroke="#333" stroke-width="3" stroke-linecap="round"/>
                        </g>

                        <!-- Pomni flying kick from top-left -->
                        <g transform="translate(60, 30)">
                            <circle cx="40" cy="30" r="20" fill="#ffe0e0" stroke="#333" stroke-width="2.5"/>
                            <polygon points="30,12 40,-8 50,12" fill="#e94560" stroke="#333" stroke-width="2"/>
                            <rect x="25" y="52" width="30" height="35" rx="4" fill="#e94560" stroke="#333" stroke-width="2.5"/>
                            <!-- Big grin -->
                            <circle cx="33" cy="28" r="3" fill="#333"/>
                            <circle cx="47" cy="28" r="3" fill="#333"/>
                            <path d="M 30,38 Q 40,50 50,38" stroke="#333" stroke-width="2" fill="none"/>
                            <!-- Fist forward -->
                            <line x1="55" y1="60" x2="85" y2="80" stroke="#333" stroke-width="3.5" stroke-linecap="round"/>
                            <circle cx="87" cy="82" r="9" fill="#ffe0e0" stroke="#333" stroke-width="2"/>
                            <!-- Flying kick leg -->
                            <line x1="35" y1="87" x2="60" y2="110" stroke="#333" stroke-width="3" stroke-linecap="round"/>
                        </g>

                        <!-- Rhubarb charging from top-right -->
                        <g transform="translate(320, 20)">
                            <circle cx="40" cy="35" r="18" fill="#e0e0ff" stroke="#333" stroke-width="2.5"/>
                            <rect x="27" y="55" width="26" height="33" rx="4" fill="#6666cc" stroke="#333" stroke-width="2.5"/>
                            <!-- Fierce grin -->
                            <circle cx="33" cy="32" r="3" fill="#333"/>
                            <circle cx="47" cy="32" r="3" fill="#333"/>
                            <path d="M 32,43 Q 40,52 48,43" stroke="#333" stroke-width="2" fill="none"/>
                            <!-- Both fists -->
                            <line x1="27" y1="62" x2="8" y2="80" stroke="#333" stroke-width="3.5" stroke-linecap="round"/>
                            <circle cx="6" cy="82" r="8" fill="#e0e0ff" stroke="#333" stroke-width="2"/>
                            <line x1="53" y1="62" x2="72" y2="75" stroke="#333" stroke-width="3.5" stroke-linecap="round"/>
                            <circle cx="74" cy="77" r="8" fill="#e0e0ff" stroke="#333" stroke-width="2"/>
                        </g>

                        <!-- Kaufmo rushing from right, arms wide -->
                        <g transform="translate(370, 120)">
                            <circle cx="35" cy="25" r="17" fill="#fff5e0" stroke="#333" stroke-width="2.5"/>
                            <rect x="26" y="20" width="18" height="8" rx="2" fill="#f0883e" stroke="#333" stroke-width="1.5"/>
                            <rect x="22" y="44" width="26" height="32" rx="4" fill="#f0883e" stroke="#333" stroke-width="2.5"/>
                            <!-- Happy face -->
                            <circle cx="29" cy="26" r="2.5" fill="#333"/>
                            <circle cx="41" cy="26" r="2.5" fill="#333"/>
                            <path d="M 28,34 Q 35,42 42,34" stroke="#333" stroke-width="2" fill="none"/>
                            <!-- Arms wide open (hug or punch? who knows!) -->
                            <line x1="22" y1="52" x2="-5" y2="40" stroke="#333" stroke-width="3.5" stroke-linecap="round"/>
                            <line x1="48" y1="52" x2="70" y2="38" stroke="#333" stroke-width="3.5" stroke-linecap="round"/>
                            <circle cx="-7" cy="39" r="7" fill="#fff5e0" stroke="#333" stroke-width="1.5"/>
                            <circle cx="72" cy="37" r="7" fill="#fff5e0" stroke="#333" stroke-width="1.5"/>
                            <text x="35" y="90" text-anchor="middle" font-size="7" fill="#999">（擁抱？揍人？）</text>
                        </g>

                        <!-- Impact effects -->
                        <text x="130" y="150" font-size="28" font-weight="900" fill="#e94560" opacity="0.15" transform="rotate(-10 130 150)">BAM</text>
                        <text x="350" y="180" font-size="22" font-weight="900" fill="#e94560" opacity="0.12" transform="rotate(8 350 180)">WELCOME BACK</text>
                    </svg>

                    <div class="bubble huge" style="margin:10px auto; display:inline-block;">「等等等等等——！！！」</div>

                    <p style="color:#999; font-size:.8em; margin-top:10px;">
                        隱藏細節：賈克斯的嘴角是<strong style="color:#e94560">上揚的</strong>。<br>
                        這是他整部漫畫中第一個真正的笑容。
                    </p>
                </div>
            </div>

            <!-- END mark -->
            <div class="end-mark">EP1. Restart — END</div>
        </div>
    </div>
</div>

<div style="text-align:center; padding: 30px 0; color: #555; font-size: .85em;">
    <p>分鏡草稿 — 僅供參考構圖與台詞配置</p>
    <p>角色以簡易幾何圖形表示（圓＝頭、方＝身體）</p>
    <p style="margin-top:12px;"><a href="AI_reading.php" style="color:#58a6ff; text-decoration:none;">← 回到完整分析報告</a></p>
</div>

</div>
</body>
</html>
