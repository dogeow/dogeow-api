<?php

namespace App\Services\Web;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebPageService
{
    public function fetchContent(string $url): array
    {
        // 确保 URL 包含协议前缀
        $url = $this->normalizeUrl($url);

        $response = Http::timeout(5)->get($url);

        if (! $response->ok()) {
            throw new \RuntimeException('获取网页失败: ' . $response->status());
        }

        $html = $response->body();

        return [
            'title' => $this->extractTitle($html),
            'favicon' => $this->extractFavicon($html, $url),
        ];
    }

    private function extractTitle(string $html): string
    {
        preg_match('/<title>(.*?)<\/title>/is', $html, $matches);

        return $matches[1] ?? '';
    }

    private function extractFavicon(string $html, string $url): string
    {
        // 尝试从 HTML 中提取 favicon
        if (preg_match('/<link[^>]+rel=[\'\"]?(?:shortcut )?icon[\'\"]?[^>]*>/i', $html, $iconTag)) {
            if (preg_match('/href=[\'\"]([^\'\"]+)[\'\"]/i', $iconTag[0], $hrefMatch)) {
                return $this->normalizeFaviconUrl($hrefMatch[1], $url);
            }
        }

        // 如果没有找到，返回默认的 favicon.ico 路径
        $parsed = parse_url($url);

        return $parsed['scheme'] . '://' . $parsed['host'] . '/favicon.ico';
    }

    private function normalizeUrl(string $url): string
    {
        // 检查是否已经包含协议前缀
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        // 如果没有协议前缀，默认添加 https://
        return 'https://' . $url;
    }

    private function normalizeFaviconUrl(string $favicon, string $baseUrl): string
    {
        if (preg_match('/^https?:\/\//i', $favicon)) {
            return $favicon;
        }

        $parsed = parse_url($baseUrl);
        $origin = $parsed['scheme'] . '://' . $parsed['host'];

        // 处理协议相对 URL (以 // 开头)
        if (Str::startsWith($favicon, '//')) {
            return $parsed['scheme'] . ':' . $favicon;
        }

        // 处理绝对路径 (以 / 开头)
        if (Str::startsWith($favicon, '/')) {
            return $origin . $favicon;
        }

        // 处理相对路径
        return rtrim($origin . dirname($parsed['path']), '/') . '/' . $favicon;
    }
}
