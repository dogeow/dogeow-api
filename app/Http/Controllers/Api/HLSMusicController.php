<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HLSMusicController extends Controller
{
    /**
     * 获取音乐文件列表
     */
    public function index()
    {
        // HLS 文件目录
        $hlsDir = public_path('musics/hls');
        
        // 检查目录是否存在
        if (!File::exists($hlsDir)) {
            return response()->json([]);
        }
        
        // 获取所有 m3u8 文件
        $directories = File::directories($hlsDir);
        $musicList = [];
        
        foreach ($directories as $directory) {
            $dirName = basename($directory);
            $m3u8File = File::glob("$directory/*.m3u8");
            
            if (count($m3u8File) > 0) {
                $m3u8Path = $m3u8File[0];
                $fileName = basename($m3u8Path);
                $musicName = str_replace('_', ' ', pathinfo($dirName, PATHINFO_FILENAME));
                
                // 检查是否有封面图片
                $coverImage = null;
                if (File::exists("$directory/cover.jpg")) {
                    $coverImage = "/musics/hls/$dirName/cover.jpg";
                } elseif (File::exists("$directory/cover.png")) {
                    $coverImage = "/musics/hls/$dirName/cover.png";
                }
                
                // 计算大致时长（从 m3u8 文件中)
                $duration = 0;
                $content = File::get($m3u8Path);
                preg_match_all('/#EXTINF:([\d.]+),/', $content, $matches);
                if (isset($matches[1]) && count($matches[1]) > 0) {
                    $duration = array_sum($matches[1]);
                }
                
                $musicList[] = [
                    'id' => $dirName,
                    'name' => $musicName,
                    'path' => "/musics/hls/$dirName/$fileName",
                    'coverUrl' => $coverImage,
                    'duration' => $duration,
                ];
            }
        }
        
        return response()->json($musicList);
    }
    
    /**
     * 获取 M3U8 文件或 TS 分片文件
     */
    public function stream($path)
    {
        // 构建文件路径
        $filePath = public_path("musics/hls/$path");
        
        // 检查文件是否存在
        if (!File::exists($filePath)) {
            Log::error("HLS 文件不存在: $filePath");
            return response()->json(['error' => 'File not found'], 404);
        }
        
        // 确定文件类型和响应头
        $fileExtension = File::extension($filePath);
        $contentType = 'application/octet-stream';
        
        switch ($fileExtension) {
            case 'm3u8':
                $contentType = 'application/vnd.apple.mpegurl';
                break;
            case 'ts':
                $contentType = 'video/MP2T';
                break;
            case 'aac':
                $contentType = 'audio/aac';
                break;
            case 'mp3':
                $contentType = 'audio/mpeg';
                break;
            case 'wav':
                $contentType = 'audio/wav';
                break;
        }
        
        // 设置响应头
        $headers = [
            'Content-Type' => $contentType,
            'Content-Length' => File::size($filePath),
            'Accept-Ranges' => 'bytes',
            'X-Pad' => 'avoid browser bug',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'public, max-age=86400',
        ];
        
        // 记录日志
        Log::info("Streaming HLS file: $filePath ($contentType)");
        
        // 流式输出文件
        return response()->file($filePath, $headers);
    }
    
    /**
     * 生成 HLS 文件（开发环境使用）
     */
    public function generateHLS(Request $request)
    {
        if (app()->environment('production')) {
            return response()->json(['error' => 'Not available in production'], 403);
        }
        
        $sourceFile = $request->input('source');
        $outputDir = $request->input('output');
        
        if (!$sourceFile || !$outputDir) {
            return response()->json(['error' => 'Source and output parameters are required'], 400);
        }
        
        $sourcePath = public_path("musics/$sourceFile");
        $outputPath = public_path("musics/hls/$outputDir");
        
        if (!File::exists($sourcePath)) {
            return response()->json(['error' => 'Source file not found'], 404);
        }
        
        // 创建输出目录
        File::makeDirectory($outputPath, 0755, true, true);
        
        // 使用 FFmpeg 生成 HLS
        $command = "ffmpeg -y -i \"$sourcePath\" -c:a aac -b:a 192k -ac 2 -hls_time 10 -hls_playlist_type vod -hls_segment_filename \"$outputPath/%03d.ts\" \"$outputPath/playlist.m3u8\" 2>&1";
        
        Log::info("Executing FFmpeg command: $command");
        
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            Log::error("FFmpeg error: " . implode("\n", $output));
            return response()->json([
                'error' => 'Failed to generate HLS',
                'output' => $output,
                'code' => $returnCode
            ], 500);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'HLS generated successfully',
            'playlist' => "/musics/hls/$outputDir/playlist.m3u8",
            'output' => $output
        ]);
    }
} 