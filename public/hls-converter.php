<?php
/**
 * HLS转换器
 * 使用 chrisyue/php-m3u8 库将m3u8文件自动转换为兼容的格式，修正各种格式问题
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Chrisyue\PhpM3u8\Data\Transformer;
use Chrisyue\PhpM3u8\Facade\ParserFacade;
use Chrisyue\PhpM3u8\Facade\DumperFacade;
use Chrisyue\PhpM3u8\Stream\TextStream;

// 设置无限超时
set_time_limit(0);

// 日志文件
$logFile = __DIR__ . '/hls-logs.txt';

// 记录函数
function writeLog($message) {
    global $logFile;
    $logContent = date('Y-m-d H:i:s') . " - {$message}\n";
    file_put_contents($logFile, $logContent, FILE_APPEND);
}

// 错误处理函数
function outputError($message, $statusCode = 500) {
    writeLog("错误: {$message}");
    
    // 返回JSON错误
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode(['error' => $message]);
    exit;
}

// 获取完整URL
function getFullUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // 支持自定义端口
    if (strpos($host, ':') === false && isset($_SERVER['SERVER_PORT'])) {
        $port = $_SERVER['SERVER_PORT'];
        if (($protocol === 'http' && $port != 80) || ($protocol === 'https' && $port != 443)) {
            $host .= ":{$port}";
        }
    }
    
    // 支持反向代理
    $baseUrl = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? 
        "{$protocol}://{$_SERVER['HTTP_X_FORWARDED_HOST']}" : 
        "{$protocol}://{$host}";
    
    // 去除查询字符串
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $uri = strtok($uri, '?');
    
    // 获取到转换器所在的目录
    $path = pathinfo($uri, PATHINFO_DIRNAME);
    
    // 确保路径不以斜杠结尾，防止后续拼接时出现双斜杠
    $baseUrl = rtrim($baseUrl, '/');
    $path = rtrim($path, '/');
    
    $fullUrl = "{$baseUrl}{$path}";
    writeLog("生成完整URL: {$fullUrl}");
    
    return $fullUrl;
}

// 检查是否是本地开发环境
function isLocalDevelopment() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return strpos($host, 'localhost') !== false || 
           strpos($host, '127.0.0.1') !== false ||
           strpos($host, '.local') !== false;
}

// 简化的M3U8处理函数 - 重新生成干净的M3U8
function generateCleanM3u8($content, $songDir) {
    global $logFile;
    
    $lines = explode("\n", $content);
    $result = [];
    $tsFiles = [];
    $duration = 0;
    
    // 解析并提取所有TS文件和持续时长
    $currentDuration = 0;
    foreach ($lines as $line) {
        $line = trim($line);
        
        // 提取持续时间
        if (preg_match('/^#EXTINF:([0-9\.]+)/', $line, $matches)) {
            $currentDuration = floatval($matches[1]);
            continue;
        }
        
        // 收集TS文件
        if (!empty($line) && strpos($line, '#') !== 0 && strpos($line, '.ts') !== false) {
            $tsFile = basename($line);
            $tsFiles[] = [
                'file' => $tsFile,
                'duration' => $currentDuration
            ];
            $duration += $currentDuration;
            $currentDuration = 0;
        }
    }
    
    // 检查是否找到TS文件
    if (empty($tsFiles)) {
        writeLog("警告: 未找到任何TS文件引用");
        return false;
    }
    
    // 计算目标持续时间 (向上取整最大片段持续时间)
    $maxDuration = 0;
    foreach ($tsFiles as $ts) {
        if ($ts['duration'] > $maxDuration) {
            $maxDuration = $ts['duration'];
        }
    }
    $targetDuration = ceil($maxDuration);
    
    // 构建清理后的M3U8
    $result[] = '#EXTM3U';
    $result[] = '#EXT-X-VERSION:3';
    $result[] = "#EXT-X-TARGETDURATION:{$targetDuration}";
    $result[] = '#EXT-X-MEDIA-SEQUENCE:0';
    $result[] = '#EXT-X-PLAYLIST-TYPE:VOD';
    
    // 添加所有片段
    $fullUrl = getFullUrl();
    // 正确编码目录名
    $encodedSongDir = rawurlencode($songDir);
    
    writeLog("生成M3U8 - 基本URL: {$fullUrl}, 歌曲目录: {$songDir}, 编码后: {$encodedSongDir}");
    
    foreach ($tsFiles as $ts) {
        $tsFile = rawurlencode($ts['file']);
        // 将TS文件URL改为通过ts-handler.php处理，确保有正确的CORS头
        $tsUrl = "{$fullUrl}/ts-handler.php?path={$encodedSongDir}/{$tsFile}";
        $result[] = "#EXTINF:{$ts['duration']},";
        $result[] = $tsUrl;
        
        writeLog("添加TS文件: {$ts['file']} -> {$tsUrl}");
    }
    
    // 添加结束标记
    $result[] = '#EXT-X-ENDLIST';
    
    writeLog("已生成干净的M3U8，包含 " . count($tsFiles) . " 个片段，总时长约 {$duration} 秒");
    
    return implode("\n", $result);
}

// 设置CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS, HEAD');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Range, Origin, Accept, Authorization');
header('Access-Control-Max-Age: 3600'); // 缓存预检请求结果1小时
header('Vary: Origin'); // 告诉CDN为不同的Origin返回不同的响应

// 对OPTIONS预检请求直接返回200
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 缓存控制
header('Cache-Control: no-cache, no-store, must-revalidate');

// 获取所请求的音乐文件路径
$path = isset($_GET['path']) ? $_GET['path'] : '';
if (empty($path)) {
    outputError('缺少参数：path', 400);
}

// 记录原始请求信息
writeLog("原始请求路径: {$path}");

// 解码URL，确保特殊字符正确处理
$decodedPath = rawurldecode($path);
writeLog("解码后路径: {$decodedPath}");

// 调试模式
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';
if ($debug) {
    writeLog("调试模式已启用");
}

// 构建M3U8文件路径
$m3u8File = __DIR__ . '/musics/hls/' . $decodedPath . '/playlist.m3u8';
$songDir = $decodedPath;

// 检查文件是否存在
if (!file_exists($m3u8File)) {
    writeLog("文件不存在: {$m3u8File}");
    
    // 尝试使用非解码路径
    $m3u8FileAlt = __DIR__ . '/musics/hls/' . $path . '/playlist.m3u8';
    if (file_exists($m3u8FileAlt)) {
        writeLog("使用原始路径找到文件: {$m3u8FileAlt}");
        $m3u8File = $m3u8FileAlt;
        $songDir = $path;
    } else {
        outputError('M3U8文件不存在', 404);
    }
}

// 读取原始M3U8文件内容
$content = file_get_contents($m3u8File);
if ($content === false) {
    writeLog("无法读取文件: {$m3u8File}");
    outputError('无法读取M3U8文件', 500);
}

writeLog("成功读取M3U8文件，大小: " . strlen($content) . " 字节");
writeLog("处理文件: {$m3u8File}，歌曲目录: {$songDir}");

// 处理文件内容
try {
    // 使用简化的M3U8处理
    $outputContent = generateCleanM3u8($content, $songDir);
    
    if ($outputContent === false) {
        outputError("无法解析M3U8文件内容", 500);
    }
    
    // 调试模式
    if ($debug) {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<h1>HLS 调试模式</h1>';
        
        echo '<h2>请求信息</h2>';
        echo '<table border="1" cellpadding="5">';
        echo '<tr><th>参数</th><th>值</th></tr>';
        echo '<tr><td>原始path参数</td><td>' . htmlspecialchars($path) . '</td></tr>';
        echo '<tr><td>解码后path参数</td><td>' . htmlspecialchars($decodedPath) . '</td></tr>';
        echo '<tr><td>M3U8文件路径</td><td>' . htmlspecialchars($m3u8File) . '</td></tr>';
        echo '<tr><td>歌曲目录</td><td>' . htmlspecialchars($songDir) . '</td></tr>';
        echo '<tr><td>基础URL</td><td>' . htmlspecialchars(getFullUrl()) . '</td></tr>';
        echo '</table>';
        
        echo '<h2>原始 M3U8 文件</h2>';
        echo '<pre>' . htmlspecialchars($content) . '</pre>';
        
        echo '<h2>修改后 M3U8 文件</h2>';
        echo '<pre>' . htmlspecialchars($outputContent) . '</pre>';
        
        // 提取TS文件URL列表
        preg_match_all('/https?:\/\/[^\n]+\.ts/m', $outputContent, $matches);
        $tsUrls = $matches[0] ?? [];
        
        echo '<h2>TS 文件检查</h2>';
        echo '<table border="1" cellpadding="5">';
        echo '<tr><th>索引</th><th>文件名</th><th>完整URL</th><th>状态</th></tr>';
        
        foreach ($tsUrls as $index => $url) {
            $fileName = basename($url);
            $status = '';
            $statusClass = '';
            
            // 检查本地TS文件是否存在
            $localPath = dirname($m3u8File) . '/' . rawurldecode(basename($url));
            if (file_exists($localPath)) {
                $status = '本地文件存在 (' . filesize($localPath) . ' 字节)';
                $statusClass = 'color: green;';
            } else {
                $decodedFileName = rawurldecode($fileName);
                $alternatePath = dirname($m3u8File) . '/' . $decodedFileName;
                if (file_exists($alternatePath)) {
                    $status = '本地文件存在(未编码名称) (' . filesize($alternatePath) . ' 字节)';
                    $statusClass = 'color: blue;';
                } else {
                    $status = '本地文件不存在';
                    $statusClass = 'color: red;';
                }
            }
            
            echo "<tr>";
            echo "<td>{$index}</td>";
            echo "<td>" . htmlspecialchars($fileName) . "</td>";
            echo "<td><a href=\"" . htmlspecialchars($url) . "\" target=\"_blank\">" . htmlspecialchars($url) . "</a></td>";
            echo "<td style=\"{$statusClass}\">" . $status . "</td>";
            echo "</tr>";
        }
        
        echo '</table>';
        
        // 添加一个直接播放测试
        echo '<h2>播放测试</h2>';
        echo '<audio controls>';
        echo '<source src="' . htmlspecialchars(str_replace('&debug=1', '', $_SERVER['REQUEST_URI'])) . '" type="application/vnd.apple.mpegurl">';
        echo '您的浏览器不支持 HTML5 音频播放';
        echo '</audio>';
        
        exit;
    }
    
    // 设置正确的内容类型和其他必要的HTTP头
    header('Content-Type: application/vnd.apple.mpegurl');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS, HEAD');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Range');
    header('Cache-Control: public, max-age=3600');
    
    // 确保响应内容是UTF-8编码，去除BOM和其他可能影响播放的字符
    $cleanContent = trim($outputContent);
    if (substr($cleanContent, 0, 3) === "\xEF\xBB\xBF") {
        $cleanContent = substr($cleanContent, 3); // 移除UTF-8 BOM
    }
    
    // 记录输出日志
    writeLog("成功输出m3u8文件，大小：" . strlen($cleanContent) . " 字节");
    
    // 输出修改后的m3u8
    echo $cleanContent;
    
} catch (Exception $e) {
    writeLog("异常: " . $e->getMessage());
    outputError('处理M3U8文件失败: ' . $e->getMessage(), 500);
} 