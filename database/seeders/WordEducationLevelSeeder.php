<?php

namespace Database\Seeders;

use App\Models\Word\EducationLevel;
use Illuminate\Database\Seeder;

class WordEducationLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $levels = [
            ['code' => 'primary', 'name' => '小学', 'sort_order' => 0],
            ['code' => 'junior_high', 'name' => '初中', 'sort_order' => 1],
            ['code' => 'senior_high', 'name' => '高中', 'sort_order' => 2],
            ['code' => 'cet4', 'name' => 'CET4', 'sort_order' => 3],
            ['code' => 'cet6', 'name' => 'CET6', 'sort_order' => 4],
            ['code' => 'postgraduate', 'name' => '考研', 'sort_order' => 5],
            ['code' => 'ielts', 'name' => '雅思', 'sort_order' => 6],
            ['code' => 'toefl', 'name' => '托福', 'sort_order' => 7],
            ['code' => 'tem4', 'name' => '专四', 'sort_order' => 8],
            ['code' => 'tem8', 'name' => '专八', 'sort_order' => 9],
        ];

        foreach ($levels as $level) {
            EducationLevel::firstOrCreate(
                ['code' => $level['code']],
                $level
            );
        }

        $this->command->info('教育级别数据已创建');
    }
}
