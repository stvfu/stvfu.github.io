<?php
require __DIR__ . '/auth.php';

if (!is_logged_in()) {
    header('Location: index.php');
    exit;
}

$csrf = $_SESSION['csrf'];
$msg = '';
$msgType = '';
$editRow = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok($_POST['csrf'] ?? null)) {
    $act = $_POST['act'] ?? '';
    $db = get_db();

    if ($act === 'delete' && !empty($_POST['id'])) {
        $stmt = $db->prepare("DELETE FROM sleep_log WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
        $msg = '已刪除紀錄 #' . (int)$_POST['id'];
        $msgType = 'ok';
    } elseif ($act === 'add') {
        $dt = trim($_POST['datetime'] ?? '');
        $action = trim($_POST['action'] ?? '');
        if ($dt && in_array($action, ['躺床','睡眠','賴床','清醒'], true)) {
            $recorded = date('Ymd_His', strtotime($dt));
            if ($recorded && $recorded !== '19700101_080000') {
                $stmt = $db->prepare("INSERT INTO sleep_log (recorded_at, action) VALUES (?, ?)");
                $stmt->execute([$recorded, $action]);
                $msg = '已新增紀錄';
                $msgType = 'ok';
            } else {
                $msg = '日期格式錯誤';
                $msgType = 'err';
            }
        } else {
            $msg = '請填寫完整資料';
            $msgType = 'err';
        }
    } elseif ($act === 'edit' && !empty($_POST['id'])) {
        $dt = trim($_POST['datetime'] ?? '');
        $action = trim($_POST['action'] ?? '');
        if ($dt && in_array($action, ['躺床','睡眠','賴床','清醒'], true)) {
            $recorded = date('Ymd_His', strtotime($dt));
            if ($recorded && $recorded !== '19700101_080000') {
                $stmt = $db->prepare("UPDATE sleep_log SET recorded_at = ?, action = ? WHERE id = ?");
                $stmt->execute([$recorded, $action, (int)$_POST['id']]);
                $msg = '已更新紀錄 #' . (int)$_POST['id'];
                $msgType = 'ok';
            } else {
                $msg = '日期格式錯誤';
                $msgType = 'err';
            }
        } else {
            $msg = '請填寫完整資料';
            $msgType = 'err';
        }
    }

    header('Location: admin.php' . ($msg ? '?msg=' . urlencode($msg) . '&t=' . $msgType : ''));
    exit;
}

if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
    $msgType = $_GET['t'] ?? 'ok';
}

