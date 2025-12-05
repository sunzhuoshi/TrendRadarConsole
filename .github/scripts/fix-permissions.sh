#!/bin/bash
# fix-permissions.sh (v2: 跳过宝塔敏感文件)
set -euo pipefail

TARGET_DIR="${TARGET_DIR:-/data/www/wwwroot/trend-radar.gifer.cn}"
WEB_USER="${WEB_USER:-www}"
DEPLOY_USER="${DEPLOY_USER:-deploy}"

# ✅ 可写目录（仅业务需要）
WRITABLE_DIRS=(
  "config"
  "storage"
  "storage/logs"
  "storage/framework/cache"
  "storage/framework/sessions"
  "storage/framework/views"
  "uploads"
  "public/uploads"
)

# ✅ 显式排除宝塔保护文件/目录（不 touch 它们）
# Note: Using -path patterns with wildcards to match files at any depth
# Each pattern is duplicated: one for root level, one for subdirectories
EXCLUDE_PATTERNS=(
  ".user.ini"
  "*/.user.ini"
  ".htaccess"
  "*/.htaccess"
  ".well-known"
  "*/.well-known"
  ".well-known/*"
  "*/.well-known/*"
  ".env"
  "*/.env"
  ".env.*"
  "*/.env.*"
  "index.html"        # 宝塔默认页
  "*/index.html"
  "404.html"
  "*/404.html"
  "phpinfo.php"
  "*/phpinfo.php"
)

echo "🔧 修复权限（跳过宝塔敏感文件）: $TARGET_DIR"

# 1. 确保 deploy 在 www 组
if ! groups "$DEPLOY_USER" 2>/dev/null | grep -q "\b$WEB_USER\b"; then
  echo "   ➕ 将 $DEPLOY_USER 加入 $WEB_USER 组..."
  sudo usermod -aG "$WEB_USER" "$DEPLOY_USER"
fi

# 2. 修复属主：仅针对非敏感文件
echo "   📁 设置属主为 $DEPLOY_USER:$WEB_USER（跳过敏感项）..."

# 构建 find 排除语句 (using -path for pattern matching at any depth)
EXCLUDE_ARGS=()
for pat in "${EXCLUDE_PATTERNS[@]}"; do
  EXCLUDE_ARGS+=(-not -path "$pat")
done

# 递归修复非敏感文件/目录属主
sudo find "$TARGET_DIR" -type d \( "${EXCLUDE_ARGS[@]}" \) -exec chown "$DEPLOY_USER:$WEB_USER" {} \;
sudo find "$TARGET_DIR" -type f \( "${EXCLUDE_ARGS[@]}" \) -exec chown "$DEPLOY_USER:$WEB_USER" {} \;

# 3. 默认权限：非敏感文件只读
echo "   📜 设置非敏感文件为只读..."
sudo find "$TARGET_DIR" -type f \( "${EXCLUDE_ARGS[@]}" \) -exec chmod 644 {} \;
sudo find "$TARGET_DIR" -type d \( "${EXCLUDE_ARGS[@]}" \) -exec chmod 755 {} \;

# 4. 可写目录设为 775/664（仍排除敏感子项）
echo "   ✍️  设置可写目录权限..."
for rel_dir in "${WRITABLE_DIRS[@]}"; do
  abs_dir="$TARGET_DIR/$rel_dir"
  if [ -d "$abs_dir" ]; then
    echo "     → $rel_dir"
    sudo chmod 775 "$abs_dir"
    # 仅修复该目录下非敏感文件
    sudo find "$abs_dir" -type d \( "${EXCLUDE_ARGS[@]}" \) -exec chmod 775 {} \;
    sudo find "$abs_dir" -type f \( "${EXCLUDE_ARGS[@]}" \) -exec chmod 664 {} \;
    sudo chmod g+s "$abs_dir" 2>/dev/null || true
  fi
done

# 5. 验证（仍用 config/ 测试，它不属于排除项）
echo "   ✅ 验证 Web 用户写权限..."
sudo mkdir -p "$TARGET_DIR/config"
test_file="$TARGET_DIR/config/.perm_test"
if sudo -u "$WEB_USER" touch "$test_file" 2>/dev/null && sudo -u "$WEB_USER" rm -f "$test_file"; then
  echo "   🎯 验证通过"
else
  echo "   ⚠️ 失败：检查 config/ 是否被排除或权限异常"
  exit 1
fi

echo "🎉 权限修复完成（敏感文件 untouched）"
