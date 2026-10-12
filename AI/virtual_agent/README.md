# Virtual Agent — AI 對話介面

多模型 AI 聊天網頁，支援 Claude 訂閱模式與 API 模式。

## 功能

- **多模型切換**：Claude、GPT、Grok、Gemini（後三者待啟用）
- **動態主題色**：切換模型時自動套用該品牌配色
- **對話歷史**：左側 sidebar 保存所有對話，支援新增 / 切換 / 刪除
- **管理員模式**：開啟後 Claude 可透過工具操作 server 檔案
- **訊息互動選單**：長按（手機）或右鍵（桌面）訊息，可複製內容、重新送出自己的發言、或重新產生 AI 回應
- **登入保護**：帳號密碼 + CSRF 防護

## 檔案結構

| 檔案 | 說明 |
|------|------|
| `index.php` | 前端頁面（HTML / CSS / JS） |
| `auth.php` | 共用設定：模型清單、session、登入驗證 |
| `api.php` | 對話端點：串流轉發 Claude 回應 |
| `claude_cli.php` | 訂閱模式：呼叫 server 上的 claude 指令 |
| `conversations_api.php` | 對話紀錄 CRUD API（SQLite） |

## 設定

敏感設定檔位於 web root 外：

```
/var/www/data/claude/config.php      ← 主設定（API key、OAuth token、密碼）
/var/www/data/claude/config.example.php  ← 範本
/var/www/data/claude/chat.db         ← 對話紀錄資料庫（SQLite）
```

複製 `config.example.php` 為 `config.php` 後填入設定即可使用。
