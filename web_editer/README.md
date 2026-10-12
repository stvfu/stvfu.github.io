# Web Editor

瀏覽器線上程式碼編輯器，基於 CodeMirror，整合自 web_editer (v1)、web_editer2、web_editer3。

## 功能

- 語法高亮（自動偵測語言模式）
- 檔案瀏覽器，支援目錄導航
- 新增、載入、儲存檔案
- Tab 轉空格、移除行尾空白
- 未儲存變更偵測
- CSRF 防護
- Ctrl+S 快速儲存

### 檔案管理

整合原 file_manager 功能：

- **新增資料夾**：工具列 Mkdir 按鈕
- **刪除檔案/資料夾**：工具列 Delete 按鈕（刪除目前開啟的檔案）
- **重新命名**：透過互動選單操作
- **電腦**：在檔案瀏覽器中右鍵點擊項目，彈出操作選單（Open / Rename / Delete）
- **手機**：長按項目約 0.4 秒，從底部滑出操作面板

### 懷舊模式

點擊工具列的 Classic 按鈕，一鍵切換回 web_editer2 的經典風格（白色背景、固定寬度佈局）。偏好設定儲存在 localStorage。

## 檔案結構

| 檔案 | 說明 |
|------|------|
| `index.php` | 主編輯器介面（含懷舊模式切換） |
| `api.php` | 後端 API（load / save / list / mkdir / delete / rename） |
| `common.php` | 共用設定、Session、CSRF、路徑工具 |
| `login.php` | 登入頁面 |
| `logout.php` | 登出處理 |

## 認證

帳號密碼定義在 `/var/www/admin_credentials.php`，所有 PHP 檔案皆透過此檔取得帳密，程式碼中不包含明文密碼。
