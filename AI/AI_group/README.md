# AI 居酒屋 — 多角色 AI 群聊

多個 AI 角色在同一個聊天室裡對話，每個角色可設定不同的 LLM Provider、人設與性格。

## 功能

- **多 Provider 支援**：Claude / OpenAI / Grok / Gemini，每個角色可獨立選擇模型
- **角色自訂**：名稱、性別、背景、性格、代表色，最多 6 個角色
- **使用者身分**：可自訂你的名字與背景設定，不限於預設的「店長」
- **場景設定**：自訂聊天情境描述，影響所有 AI 角色的回應風格
- **三種模式**：💬 自由聊天 / ⚔️ 辯論 / 📖 故事接龍
- **串流回覆**：所有 Provider 皆即時串流輸出
- **對話紀錄**：SQLite 儲存，可切換 / 刪除歷史對話
- **角色預設組合**：儲存 / 載入不同的角色配置
- **訊息操作**：右鍵（桌面）或長按（手機）任一訊息，可複製、編輯、重新送出 / 重新產生、刪除至底

## 架構

```
AI_group/
├── index.php              # 前端 SPA（登入、聊天 UI、設定面板）
├── api.php                # 後端 API（組裝 prompt、串流呼叫各家 LLM）
├── conversations_api.php  # 對話紀錄 & 預設組合 CRUD（SQLite）
└── README.md
```

## 運作流程

1. 使用者送出一則訊息
2. 後端將所有角色隨機排序，依序呼叫各家 API
3. 每個角色可看到同一輪中前面角色的回覆（能接話、吐槽）
4. 全部角色回完後等待使用者下一則訊息

## 設定

API 金鑰與登入資訊存放於 `/var/www/data/claude/config.php`，需包含：

- `username` / `password_hash` — 登入驗證
- `anthropic_api_key` — Claude
- `openai_api_key` — OpenAI
- `xai_api_key` — Grok
- `google_api_key` — Gemini

對話資料庫自動建立於 `/var/www/data/claude/aigroup.db`（SQLite）。
