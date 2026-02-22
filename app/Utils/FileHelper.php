<?php

namespace App\Utils;

class FileHelper
{
    /**
     * 将字节格式化为可读格式
     */
    public static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);

        if ($bytes === 0) {
            return '0 B';
        }

        $pow = floor(log($bytes) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[(int) $pow];
    }

    /**
     * 获取文件大小（字节）
     */
    public static function getFileSize(string $filePath): int|false
    {
        return file_exists($filePath) ? filesize($filePath) : false;
    }

    /**
     * 获取可读的文件大小
     */
    public static function getFormattedFileSize(string $filePath): string
    {
        $size = self::getFileSize($filePath);

        return $size !== false ? self::formatBytes($size) : '0 B';
    }

    /**
     * 检查文件是否可读且非空
     */
    public static function isValidFile(string $filePath): bool
    {
        return file_exists($filePath) && is_readable($filePath) && filesize($filePath) > 0;
    }

    /**
     * 确保目录存在，不存在则创建
     */
    public static function ensureDirectoryExists(string $directoryPath, int $permissions = 0755): bool
    {
        if (is_dir($directoryPath)) {
            return true;
        }

        return mkdir($directoryPath, $permissions, true);
    }
}
