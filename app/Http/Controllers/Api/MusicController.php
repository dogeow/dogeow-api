<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;

class MusicController extends Controller
{
    /**
     * 获取所有可用的音乐列表
     */
    public function index()
    {
        $musicDir = public_path('musics');
        
        if (!File::exists($musicDir)) {
            return response()->json(['error' => '音乐目录不存在'], 404);
        }
        
        $files = File::files($musicDir);
        $musicList = [];
        
        foreach ($files as $file) {
            $extension = $file->getExtension();
            if (in_array($extension, ['mp3', 'ogg', 'wav', 'flac'])) {
                $filename = $file->getFilename();
                $musicList[] = [
                    'name' => str_replace('.' . $extension, '', $filename),
                    'path' => '/musics/' . $filename,
                    'size' => $file->getSize(),
                    'extension' => $extension
                ];
            }
        }
        
        return response()->json($musicList);
    }

    /**
     * 下载音乐文件，确保正确的MIME类型和响应头
     */
    public function download($filename)
    {
        $filePath = public_path('musics/' . $filename);
        
        if (!File::exists($filePath)) {
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
            'Content-Disposition' => 'inline; filename="' . $filename . '"'
        ];
        
        // 支持范围请求（用于音频流式播放）
        if (request()->hasHeader('Range')) {
            $range = request()->header('Range');
            $range = str_replace('bytes=', '', $range);
            list($start, $end) = explode('-', $range);
            
            $start = (int) $start;
            $end = $end ? (int) $end : $fileSize - 1;
            $length = $end - $start + 1;
            
            $headers['Content-Range'] = "bytes $start-$end/$fileSize";
            $headers['Content-Length'] = $length;
            $headers['Accept-Ranges'] = 'bytes';
            
            return response()->stream(
                function () use ($filePath, $start, $length) {
                    $handle = fopen($filePath, 'rb');
                    fseek($handle, $start);
                    $buffer = 8192;
                    $remaining = $length;
                    
                    while ($remaining > 0 && !feof($handle)) {
                        $read = min($buffer, $remaining);
                        echo fread($handle, $read);
                        $remaining -= $read;
                        flush();
                    }
                    
                    fclose($handle);
                },
                206,
                $headers
            );
        }
        
        // 普通文件下载
        return response()->file($filePath, $headers);
    }
    
    /**
     * 获取文件的MIME类型，确保返回正确的音频类型
     */
    private function getMimeType($path)
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $defaultType = 'application/octet-stream';
        
        $mimeTypes = [
            'mp3' => 'audio/mpeg',
            'ogg' => 'audio/ogg',
            'wav' => 'audio/wav',
            'flac' => 'audio/flac',
            'm4a' => 'audio/mp4',
            'aac' => 'audio/aac'
        ];
        
        return $mimeTypes[$extension] ?? $defaultType;
    }
} 