<?php

namespace App\Http\Controllers\Api\Cloud;

use App\Http\Controllers\Controller;
use App\Models\Cloud\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncodedImageInterface;

class FileController extends Controller
{
    /**
     * 获取所有文件列表
     */
    public function index(Request $request)
    {
        // 获取用户ID，如果用户未登录，使用默认用户ID(1)
        $userId = Auth::check() ? Auth::id() : 1;
        
        $query = File::query()->where('user_id', $userId);

        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        } else {
            $query->whereNull('parent_id');
        }

        // 搜索
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // 类型过滤
        if ($request->has('type')) {
            $type = $request->type;
            if ($type === 'folder') {
                $query->where('is_folder', true);
            } else {
                $query->where('is_folder', false);
                
                $extensions = [];
                switch ($type) {
                    case 'image':
                        $extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'];
                        break;
                    case 'pdf':
                        $extensions = ['pdf'];
                        break;
                    case 'document':
                        $extensions = ['doc', 'docx', 'txt', 'rtf', 'md'];
                        break;
                    case 'spreadsheet':
                        $extensions = ['xls', 'xlsx', 'csv'];
                        break;
                    case 'archive':
                        $extensions = ['zip', 'rar', '7z', 'tar', 'gz'];
                        break;
                    case 'audio':
                        $extensions = ['mp3', 'wav', 'ogg', 'flac'];
                        break;
                    case 'video':
                        $extensions = ['mp4', 'avi', 'mov', 'wmv', 'mkv'];
                        break;
                }
                
                if (!empty($extensions)) {
                    $query->whereIn('extension', $extensions);
                }
            }
        }

        // 排序
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        $files = $query->get();

