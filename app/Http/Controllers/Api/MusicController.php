<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class MusicController extends Controller
{
    /**
     * 获取所有可用的音乐列表
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $musicDir = public_path('musics');

        if (! File::exists($musicDir)) {
            return response()->json(['error' => '音乐目录不存在'], 404);
        }

        $allowedExtensions = ['mp3', 'ogg', 'wav', 'flac'];
        $files = File::files($musicDir);

        $musicList = collect($files)
            ->filter(function ($file) use ($allowedExtensions) {
                return in_array($file->getExtension(), $allowedExtensions, true);
            })
            ->map(function ($file) {
                $extension = $file->getExtension();

                return [
                    'name' => pathinfo($file->getFilename(), PATHINFO_FILENAME),
                    'path' => '/musics/' . $file->getFilename(),
                    'size' => $file->getSize(),
                    'extension' => $extension,
                ];
            })
            ->values()
            ->toArray();

        return response()->json($musicList);
    }

    /**
     * 下载音乐文件，确保正确的MIME类型和响应头
     */
    public function download(string $filename): Response|\Illuminate\Http\JsonResponse
    {
        $filePath = public_path('musics/' . $filename);

        if (! File::exists($filePath)) {
            return response()->json(['error' => '文件不存在'], 404);
        }

        $mimeType = $this->getMimeType($filePath);
        $fileSize = File::size($filePath);

        // 设置正确的响应头
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => $fileSize,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=3600',
            'Content-Disposition' => 'inline; filename="' . addslashes($filename) . '"',
        ];

        // 支持范围请求（用于音频流式播放）
        $rangeHeader = request()->header('Range');
        if ($rangeHeader) {
            if (preg_match('/bytes=(\d*)-(\d*)/', $rangeHeader, $matches)) {
                $start = $matches[1] === '' ? 0 : (int) $matches[1];
                $end = ($matches[2] !== '') ? (int) $matches[2] : ($fileSize - 1);

                // 保证范围合法
                $start = max(0, min($start, $fileSize - 1));
                $end = max($start, min($end, $fileSize - 1));
                $length = $end - $start + 1;

                $headers['Content-Range'] = "bytes $start-$end/$fileSize";
                $headers['Content-Length'] = $length;

                return response()->stream(
                    function () use ($filePath, $start, $length) {
                        $handle = fopen($filePath, 'rb');
                        if ($handle === false) {
                            return;
                        }
                        fseek($handle, $start);
                        $bufferSize = 8192;
                        $remaining = $length;
                        while ($remaining > 0 && ! feof($handle)) {
                            $read = min($bufferSize, $remaining);
                            $buffer = fread($handle, $read);
                            if ($buffer === false) {
                                break;
                            }
                            echo $buffer;
                            $remaining -= $read;
                            flush();
                        }
                        fclose($handle);
                    },
                    206,
                    $headers
                );
            }
        }

        // 普通文件下载
        return response()->file($filePath, $headers);
    }

    /**
     * 获取文件的MIME类型，确保返回正确的音频类型
     */
    private function getMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $defaultType = 'application/octet-stream';

        $mimeTypes = [
            'mp3' => 'audio/mpeg',
            'ogg' => 'audio/ogg',
            'wav' => 'audio/wav',
            'flac' => 'audio/flac',
            'm4a' => 'audio/mp4',
            'aac' => 'audio/aac',
        ];

        return $mimeTypes[$extension] ?? $defaultType;
    }
}
