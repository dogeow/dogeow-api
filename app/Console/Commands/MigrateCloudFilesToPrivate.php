<?php

namespace App\Console\Commands;

use App\Models\Cloud\File;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MigrateCloudFilesToPrivate extends Command
{
    protected $signature = 'cloud:migrate-private {--apply : 复制校验后移除公开副本；默认仅预演} {--user= : 仅处理指定用户}';

    protected $description = '将历史云盘文件从 public 迁到 cloud 私有盘，保持文件 ID、路径和签名 URL 兼容';

    public function handle(): int
    {
        $userId = $this->option('user');
        if ($userId !== null && (! ctype_digit((string) $userId) || (int) $userId < 1)) {
            $this->error('用户 ID 必须是正整数');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $lock = $apply ? Cache::lock('cloud:migrate-private', 3600) : null;
        if ($lock && ! $lock->get()) {
            $this->error('已有私有盘迁移任务正在运行');

            return self::FAILURE;
        }

        $counts = ['planned' => 0, 'migrated' => 0, 'private' => 0, 'failed' => 0];
        try {
            $public = Storage::disk('public');
            $private = Storage::disk('cloud');
            File::query()->where('is_folder', false)
                ->when($userId !== null, fn ($query) => $query->where('user_id', (int) $userId))
                ->chunkById(100, function ($files) use ($apply, $public, $private, &$counts) {
                    foreach ($files as $file) {
                        try {
                            $path = (string) $file->path;
                            $this->validatePath($path);
                            if (! $this->fileExists($public, $path)) {
                                if (! $this->fileExists($private, $path)) {
                                    throw new RuntimeException('文件在公开盘和私有盘均不存在');
                                }
                                $counts['private']++;

                                continue;
                            }
                            $this->migrateFile($public, $private, $path, $apply);
                            $counts[$apply ? 'migrated' : 'planned']++;
                        } catch (Throwable $exception) {
                            $counts['failed']++;
                            $this->error("文件 #{$file->id} 迁移失败：{$exception->getMessage()}");
                        }
                    }
                });
        } finally {
            $lock?->release();
        }

        $this->info(($apply ? '迁移结果' : '预演结果') . sprintf(
            '：待迁移 %d，已迁移 %d，已私有 %d，失败 %d',
            $counts['planned'], $counts['migrated'], $counts['private'], $counts['failed']
        ));

        return $counts['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * 文件系统可被其他进程修改，每次检查都必须重新读取状态。
     *
     * @phpstan-impure
     */
    private function fileExists(Filesystem $disk, string $path): bool
    {
        return $disk->exists($path);
    }

    private function validatePath(string $path): void
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\')
            || str_contains($path, "\0") || preg_match('~(^|/)\.\.(/|$)|^[a-z][a-z0-9+.-]*:~i', $path)) {
            throw new RuntimeException('文件路径无效');
        }
    }

    private function migrateFile(Filesystem $public, Filesystem $private, string $path, bool $apply): void
    {
        $expected = $this->checksum($public, $path);
        if ($this->fileExists($private, $path) && ! hash_equals($expected, $this->checksum($private, $path))) {
            throw new RuntimeException('私有盘存在不同内容，未覆盖或删除任何副本');
        }
        if (! $apply) {
            return;
        }

        $temporary = '.migration/' . Str::uuid();
        try {
            if (! $this->fileExists($private, $path)) {
                $stream = $public->readStream($path);
                if (! is_resource($stream)) {
                    throw new RuntimeException('无法读取公开文件');
                }
                try {
                    if (! $private->writeStream($temporary, $stream)) {
                        throw new RuntimeException('写入私有盘失败');
                    }
                } finally {
                    fclose($stream);
                }
                if (! hash_equals($expected, $this->checksum($private, $temporary))) {
                    throw new RuntimeException('私有副本校验失败，公开原件已保留');
                }
                // 上传路径由 UUID 生成，应用不覆盖文件内容；迁移也不覆盖已有私有文件。
                if (! $this->fileExists($private, $path) && ! $private->move($temporary, $path)) {
                    throw new RuntimeException('私有副本归档失败');
                }
            }
            if (! hash_equals($expected, $this->checksum($public, $path))
                || ! hash_equals($expected, $this->checksum($private, $path))) {
                throw new RuntimeException('文件在迁移期间发生变化，公开原件已保留');
            }
            if (! $public->delete($path)) {
                throw new RuntimeException('公开副本删除失败；可重新运行安全重试');
            }
        } finally {
            $private->delete($temporary);
        }
    }

    private function checksum(Filesystem $disk, string $path): string
    {
        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            throw new RuntimeException('无法校验文件');
        }
        try {
            $hash = hash_init('sha256');
            $bytesRead = hash_update_stream($hash, $stream);
            if ($bytesRead !== $disk->size($path)) {
                throw new RuntimeException('文件校验读取不完整');
            }

            return hash_final($hash);
        } finally {
            fclose($stream);
        }
    }
}