        return response()->json($files);
    }

    /**
     * 创建文件夹
     */
    public function createFolder(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:cloud_files,id',
            'description' => 'nullable|string',
        ]);

        // 获取用户ID，如果用户未登录，使用默认用户ID(1)
        $userId = Auth::check() ? Auth::id() : 1;

        $file = new File();
        $file->name = $request->name;
        $file->parent_id = $request->parent_id;
        $file->user_id = $userId;
        $file->is_folder = true;
        $file->path = 'folders/' . Str::uuid();
        $file->description = $request->description;
        $file->save();

        return response()->json($file, 201);
    }

    /**
     * 上传文件
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:102400', // 100MB
            'parent_id' => 'nullable|exists:cloud_files,id',
            'description' => 'nullable|string',
        ]);

        $uploadedFile = $request->file('file');
        $originalName = $uploadedFile->getClientOriginalName();
        $extension = $uploadedFile->getClientOriginalExtension();
        $mimeType = $uploadedFile->getMimeType();
        $size = $uploadedFile->getSize();
        
        // 获取用户ID，如果用户未登录，使用默认用户ID(1)
        $userId = Auth::check() ? Auth::id() : 1;
        
        // 生成唯一文件路径
        $path = 'cloud/' . $userId . '/' . date('Y/m/d') . '/' . Str::uuid() . '.' . $extension;
        
        // 保存文件
        Storage::disk('public')->put($path, file_get_contents($uploadedFile));

        // 创建数据库记录
        $file = new File();
        $file->name = pathinfo($originalName, PATHINFO_FILENAME);
        $file->original_name = $originalName;
        $file->extension = $extension;
        $file->mime_type = $mimeType;
        $file->path = $path;
        $file->size = $size;
        $file->parent_id = $request->parent_id;
        $file->user_id = $userId; // 使用获取的用户ID
        $file->description = $request->description;
        $file->save();

        return response()->json($file, 201);
    }

    /**
     * 下载文件
     */
    public function download($id)
    {
        // 获取用户ID，如果用户未登录，使用默认用户ID(1)
        $userId = Auth::check() ? Auth::id() : 1;
        
        $file = File::where('user_id', $userId)->findOrFail($id);

        if ($file->is_folder) {
            return response()->json(['error' => '不能下载文件夹'], 400);
        }

        if (!Storage::disk('public')->exists($file->path)) {
            return response()->json(['error' => '文件不存在'], 404);
        }

        return response()->download(storage_path('app/public/' . $file->path), $file->original_name);
    }

    /**
     * 删除文件或文件夹
     */
    public function destroy($id)
    {
        // 获取用户ID，如果用户未登录，使用默认用户ID(1)
        $userId = Auth::check() ? Auth::id() : 1;
        
        $file = File::where('user_id', $userId)->findOrFail($id);

        // 如果是文件夹，递归删除所有子文件和子文件夹
        if ($file->is_folder) {
            $this->deleteFolder($file);
        } else {
            // 删除存储的文件
            if (Storage::disk('public')->exists($file->path)) {
                Storage::disk('public')->delete($file->path);
            }
            
            // 删除数据库记录
            $file->delete();
        }

        return response()->json(null, 204);
    }

    /**
     * 递归删除文件夹
     */
    private function deleteFolder(File $folder)
    {
        // 获取用户ID，如果用户未登录，使用默认用户ID(1)
        $userId = Auth::check() ? Auth::id() : 1;
        
        // 获取所有子文件和子文件夹
        $children = File::where('parent_id', $folder->id)->get();

        foreach ($children as $child) {
            if ($child->is_folder) {
                $this->deleteFolder($child);
            } else {
                // 删除存储的文件
                if (Storage::disk('public')->exists($child->path)) {
                    Storage::disk('public')->delete($child->path);
                }
                
                // 删除数据库记录
                $child->delete();
            }
        }

        // 删除文件夹记录
        $folder->delete();
    }

    /**
     * 获取文件详情
     */
    public function show($id)
    {
        // 获取用户ID，如果用户未登录，使用默认用户ID(1)
        $userId = Auth::check() ? Auth::id() : 1;
        
        $file = File::where('user_id', $userId)->findOrFail($id);
        return response()->json($file);
    }

    /**
     * 更新文件信息
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        // 获取用户ID，如果用户未登录，使用默认用户ID(1)
        $userId = Auth::check() ? Auth::id() : 1;
        
        $file = File::where('user_id', $userId)->findOrFail($id);
        $file->name = $request->name;
        $file->description = $request->description;
        $file->save();

        return response()->json($file);
    }

    /**
     * 移动文件
     */
    public function move(Request $request)
    {
        $request->validate([
            'file_ids' => 'required|array',
            'file_ids.*' => 'exists:cloud_files,id',
            'target_folder_id' => 'nullable|exists:cloud_files,id',
        ]);

        // 获取用户ID，如果用户未登录，使用默认用户ID(1)
        $userId = Auth::check() ? Auth::id() : 1;
        
        $targetFolderId = $request->target_folder_id;
        
        // 如果目标文件夹ID存在，验证它是否是文件夹
        if ($targetFolderId) {
            $targetFolder = File::where('user_id', $userId)
                ->where('id', $targetFolderId)
                ->where('is_folder', true)
                ->firstOrFail();
        }

        // 更新所有选中文件的父文件夹ID
        File::where('user_id', $userId)
            ->whereIn('id', $request->file_ids)
            ->update(['parent_id' => $targetFolderId]);

        return response()->json(['success' => true]);
    }

    /**
     * 获取存储使用统计
     */
    public function statistics()
    {
        // 获取用户ID，如果用户未登录，使用默认用户ID(1)
        $userId = Auth::check() ? Auth::id() : 1;
        
        $totalSize = File::where('user_id', $userId)
            ->where('is_folder', false)
            ->sum('size');
            
        $fileCount = File::where('user_id', $userId)
            ->where('is_folder', false)
            ->count();
            
        $folderCount = File::where('user_id', $userId)
            ->where('is_folder', true)
            ->count();
            
        $filesByType = File::where('user_id', $userId)
            ->where('is_folder', false)
            ->selectRaw('
                CASE 
                    WHEN extension IN ("jpg", "jpeg", "png", "gif", "bmp", "svg", "webp") THEN "图片"
                    WHEN extension IN ("pdf") THEN "PDF"
                    WHEN extension IN ("doc", "docx", "txt", "rtf", "md") THEN "文档"
                    WHEN extension IN ("xls", "xlsx", "csv") THEN "表格"
                    WHEN extension IN ("zip", "rar", "7z", "tar", "gz") THEN "压缩包"
                    WHEN extension IN ("mp3", "wav", "ogg", "flac") THEN "音频"
                    WHEN extension IN ("mp4", "avi", "mov", "wmv", "mkv") THEN "视频"
                    ELSE "其他"
                END as file_type,
                COUNT(*) as count,
                SUM(size) as total_size
            ')
            ->groupBy('file_type')
            ->get();
        
        return response()->json([
            'total_size' => $totalSize,
            'human_readable_size' => $this->formatSize($totalSize),
            'file_count' => $fileCount,
            'folder_count' => $folderCount,
            'files_by_type' => $filesByType,
        ]);
    }
    
    /**
     * 格式化文件大小
     */
    private function formatSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * 获取完整的目录树
     */
    public function tree()
    {
        // 获取用户ID，如果用户未登录，使用默认用户ID(1)
        $userId = Auth::check() ? Auth::id() : 1;
        
        $rootFolders = File::where('user_id', $userId)
            ->where('is_folder', true)
            ->whereNull('parent_id')
            ->get();
            
        $tree = [];
        
        foreach ($rootFolders as $folder) {
            $tree[] = $this->buildFolderTree($folder);
        }
        
        return response()->json($tree);
    }
    
    /**
     * 递归构建文件夹树
     */
    private function buildFolderTree($folder)
    {
        // 获取用户ID，如果用户未登录，使用默认用户ID(1)
        $userId = Auth::check() ? Auth::id() : 1;
        
        $children = File::where('user_id', $userId)
            ->where('is_folder', true)
            ->where('parent_id', $folder->id)
            ->get();
            
        $node = [
            'id' => $folder->id,
            'name' => $folder->name,
            'children' => [],
        ];
        
        foreach ($children as $child) {
            $node['children'][] = $this->buildFolderTree($child);
        }
        
        return $node;
    }
    
    /**
     * 预览文件
     */
    public function preview($id)
    {
        // 获取用户ID，如果用户未登录，使用默认用户ID(1)
        $userId = Auth::check() ? Auth::id() : 1;
        
        $file = File::where('user_id', $userId)->findOrFail($id);

        if ($file->is_folder) {
            return response()->json(['error' => '不能预览文件夹'], 400);
        }

        if (!Storage::disk('public')->exists($file->path)) {
            return response()->json(['error' => '文件不存在'], 404);
        }
        
        $extension = strtolower($file->extension);
        $mimeType = $file->mime_type;
        
        // 图片类文件直接返回URL
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'])) {
            return response()->json([
                'type' => 'image',
                'url' => url('storage/' . $file->path),
            ]);
        }
        
        // PDF文件直接返回URL
        if ($extension === 'pdf') {
            return response()->json([
                'type' => 'pdf',
                'url' => url('storage/' . $file->path),
            ]);
        }
        
        // 文本文件返回内容
        if (in_array($extension, ['txt', 'md', 'json', 'xml', 'html', 'css', 'js', 'php']) || 
            Str::startsWith($mimeType, 'text/')) {
            return response()->json([
                'type' => 'text',
                'content' => Storage::disk('public')->get($file->path),
            ]);
        }
        
        // 无法预览的文件
        return response()->json([
            'type' => 'unknown',
            'message' => '此文件类型不支持预览，请下载后查看',
        ]);
    }
} 