# 睡眠紀錄系統

睡眠狀態打卡工具，記錄躺床、睡眠、賴床、清醒四種狀態的切換時間。

## 伺服器資訊

- **Base URL**: `https://stvfu.com/page/sleep/`
- **資料庫**: SQLite (`/var/www/database/sleep.db`)，表 `sleep_log`
- **認證設定**: `/var/www/data/claude/config.php`

## 頁面

| 檔案 | 用途 |
|------|------|
| `index.php` | 前台打卡介面（session 登入） |
| `sleep_view.php` | 視覺化時間軸（響應式，支援手機） |
| `admin.php` | 後台管理：列出 / 新增 / 編輯 / 刪除紀錄 |
| `api.php` | 內部用 API，回傳所有紀錄（需 session） |
| `api_action.php` | **App 用 API**（Token 認證，見下方） |
| `auth.php` | 共用認證 / DB 工具（不可直接訪問） |

## App API（api_action.php）

所有請求皆為 **POST**，Content-Type 為 `application/json`。

### 認證

使用 Bearer Token，放在 `Authorization` header：

```
Authorization: Bearer 803a37587c1bc89d3c57cd368f9ccf52792153df746097033256c49e346af91c
```

### 1. 打卡 — checkin

記錄一筆狀態。

**Request:**
```json
{
  "action": "checkin",
  "type": "bed",
  "at": "2025-07-01 03:30:00"
}
```

| 欄位 | 必填 | 說明 |
|------|------|------|
| `action` | 是 | 固定 `"checkin"` |
| `type` | 是 | `bed`（躺床）、`sleep`（睡眠）、`lazy`（賴床）、`wake`（清醒） |
| `at` | 否 | 自訂時間（PHP `strtotime` 可解析的格式），省略則用伺服器當前時間 |

**Response (200):**
```json
{
  "ok": true,
  "id": 123,
  "recorded_at": "20250701_033000",
  "action": "躺床"
}
```

### 2. 查詢目前狀態 — status

取得最後一筆紀錄。

**Request:**
```json
{
  "action": "status"
}
```

**Response (200):**
```json
{
  "action": "睡眠",
  "action_key": "sleep",
  "recorded_at": "20250701_033000"
}
```

若無紀錄，`action` 和 `recorded_at` 為 `null`。

### 3. 歷史紀錄 — history

取得最近的紀錄列表。

**Request:**
```json
{
  "action": "history",
  "limit": 20
}
```

| 欄位 | 必填 | 說明 |
|------|------|------|
| `limit` | 否 | 回傳筆數，1–100，預設 20 |

**Response (200):**
```json
{
  "records": [
    { "id": 123, "recorded_at": "20250701_033000", "action": "躺床" },
    { "id": 122, "recorded_at": "20250630_132320", "action": "清醒" }
  ]
}
```

### 錯誤回應

| HTTP 狀態碼 | 說明 |
|-------------|------|
| 400 | 參數錯誤（無效的 type 或 action） |
| 401 | Token 錯誤或缺失 |
| 405 | 非 POST 請求 |

格式：
```json
{ "error": "error message" }
```

### cURL 範例

```bash
# 打卡（當前時間）
curl -X POST https://stvfu.com/page/sleep/api_action.php \
  -H "Authorization: Bearer 803a37587c1bc89d3c57cd368f9ccf52792153df746097033256c49e346af91c" \
  -H "Content-Type: application/json" \
  -d '{"action":"checkin","type":"bed"}'

# 補填（指定時間）
curl -X POST https://stvfu.com/page/sleep/api_action.php \
  -H "Authorization: Bearer 803a37587c1bc89d3c57cd368f9ccf52792153df746097033256c49e346af91c" \
  -H "Content-Type: application/json" \
  -d '{"action":"checkin","type":"sleep","at":"2025-07-01 04:00:00"}'

# 查詢狀態
curl -X POST https://stvfu.com/page/sleep/api_action.php \
  -H "Authorization: Bearer 803a37587c1bc89d3c57cd368f9ccf52792153df746097033256c49e346af91c" \
  -H "Content-Type: application/json" \
  -d '{"action":"status"}'

# 歷史紀錄
curl -X POST https://stvfu.com/page/sleep/api_action.php \
  -H "Authorization: Bearer 803a37587c1bc89d3c57cd368f9ccf52792153df746097033256c49e346af91c" \
  -H "Content-Type: application/json" \
  -d '{"action":"history","limit":10}'
```

## 狀態流程

```
清醒 ──→ 躺床 ──→ 睡眠 ──→ 賴床 ──→ 清醒
  │               ↑   │               ↑
  └──→ 睡眠 ──────┘   └──→ 清醒 ──────┘
           賴床 ──→ 睡眠
```

網頁前台會根據當前狀態限制可用按鈕，API 端不做限制，由 App 自行處理。

## 資料庫結構

```sql
CREATE TABLE sleep_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    recorded_at TEXT NOT NULL,  -- 格式: 20250701_033000
    action TEXT NOT NULL        -- 躺床 / 睡眠 / 賴床 / 清醒
);
```
