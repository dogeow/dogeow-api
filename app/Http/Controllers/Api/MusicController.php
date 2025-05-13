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