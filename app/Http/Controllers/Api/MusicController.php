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
            if (in_array($file->getExtension(), ['mp3', 'ogg', 'wav', 'flac'])) {
                $musicList[] = [
                    'name' => str_replace('.' . $file->getExtension(), '', $file->getFilename()),
                    'path' => '/musics/' . $file->getFilename(),
                    'size' => $file->getSize(),
                    'extension' => $file->getExtension()
                ];
            }
        }
        
        return response()->json($musicList);
    }
    
    /**
     * 流式播放音乐文件，支持范围请求
     */
    public function stream($filename)
    {
        $path = public_path('musics/' . $filename);

        Log::info('Stream request', ['path' => $path, 'exists' => File::exists($path)]);
        
        if (!File::exists($path)) {
            Log::error('音乐文件不存在', ['path' => $path]);
            return response()->json(['error' => '文件不存在'], 404);
        }
        
        $fileSize = File::size($path);
        $mimeType = $this->getMimeType($path);
        
        // 处理范围请求
        $start = 0;
        $end = $fileSize - 1;
        $statusCode = 200;
        
        // 获取请求头中的Range
        $range = request()->header('Range');
        Log::info('Range header', ['range' => $range]);
        
        if ($range) {
            // 解析范围请求
            $rangeParts = explode('=', $range);
            if (count($rangeParts) === 2 && $rangeParts[0] === 'bytes') {
                $rangeValues = explode('-', $rangeParts[1]);
                
                // 起始位置
                if (!empty($rangeValues[0])) {
                    $start = (int) $rangeValues[0];
                }
                
                // 结束位置(如果提供)
                if (!empty($rangeValues[1])) {
                    $end = (int) $rangeValues[1];
                }
                
                // 确保结束位置不超过文件大小
                if ($end >= $fileSize) {
                    $end = $fileSize - 1;
                }
                
                // 如果是范围请求，返回206状态码
                $statusCode = 206;
            }
        }
        
        // 计算实际要发送的数据长度
        $length = $end - $start + 1;
        
        // 设置响应头
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => $length,
            'Accept-Ranges' => 'bytes',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS, HEAD',
            'Access-Control-Allow-Headers' => 'Origin, Content-Type, Accept, Range',
            'Cross-Origin-Resource-Policy' => 'cross-origin', // 允许跨域资源共享
            'Cache-Control' => 'public, max-age=31536000' // 缓存一年
        ];
        
        // 如果是范围请求，添加Content-Range头
        if ($statusCode === 206) {
            $headers['Content-Range'] = "bytes $start-$end/$fileSize";
        }
        
        Log::info('Stream response', [
            'statusCode' => $statusCode,
            'mimeType' => $mimeType,
            'length' => $length,
            'start' => $start,
            'end' => $end,
            'fileSize' => $fileSize
        ]);
        
        // 流式返回文件内容
        return new StreamedResponse(function () use ($path, $start, $length) {
            $handle = fopen($path, 'rb');
            fseek($handle, $start);
            
            // 如果是MP3文件的开头部分，可能需要特殊处理
            $isFirstChunk = ($start === 0);
            
            // 每次发送 8KB
            $chunkSize = 8 * 1024;
            $bytesLeft = $length;
            $bytesRead = 0;
            
            while (!feof($handle) && $bytesLeft > 0) {
                $bytesToSend = min($chunkSize, $bytesLeft);
                $data = fread($handle, $bytesToSend);
                echo $data;
                
                $bytesRead += strlen($data);
                $bytesLeft -= strlen($data);
                
                // 确保内容全部发送到客户端
                flush();
                
                // 为了避免服务器过载，可以考虑小延迟
                if ($bytesLeft > 0) {
                    usleep(1000); // 1ms延迟，通常不会被感知
                }
            }
            
            // 确保文件句柄被关闭
            fclose($handle);
            
            // 记录实际发送的字节数
            Log::info('Stream completed', [
                'bytesRead' => $bytesRead,
                'expectedLength' => $length,
                'difference' => $bytesRead - $length
            ]);
        }, $statusCode, $headers);
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