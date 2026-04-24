#!/bin/bash
# 首次部署初始化脚本：准备 Deployer 所需 shared 目录，并触发第一次 release 部署。
set -euo pipefail

AUTO_DETECTED_WORKSPACE_ROOT=0

if [ -z "${WORKSPACE_ROOT:-}" ]; then
  WORKSPACE_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
  AUTO_DETECTED_WORKSPACE_ROOT=1
fi

DEPLOY_PATH="${DEPLOY_PATH:-/example/dogeow-api}"
SHARED_DIR="${DEPLOY_PATH}/shared"
SHARED_ENV_FILE="${SHARED_DIR}/.env"
RELEASES_DIR="${DEPLOY_PATH}/releases"
CURRENT_LINK="${DEPLOY_PATH}/current"
WORKSPACE_ENV_FILE="${WORKSPACE_ROOT}/.env"
LOCAL_ENV_FILE="${LOCAL_ENV_FILE:-}"
DEPLOYER_COMMAND=()

log() {
  echo "[first-deploy] $*"
}

die() {
  echo "错误：$*" >&2
  exit 1
}

require_command() {
  if ! command -v "$1" >/dev/null 2>&1; then
    die "缺少命令：$1"
  fi
}

ensure_directory() {
  if [ -d "$1" ]; then
    return
  fi

  mkdir -p "$1"
}

copy_shared_env_file() {
  local source_env_file="$LOCAL_ENV_FILE"

  if [ -z "$source_env_file" ] && [ -f "$SHARED_ENV_FILE" ]; then
    return
  fi

  if [ -z "$source_env_file" ]; then
    source_env_file="$WORKSPACE_ENV_FILE"
  fi

  if [ ! -f "$source_env_file" ]; then
    die "共享配置不存在，且未找到可复制的源文件：$source_env_file"
  fi

  cp -f "$source_env_file" "$SHARED_ENV_FILE"
  chmod 640 "$SHARED_ENV_FILE"
}

prepare_shared_directories() {
  ensure_directory "$SHARED_DIR"
  ensure_directory "$RELEASES_DIR"
  ensure_directory "$SHARED_DIR/storage"
  ensure_directory "$SHARED_DIR/storage/app"
  ensure_directory "$SHARED_DIR/storage/app/public"
  ensure_directory "$SHARED_DIR/storage/framework"
  ensure_directory "$SHARED_DIR/storage/framework/cache"
  ensure_directory "$SHARED_DIR/storage/framework/sessions"
  ensure_directory "$SHARED_DIR/storage/framework/views"
  ensure_directory "$SHARED_DIR/storage/logs"
}

resolve_deployer_command() {
  local dep_bin="${DEP_BIN:-}"

  if [ -n "$dep_bin" ]; then
    if [ -f "$dep_bin" ]; then
      if [[ "$dep_bin" == *.phar ]]; then
        DEPLOYER_COMMAND=(php "$dep_bin")
      else
        DEPLOYER_COMMAND=("$dep_bin")
      fi
      return
    fi

    if command -v "$dep_bin" >/dev/null 2>&1; then
      DEPLOYER_COMMAND=("$dep_bin")
      return
    fi

    die "DEP_BIN 不可用：$dep_bin"
  fi

  if [ -f "$HOME/.deployer/dep.phar" ]; then
    DEPLOYER_COMMAND=(php "$HOME/.deployer/dep.phar")
    return
  fi

  if command -v dep >/dev/null 2>&1; then
    DEPLOYER_COMMAND=(dep)
    return
  fi

  die "未找到 Deployer。请先安装 dep，或通过 DEP_BIN 指定可执行文件 / phar 路径"
}

run_first_deploy() {
  export DEPLOY_PATH

  if [ -n "${SUPERVISOR_GROUP:-}" ]; then
    export SUPERVISOR_GROUP
  fi

  cd "$WORKSPACE_ROOT"
  "${DEPLOYER_COMMAND[@]}" deploy production -v
}

require_command php
require_command chmod
require_command cp
require_command mkdir

if [ "$AUTO_DETECTED_WORKSPACE_ROOT" -eq 1 ]; then
  log "自动识别 WORKSPACE_ROOT：$WORKSPACE_ROOT"
fi

if [ ! -f "$WORKSPACE_ROOT/deploy.php" ]; then
  die "WORKSPACE_ROOT 下未找到 deploy.php：$WORKSPACE_ROOT"
fi

if [ -e "$CURRENT_LINK" ] || [ -L "$CURRENT_LINK" ]; then
  die "检测到 current 已存在，首次部署似乎已经完成；后续更新请直接使用 dep deploy production"
fi

if [ -d "$RELEASES_DIR" ] && find "$RELEASES_DIR" -mindepth 1 -maxdepth 1 -type d -exec basename {} \; | grep -Eq '^[0-9]{14}$'; then
  die "检测到已有 release 目录，首次部署脚本只适用于未创建过 release 的部署根目录"
fi

prepare_shared_directories
copy_shared_env_file

if [ ! -f "$SHARED_ENV_FILE" ]; then
  die "缺少共享配置文件：$SHARED_ENV_FILE；请确认工作树根目录存在 .env，或设置 LOCAL_ENV_FILE=/path/to/.env"
fi

resolve_deployer_command

log "部署根目录：$DEPLOY_PATH"
log "共享配置文件：$SHARED_ENV_FILE"
log "使用 Deployer 执行首次发布"
run_first_deploy

log "首次部署完成"
log "后续更新请直接执行：dep deploy production"