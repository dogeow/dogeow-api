<?php

namespace App\Http\Controllers\Api\Cloud;

use App\Http\Controllers\Concerns\GetCurrentUserId;
use App\Http\Controllers\Controller;
use App\Models\Cloud\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class FileTreeController extends Controller
{
    use GetCurrentUserId;

    /**
     * 获取存储使用统计
     */
    public function statistics(): JsonResponse
    {
        $userId = $this->getCurrentUserId();

        // 基础统计 - 单次查询获取所有数据
        /** @var \stdClass|null $baseStats */
        $baseStats = File::where('user_id', $userId)
            ->selectRaw('
                COUNT(CASE WHEN is_folder = false THEN 1 END) as file_count,
                COUNT(CASE WHEN is_folder = true THEN 1 END) as folder_count,
                COALESCE(SUM(CASE WHEN is_folder = false THEN size END), 0) as total_size
            ')
            ->first();

        // 标签和扩展名通过绑定参数传入，兼容 PostgreSQL / MySQL / SQLite。
        $labels = ['image' => '图片', 'pdf' => 'PDF', 'document' => '文档', 'spreadsheet' => '表格',
            'archive' => '压缩包', 'audio' => '音频', 'video' => '视频'];
        $cases = [];
        $bindings = [];
        foreach ($labels as $type => $label) {
            $extensions = File::getExtensionsByType($type);
            $placeholders = implode(', ', array_fill(0, count($extensions), '?'));
            $cases[] = "WHEN LOWER(extension) IN ({$placeholders}) THEN ?";
            $bindings = array_merge($bindings, $extensions, [$label]);
        }
        $bindings[] = '其他';
        $expression = 'CASE ' . implode(' ', $cases) . ' ELSE ? END';
        $filesByType = File::where('user_id', $userId)
            ->where('is_folder', false)
            ->selectRaw($expression . ' as file_type, COUNT(*) as count, COALESCE(SUM(size), 0) as total_size', $bindings)
            ->groupBy('file_type')
            ->get();

        return response()->json([
            'total_size' => (int) $baseStats->total_size,
            'human_readable_size' => $this->formatSize((int) $baseStats->total_size),
            'file_count' => (int) $baseStats->file_count,
            'folder_count' => (int) $baseStats->folder_count,
            'files_by_type' => $filesByType->map(fn (File $item) => [
                'file_type' => $item->file_type,
                'count' => (int) $item->count,
                'total_size' => (int) $item->total_size,
            ]),
        ]);
    }

    /**
     * 获取完整的目录树
     */
    public function tree(): JsonResponse
    {
        $userId = $this->getCurrentUserId();

        $folders = File::query()
            ->where('user_id', $userId)
            ->where('is_folder', true)
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        return response()->json($this->buildFolderTreeFromCollection($folders));
    }

    /**
     * 一次加载全部文件夹后在内存中建树，避免递归 N+1。
     *
     * @param  Collection<int, File>  $folders
     * @return list<array{id:int,name:string,children:list<array{id:int,name:string,children:list}>}>
     */
    private function buildFolderTreeFromCollection(Collection $folders): array
    {
        /** @var array<int|string, list<File>> $byParent */
        $byParent = [];

        foreach ($folders as $folder) {
            $parentKey = $folder->parent_id === null ? 'root' : (int) $folder->parent_id;
            $byParent[$parentKey][] = $folder;
        }

        $build = function (int|string $parentKey) use (&$build, $byParent): array {
            $children = $byParent[$parentKey] ?? [];
            $nodes = [];

            foreach ($children as $folder) {
                $nodes[] = [
                    'id' => (int) $folder->id,
                    'name' => (string) $folder->name,
                    'children' => $build((int) $folder->id),
                ];
            }

            return $nodes;
        };

        return $build('root');
    }

    /**
     * 格式化文件大小
     */
    private function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