if (isset($_GET['edit'])) {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM sleep_log WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editRow = $stmt->fetch(PDO::FETCH_ASSOC);
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$db = get_db();
$total = (int)$db->query("SELECT COUNT(*) FROM sleep_log")->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));
$rows = $db->query("SELECT * FROM sleep_log ORDER BY recorded_at DESC, id DESC LIMIT $perPage OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);

function fmt_dt(string $raw): string {
    $y = substr($raw,0,4); $m = substr($raw,4,2); $d = substr($raw,6,2);
    $h = substr($raw,9,2); $i = substr($raw,11,2); $s = substr($raw,13,2);
    return "$y-$m-$d $h:$i:$s";
}

function to_input_dt(string $raw): string {
    $y = substr($raw,0,4); $m = substr($raw,4,2); $d = substr($raw,6,2);
    $h = substr($raw,9,2); $i = substr($raw,11,2);
    return "$y-$m-{$d}T$h:$i";
}

$actionColors = ['躺床' => '#27ae60', '睡眠' => '#8e44ad', '賴床' => '#e6a817', '清醒' => '#3498db'];
$actionIcons  = ['躺床' => '&#128716;', '睡眠' => '&#128164;', '賴床' => '&#128564;', '清醒' => '&#9728;'];
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>睡眠紀錄管理</title>
<style>
:root {
    --bg: #111; --panel: #1a1a1a; --border: #333; --text: #eee; --muted: #888;
    --accent: #c96442; --accent-text: #fff; --error: #e74c3c; --ok: #27ae60;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: -apple-system, "Segoe UI", "Noto Sans TC", "Microsoft JhengHei", sans-serif;
    background: var(--bg); color: var(--text); padding: 16px;
}
a { color: var(--accent); text-decoration: none; }
a:hover { text-decoration: underline; }

.top-bar { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; margin-bottom: 20px; }
.top-bar h2 { margin: 0; font-size: 1.3rem; }

.msg { padding: 10px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 0.95rem; }
.msg-ok { background: #1a3a2a; border: 1px solid var(--ok); color: #6fcf97; }
.msg-err { background: #3a1a1a; border: 1px solid var(--error); color: #ff6b6b; }

.add-form {
    background: var(--panel); border: 1px solid var(--border); border-radius: 12px;
    padding: 16px 20px; margin-bottom: 20px;
}
.add-form h3 { font-size: 1rem; margin-bottom: 12px; color: var(--muted); }
.form-row { display: flex; gap: 10px; align-items: end; flex-wrap: wrap; }
.form-group { display: flex; flex-direction: column; gap: 4px; }
.form-group label { font-size: 0.85rem; color: var(--muted); }
.form-group input, .form-group select {
    background: var(--bg); border: 1px solid var(--border); border-radius: 6px;
    padding: 8px 10px; color: var(--text); font: inherit; font-size: 0.95rem;
}
.form-group input:focus, .form-group select:focus { outline: 2px solid var(--accent); outline-offset: 1px; }

.btn {
    padding: 8px 18px; border: none; border-radius: 6px; cursor: pointer;
    font: inherit; font-size: 0.9rem; color: #fff;
}
.btn-add { background: var(--ok); }
.btn-save { background: var(--accent); }
.btn-cancel { background: var(--muted); }
.btn-edit { background: none; border: 1px solid var(--border); color: var(--muted); padding: 4px 12px; font-size: 0.85rem; border-radius: 4px; cursor: pointer; }
.btn-del { background: none; border: 1px solid #5a2020; color: #e74c3c; padding: 4px 12px; font-size: 0.85rem; border-radius: 4px; cursor: pointer; }
.btn-edit:hover { border-color: var(--muted); color: var(--text); }
.btn-del:hover { background: #3a1a1a; }

.edit-form {
    background: #1a2a1a; border: 1px solid var(--ok); border-radius: 12px;
    padding: 16px 20px; margin-bottom: 20px;
}
.edit-form h3 { font-size: 1rem; margin-bottom: 12px; color: #6fcf97; }

table { width: 100%; border-collapse: collapse; }
th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid var(--border); }
th { background: var(--panel); color: var(--muted); font-size: 0.85rem; font-weight: 600; position: sticky; top: 0; }
td { font-size: 0.95rem; }
tr:hover td { background: #1a1a2a; }
td.mono { font-family: monospace; font-size: 0.9rem; }
.action-tag {
    display: inline-block; padding: 2px 10px; border-radius: 4px; font-size: 0.85rem; font-weight: 600;
}
.td-actions { white-space: nowrap; }
.td-actions form { display: inline; }

.pager { display: flex; gap: 8px; justify-content: center; margin-top: 20px; flex-wrap: wrap; }
.pager a, .pager span {
    display: inline-block; padding: 6px 14px; border-radius: 6px; font-size: 0.9rem;
    border: 1px solid var(--border);
}
.pager span { background: var(--accent); color: #fff; border-color: var(--accent); }
.pager a { color: var(--muted); }
.pager a:hover { border-color: var(--muted); color: var(--text); text-decoration: none; }
.total { text-align: center; color: var(--muted); font-size: 0.85rem; margin-top: 10px; }

@media (max-width: 600px) {
    .form-row { flex-direction: column; }
    .form-group { width: 100%; }
    .form-group input, .form-group select { width: 100%; }
    th, td { padding: 8px 6px; font-size: 0.85rem; }
    .btn { width: 100%; margin-top: 8px; }
}
</style>
</head>
<body>

<div class="top-bar">
    <h2>睡眠紀錄管理</h2>
    <a href="index.php">返回首頁</a>
    <a href="sleep_view.php">視覺化</a>
</div>

<?php if ($msg): ?>
<div class="msg msg-<?= h($msgType) ?>"><?= h($msg) ?></div>
<?php endif; ?>

<?php if ($editRow): ?>
<div class="edit-form">
    <h3>編輯紀錄 #<?= (int)$editRow['id'] ?></h3>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="act" value="edit">
        <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>">
        <div class="form-row">
            <div class="form-group">
                <label>時間</label>
                <input type="datetime-local" name="datetime" value="<?= h(to_input_dt($editRow['recorded_at'])) ?>" required>
            </div>
            <div class="form-group">
                <label>狀態</label>
                <select name="action" required>
                    <?php foreach (['躺床','睡眠','賴床','清醒'] as $a): ?>
                    <option value="<?= h($a) ?>" <?= $editRow['action'] === $a ? 'selected' : '' ?>><?= h($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="justify-content:end">
                <div style="display:flex;gap:8px">
                    <button class="btn btn-save" type="submit">儲存</button>
                    <a class="btn btn-cancel" href="admin.php<?= $page > 1 ? '?page='.$page : '' ?>">取消</a>
                </div>
            </div>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="add-form">
    <h3>新增紀錄</h3>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="act" value="add">
        <div class="form-row">
            <div class="form-group">
                <label>時間</label>
                <input type="datetime-local" name="datetime" required>
            </div>
            <div class="form-group">
                <label>狀態</label>
                <select name="action" required>
                    <option value="躺床">躺床</option>
                    <option value="睡眠">睡眠</option>
                    <option value="賴床">賴床</option>
                    <option value="清醒">清醒</option>
                </select>
            </div>
            <div class="form-group" style="justify-content:end">
                <button class="btn btn-add" type="submit">新增</button>
            </div>
        </div>
    </form>
</div>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>時間</th>
            <th>狀態</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
        <tr>
            <td class="mono"><?= (int)$row['id'] ?></td>
            <td class="mono"><?= h(fmt_dt($row['recorded_at'])) ?></td>
            <td>
                <span class="action-tag" style="background:<?= h($actionColors[$row['action']] ?? '#555') ?>">
                    <?= $actionIcons[$row['action']] ?? '' ?> <?= h($row['action']) ?>
                </span>
            </td>
            <td class="td-actions">
                <a class="btn-edit" href="?edit=<?= (int)$row['id'] ?>&page=<?= $page ?>">編輯</a>
                <form method="post" onsubmit="return confirm('確定刪除 #<?= (int)$row['id'] ?> 的紀錄？')">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <input type="hidden" name="act" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                    <button class="btn-del" type="submit">刪除</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php if ($totalPages > 1): ?>
<div class="pager">
    <?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>">&laquo; 上一頁</a><?php endif; ?>
    <?php
    $start = max(1, $page - 3);
    $end = min($totalPages, $page + 3);
    for ($i = $start; $i <= $end; $i++):
        if ($i === $page): ?>
            <span><?= $i ?></span>
        <?php else: ?>
            <a href="?page=<?= $i ?>"><?= $i ?></a>
        <?php endif;
    endfor; ?>
    <?php if ($page < $totalPages): ?><a href="?page=<?= $page+1 ?>">下一頁 &raquo;</a><?php endif; ?>
</div>
<?php endif; ?>
<div class="total">共 <?= $total ?> 筆紀錄，第 <?= $page ?>/<?= $totalPages ?> 頁</div>

</body>
</html>
