#!/bin/bash
# 從 comicpunch_app repo 同步最新程式碼到 /var/www/html/comic/
# 用法: bash sync_comicpunch_repo_to_comic.sh

set -e

REPO="git@github.com:stvfu/comicpunch_app.git"
TARGET="/var/www/html/comic"
TMP_DIR=$(mktemp -d)

echo "=== 同步 comicpunch_app → $TARGET ==="

echo "[1/3] 下載最新版本..."
git clone --depth 1 "$REPO" "$TMP_DIR"

echo "[2/3] 同步檔案（排除 .git、data/）..."
rsync -av --delete \
    --exclude '.git' \
    --exclude 'data/' \
    --exclude 'mobile/download/' \
    "$TMP_DIR/" "$TARGET/"

echo "[3/3] 清理暫存..."
rm -rf "$TMP_DIR"

echo "=== 同步完成 ==="
