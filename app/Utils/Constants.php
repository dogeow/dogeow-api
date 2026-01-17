<?php

namespace App\Utils;

class Constants
{
    /**
     * 获取分页配置
     */
    public static function pagination(?string $key = null): mixed
    {
        $config = config('app_constants.pagination');

        return $key ? ($config[$key] ?? null) : $config;
    }

    /**
     * 获取聊天配置
     */
    public static function chat(?string $section = null, ?string $key = null): mixed
    {
        $config = config('app_constants.chat');

        if ($section && $key) {
            return $config[$section][$key] ?? null;
        }

        if ($section) {
            return $config[$section] ?? null;
        }

        return $config;
    }

    /**
     * 获取文件上传配置
     */
    public static function upload(?string $key = null): mixed
    {
        $config = config('app_constants.upload');

        return $key ? ($config[$key] ?? null) : $config;
    }

    /**
     * 获取图片处理配置
     */
    public static function image(?string $key = null): mixed
    {
        $config = config('app_constants.image');

        return $key ? ($config[$key] ?? null) : $config;
    }

    /**
     * 获取缓存配置
     */
    public static function cache(?string $key = null): mixed
    {
        $config = config('app_constants.cache');

        return $key ? ($config[$key] ?? null) : $config;
    }

    /**
     * 获取验证配置
     */
    public static function validation(?string $section = null, ?string $key = null): mixed
    {
        $config = config('app_constants.validation');

        if ($section && $key) {
            return $config[$section][$key] ?? null;
        }

        if ($section) {
            return $config[$section] ?? null;
        }

        return $config;
    }

    /**
     * 获取API配置
     */
    public static function api(?string $key = null): mixed
    {
        $config = config('app_constants.api');

        return $key ? ($config[$key] ?? null) : $config;
    }

    // 快捷方法
    public static function chatMessageMaxLength(): int
    {
        return self::chat('message', 'max_length');
    }

    public static function chatRoomNameMaxLength(): int
    {
        return self::chat('room', 'name_max_length');
    }

    public static function defaultPerPage(): int
    {
        return self::pagination('default_per_page');
    }

    public static function maxPerPage(): int
    {
        return self::pagination('max_per_page');
    }

    public static function maxFileSize(): int
    {
        return self::upload('max_file_size');
    }

    public static function allowedExtensions(): array
    {
        return self::upload('allowed_extensions');
    }

    public static function thumbnailSize(): int
    {
        return self::image('thumbnail_size');
    }

    public static function compressedMaxSize(): int
    {
        return self::image('compressed_max_size');
    }
}
