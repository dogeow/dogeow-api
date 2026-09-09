<?php

namespace App\Console\Commands;

use App\Services\UpyunService;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class UploadAnnakareninaBooksCommand extends Command
{
    protected $signature = 'books:upload-annakarenina
                            {--source= : 本地书籍目录，默认 ../dogeow/public/books/annakarenina}
                            {--prefix=books/annakarenina : 又拍云上的路径前缀}';

    protected $description = '将安娜·卡列尼娜分章 TXT 与索引上传到又拍云';

    public function handle(UpyunService $upyun): int
    {
        if (! $upyun->isConfigured()) {
            $this->error('又拍云未配置。请在 .env 中设置 UPYUN_BUCKET、UPYUN_OPERATOR、UPYUN_PASSWORD');

            return self::FAILURE;
        }

        $source = (string) ($this->option('source') ?: base_path('../dogeow/public/books/annakarenina'));
        $source = realpath($source) ?: $source;
        $prefix = trim((string) $this->option('prefix'), '/');

        if (! is_dir($source)) {
            $this->error("源目录不存在: {$source}");
            $this->comment('请先在 dogeow 目录运行: npm run preprocess:annakarenina');

            return self::FAILURE;
        }

        $files = $this->collectFiles($source);
        if ($files === []) {
            $this->error("目录内没有可上传的 TXT/JSON 文件: {$source}");

            return self::FAILURE;
        }

        $this->info('上传 ' . count($files) . " 个文件到又拍云 /{$prefix}/ ...");

        $uploaded = 0;
        foreach ($files as $absolutePath => $relativePath) {
            $remotePath = $prefix . '/' . str_replace('\\', '/', $relativePath);
            $this->line("  {$relativePath} -> /{$remotePath}");

            $result = $upyun->upload($absolutePath, $remotePath, $this->mimeType($relativePath));
            if (! $result['success']) {
                $this->error($result['message'] ?? "上传失败: {$relativePath}");

                return self::FAILURE;
            }

            $uploaded++;
        }

        $sampleUrl = $upyun->buildPublicUrl('/' . $prefix . '/index.json');
        $this->info("上传完成，共 {$uploaded} 个文件。");
        $this->line('索引 URL: ' . $sampleUrl);

        return self::SUCCESS;
    }

    /**
     * @return array<string, string> absolute path => relative path
     */
    private function collectFiles(string $source): array
    {
        $source = rtrim($source, DIRECTORY_SEPARATOR);
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $extension = strtolower($file->getExtension());
            if (! in_array($extension, ['txt', 'json'], true)) {
                continue;
            }

            $absolutePath = $file->getRealPath() ?: $file->getPathname();
            $relativePath = ltrim(str_replace($source, '', $absolutePath), DIRECTORY_SEPARATOR);
            $files[$absolutePath] = $relativePath;
        }

        ksort($files);

        return $files;
    }

    private function mimeType(string $relativePath): string
    {
        return str_ends_with(strtolower($relativePath), '.json')
            ? 'application/json'
            : 'text/plain; charset=utf-8';
    }
}
