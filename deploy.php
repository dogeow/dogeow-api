<?php

/**
 * Deployer 部署配置（GitHub Actions self-hosted runner 使用）
 *
 * 设计要点：
 * - self-hosted runner 就在目标服务器上，host 用 localhost() 走本地 shell，无需 SSH。
 * - deploy_path / supervisor_group 通过环境变量注入，避免把机器路径写死进仓库。
 * - Laravel recipe 已覆盖 shared/writable/symlink/migrate/optimize，本文件只补 Supervisor 重启。
 *
 * 本地使用：
 *   DEPLOY_PATH=/example/dogeow-api SUPERVISOR_GROUP=laravel-horizon \
 *     vendor/bin/dep deploy production
 *
 * 回滚：
 *   vendor/bin/dep rollback production
 */

namespace Deployer;

require 'recipe/laravel.php';

// =====================
// 基本配置
// =====================
set('application', 'dogeow-api');
set('repository', 'git@github.com:react-laravel/dogeow-api.git');
set('keep_releases', 5);
set('git_tty', false); // CI 环境没有 TTY

// 跨版本共享文件 / 目录（升级不会丢）
add('shared_files', ['.env']);
add('shared_dirs', ['storage']);

// Web server / worker 需要可写的目录
add('writable_dirs', [
    'bootstrap/cache',
    'storage',
    'storage/app',
    'storage/app/public',
    'storage/framework',
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
]);

// =====================
// Hosts
// =====================
localhost('production')
    ->set('deploy_path', getenv('DEPLOY_PATH') ?: '/example/dogeow-api')
    ->set('supervisor_group', getenv('SUPERVISOR_GROUP') ?: 'laravel-horizon');

// =====================
// 自定义任务
// =====================
desc('重启 Supervisor 下的 queue worker / Horizon');
task('supervisor:restart', function () {
    run('sudo supervisorctl restart {{supervisor_group}}:*');
    run('sudo supervisorctl status {{supervisor_group}}:*');
});

// =====================
// Hooks
// =====================
// 部署失败自动解锁，避免下次运行报 "Deploy is locked"
after('deploy:failed', 'deploy:unlock');

// current 切换之后再重启 worker：
//   queue:restart 让跑中的 job 做完就退出，Supervisor 拉起新进程时加载新代码。
after('deploy:symlink', 'artisan:queue:restart');
after('artisan:queue:restart', 'supervisor:restart');
