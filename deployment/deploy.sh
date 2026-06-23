#!/bin/bash

# CRM后端部署脚本
# 使用方法：./deploy.sh [压缩包路径] [目标目录]

set -e  # 遇到错误立即退出

# 配置参数
DEPLOY_PACKAGE="${1:-$(ls -t crm_backend_*.tar.gz | head -1)}"
TARGET_DIR="${2:-/var/www/crm}"
BACKUP_DIR="/var/backups/crm"
WEB_USER="www-data"
WEB_GROUP="www-data"

# 颜色输出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 日志函数
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# 检查参数
if [ ! -f "$DEPLOY_PACKAGE" ]; then
    log_error "部署包不存在: $DEPLOY_PACKAGE"
    exit 1
fi

# 创建备份目录
mkdir -p "$BACKUP_DIR"

# 生成备份时间戳
BACKUP_TIME=$(date +"%Y%m%d_%H%M%S")

log_info "开始部署 CRM 后端..."
log_info "部署包: $DEPLOY_PACKAGE"
log_info "目标目录: $TARGET_DIR"
log_info "备份时间: $BACKUP_TIME"

# 检查目标目录是否存在
if [ -d "$TARGET_DIR" ]; then
    log_info "备份当前版本..."
    cp -r "$TARGET_DIR" "${BACKUP_DIR}/crm_backup_${BACKUP_TIME}"
    log_info "备份完成: ${BACKUP_DIR}/crm_backup_${BACKUP_TIME}"
else
    log_warn "目标目录不存在，将创建新目录"
    mkdir -p "$TARGET_DIR"
fi

# 解压部署包
log_info "解压部署包..."
TEMP_DEPLOY_DIR="/tmp/crm_deploy_${BACKUP_TIME}"
mkdir -p "$TEMP_DEPLOY_DIR"
tar -xzf "$DEPLOY_PACKAGE" -C "$TEMP_DEPLOY_DIR"

# 部署文件
log_info "部署文件..."
if [ -d "${TEMP_DEPLOY_DIR}/crm_php-master" ]; then
    # 清空目标目录（保留必要配置）
    if [ -f "${TARGET_DIR}/config/database.php" ]; then
        cp "${TARGET_DIR}/config/database.php" "/tmp/database_config_${BACKUP_TIME}.php"
    fi
    
    rm -rf "${TARGET_DIR:?}"/*
    cp -r "${TEMP_DEPLOY_DIR}/crm_php-master"/* "$TARGET_DIR/"
    
    # 恢复配置文件
    if [ -f "/tmp/database_config_${BACKUP_TIME}.php" ]; then
        cp "/tmp/database_config_${BACKUP_TIME}.php" "${TARGET_DIR}/config/database.php"
        rm "/tmp/database_config_${BACKUP_TIME}.php"
    fi
else
    log_error "部署包格式错误"
    exit 1
fi

# 设置文件权限
log_info "设置文件权限..."
chown -R "$WEB_USER:$WEB_GROUP" "$TARGET_DIR"
chmod -R 755 "$TARGET_DIR"
chmod -R 777 "$TARGET_DIR/runtime"
chmod -R 777 "$TARGET_DIR/public"

# 清理临时目录
rm -rf "$TEMP_DEPLOY_DIR"

# 重启相关服务
log_info "重启相关服务..."
systemctl restart nginx
systemctl restart php-fpm

# 检查服务状态
log_info "检查服务状态..."
systemctl status nginx --no-pager
systemctl status php-fpm --no-pager

log_info "✅ 部署完成！"
log_info "如需回滚，请运行: ./rollback.sh ${BACKUP_TIME}"

# 显示版本信息
if [ -f "$TARGET_DIR/VERSION.txt" ]; then
    log_info "部署版本信息："
    cat "$TARGET_DIR/VERSION.txt"
fi