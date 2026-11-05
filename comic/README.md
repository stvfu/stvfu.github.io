# 忽田各漫畫作品集

同人誌社團「猛少女之拳」作家 **忽田各** 的個人漫畫作品集平台。  
提供線上閱讀、後台管理，以及 Android App 推播通知，讓讀者即時掌握最新更新。

## 功能總覽

### 前台網頁（閱讀端）
- 作品列表首頁，支援搜尋篩選
- 作品詳情頁：封面、簡介、狀態、分類頁籤、章節列表（可排序）
- 閱讀器：捲動模式 / 單頁模式、左翻 / 右翻切換、鍵盤與觸控手勢翻頁
- 閱讀進度自動記憶（localStorage）
- 章節留言板（SQLite）、閱覽次數統計
- 章節間上下話導航、頁面滑桿快速跳頁

### 後台管理（Admin）
- 帳號密碼登入驗證
- 作品 CRUD：新增、編輯資訊、刪除、拖曳排序
- 章節管理：新增 / 刪除 / 重命名、分類設定、超連結欄位
- 頁面管理：拖曳上傳圖片、拖曳排序、批次選取刪除、設為封面
- 章節封面上傳、縮圖自動產生

### Android App（推播通知）
- 基於 Firebase Cloud Messaging (FCM) 的推播通知
- 裝置 Token 自動註冊
- 更新通知發佈後台（Server 端）
- 支援「更新」與「公告」兩種推播分類

## 技術架構

| 層級 | 技術 |
|------|------|
| 前端 | PHP + 原生 HTML/CSS/JavaScript |
| 後端 | PHP（Apache）、SQLite |
| 資料儲存 | JSON 檔（`data/manga.json`）+ SQLite（留言/閱覽數/推播） |
| App | Android (Kotlin)、Firebase Cloud Messaging |
| 推播 Server | PHP + Firebase Admin SDK |
| 部署環境 | AWS EC2 + Apache + Let's Encrypt SSL |

## 專案結構

```
comic/
├── index.php              # 首頁（作品列表）
├── manga.php              # 作品詳情頁
├── reader.php             # 閱讀器
├── comments.php           # 留言 / 閱覽數 API
├── config.php             # 網站設定
├── assets/
│   └── style.css          # 前台樣式
├── includes/
│   └── helpers.php        # 共用函式
├── data/
│   └── manga.json         # 作品資料（由後台管理，git ignored）
├── admin/
│   └── index.php          # 漫畫後台管理面板
├── works/                 # 作品資料夾（每部作品一個子目錄）
│   ├── re_tadc/
│   ├── kimetsu_no_ken/
│   ├── kinniku_friends/
│   └── ...
├── mobile/
│   ├── android/           # Android App 原始碼（Kotlin + FCM）
│   │   └── comic_notify/
│   ├── server/            # 推播通知 Server
│   │   ├── api/
│   │   │   ├── db.php           # SQLite 資料庫初始化
│   │   │   ├── fcm_send.php     # FCM 推播發送
│   │   │   ├── register_token.php  # 裝置 Token 註冊
│   │   │   └── updates.php      # 更新內容 API
│   │   ├── admin/
│   │   │   └── index.php        # 推播管理後台
│   │   ├── config.php.example   # 設定範本
│   │   └── key/                 # Firebase 金鑰（git ignored）
│   └── download/          # APK 下載
└── ref_images/            # 參考素材
```

## 環境需求

- PHP 8.0+
- Apache（啟用 `mod_rewrite`）
- SQLite3 + GD extension
- Firebase 專案（用於推播）
- Android Studio（編譯 App）

## 安裝與設定

### 1. 網站端

```bash
# 確保 data/ 和 works/ 目錄可寫入
chmod 755 data/ works/
chown www-data:www-data data/ works/

# 管理員帳密設定於 /var/www/admin_credentials.php（不納入版控）
# 需定義 ADMIN_USER 和 ADMIN_PASS 常數
```

### 2. 推播 Server

```bash
cd mobile/server
cp config.php.example config.php
# 編輯 config.php，填入：
#   - DATA_ROOT 路徑
#   - ADMIN_PASS
#   - FIREBASE_KEY_PATH（Firebase Admin SDK 金鑰路徑）
#   - FIREBASE_PROJECT_ID
```

### 3. Android App

1. 用 Android Studio 開啟 `mobile/android/comic_notify/`
2. 設定 `google-services.json`（從 Firebase Console 下載）
3. Build & Run

## 收錄作品

| 作品 | 目錄 |
|------|------|
| RE:TADC | `works/re_tadc/` |
| 鬼滅之拳 | `works/kimetsu_no_ken/` |
| 筋肉朋友 | `works/kinniku_friends/` |
| 肌肉檸檬 | `works/musclemon/` |
| 寶可夢肌肉GO | `works/poke_muscle_go/` |
| 暗殺拳教室 | `works/assassination_ken_classroom/` |
| 我推的孩子 | `works/woshinoko/` |
| 飛天小男警 | `works/the_powerpuff_man/` |

## 授權

本專案為個人創作用途，所有漫畫內容版權歸作者忽田各所有。
