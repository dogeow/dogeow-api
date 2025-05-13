<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WordController extends Controller
{
    public function testCategories()
    {
        $tableName = 'word_categories';
        
        // 尝试直接从数据库查询
        $categories = DB::table($tableName)->get();
        
        // 检查表是否存在
        $tableExists = Schema::hasTable($tableName);
        
        // 查看表结构
        $columns = [];
        if ($tableExists) {
            $columns = DB::getSchemaBuilder()->getColumnListing($tableName);
        }
        
        return [
            'table_exists' => $tableExists,
            'table_name' => $tableName,
            'columns' => $columns,
            'count' => $categories->count(),
            'data' => $categories
        ];
    }
} 