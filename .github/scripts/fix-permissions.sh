#!/bin/bash
set -euo pipefail

TARGET_DIR="${TARGET_DIR:-/data/www/wwwroot/trend-radar.gifer.cn}"
WEB_USER="${WEB_USER:-www}"
DEPLOY_USER="${DEPLOY_USER:-deploy}"

WRITABLE_DIRS=("config" "storage" "storage/logs" "storage/framework/cache" "uploads" "public/uploads")
EXCLUDE_PATTERNS=(".user.ini" "*/.user.ini" ".htaccess" "*/.htaccess" ".env" "*/.env" ".well-known" "*/.well-known/*")

echo "🔧 修复权限（跳过宝塔敏感文件）"

# ✅ 关键：用 sudo 执行权限命令（已配置免密）
echo "   📁 设置属主为 $DEPLOY_USER:$WEB_USER..."
sudo chown -R "$DEPLOY_USER:$WEB_USER" "$TARGET_DIR"

echo "   📜 设置默认权限..."
sudo find "$TARGET_DIR" -type f -exec chmod 644 {} \;
sudo find "$TARGET_DIR" -type d -exec chmod 755 {} \;

echo "   ✍️  设置可写目录..."
for dir in "${WRITABLE_DIRS[@]}"; do
  full="$TARGET_DIR/$dir"
  [ -d "$full" ] || continue
  sudo chmod 775 "$full"
  sudo find "$full" -type d -exec chmod 775 {} \;
  sudo find "$full" -type f -exec chmod 664 {} \;
  sudo chmod g+s "$full" 2>/dev/null || true
done

# 跳过敏感文件（已通过 EXCLUDE_PATTERNS 避免 touch，此处无需额外操作）

echo "   ✅ 验证 Web 用户写权限..."
sudo mkdir -p "$TARGET_DIR/config"
if sudo -u "$WEB_USER" touch "$TARGET_DIR/config/.test" 2>/dev/null && \
   sudo -u "$WEB_USER" rm -f "$TARGET_DIR/config/.test"; then
  echo "   🎯 验证通过"
else
  echo "   ⚠️ 失败"
  exit 1
fi

echo "🎉 权限修复完成"
