<?php
/**
 * TS文件处理器
 * 用于向TS文件请求添加CORS头，解决跨域问题
 */

// 设置无限超时
set_time_limit(0);

// 添加CORS头
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS, HEAD');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Range, Origin, Accept, Authorization');
header('Access-Control-Max-Age: 3600');
header('Access-Control-Expose-Headers: Content-Length, Content-Range, Content-Disposition');
header('Cross-Origin-Resource-Policy: cross-origin');
header('Vary: Origin');

// 处理OPTIONS预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 获取请求的TS文件路径
$path = isset($_GET['path']) ? $_GET['path'] : '';
if (empty($path)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Missing path parameter']);
    exit;
}

// 解码路径
$decodedPath = rawurldecode($path);

// 构建完整文件路径
$filePath = __DIR__ . '/musics/hls/' . $decodedPath;

// 检查文件是否存在
if (!file_exists($filePath)) {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'File not found: ' . $filePath]);
    exit;
}

// 获取文件大小
$fileSize = filesize($filePath);

// 设置响应头
header('Content-Type: video/MP2T');
header('Content-Length: ' . $fileSize);
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=86400'); // 缓存1天

// 处理范围请求 (Range)
$start = 0;
$end = $fileSize - 1;

if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];
    $matches = [];
    
    if (preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
        $start = !empty($matches[1]) ? intval($matches[1]) : 0;
        $end = !empty($matches[2]) ? intval($matches[2]) : $fileSize - 1;
        
        if ($end >= $fileSize) {
            $end = $fileSize - 1;
        }
        
        header('HTTP/1.1 206 Partial Content');
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
        header('Content-Length: ' . ($end - $start + 1));
    }
}

// 发送文件内容
$fp = fopen($filePath, 'rb');
if ($start > 0) {
    fseek($fp, $start);
}

$chunkSize = 8192; // 8KB 块
$bytesToSend = $end - $start + 1;

while (!feof($fp) && $bytesToSend > 0) {
    $buffer = fread($fp, min($chunkSize, $bytesToSend));
    $bytesToSend -= strlen($buffer);
    echo $buffer;
    flush();
}

fclose($fp);
exit; 