# Live2D 專案管理系統

Live2D 委託案件的紀錄與管理工具。

## 架構

```
index.php                 # 公開瀏覽頁（主入口）
case_editer.php           # 登入後的編輯頁（新增/修改/刪除）
api.php                   # CRUD API + 匯出功能
page.css                  # 共用樣式
live2D_project_list.html  # GitHub 靜態瀏覽頁（fallback）
live2D_case.txt           # JSON 快照（供 GitHub 離線瀏覽）
/var/www/database/live2D.db  # SQLite 資料庫（不進 repo）
```

## API

所有資料操作透過 `api.php` 統一處理：

| action | 方法 | 說明 | 需登入 |
|--------|------|------|--------|
| `list` | GET | 取得全部專案 | 否 |
| `get` | GET | 取得單筆（`?id=`） | 否 |
| `create` | POST | 新增專案 | 是 |
| `update` | POST | 修改專案 | 是 |
| `delete` | POST | 刪除專案 | 是 |
| `export` | POST | 匯出 DB 至 `live2D_case.txt` | 是 |

## 使用流程

1. 進入 `case_editer.php` 登入
2. 新增 / 編輯 / 刪除案例
3. 推 git 前按「匯出至 live2D_case.txt」同步靜態快照

## GitHub Pages fallback

`live2D_project_list.html` 在無 PHP 環境下會自動 fallback 讀取 `live2D_case.txt`，確保 GitHub 上也能瀏覽資料。
