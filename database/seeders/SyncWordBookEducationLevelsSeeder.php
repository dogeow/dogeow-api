<?php

namespace Database\Seeders;

use App\Models\Word\Book;
use App\Models\Word\EducationLevel;
use Illuminate\Database\Seeder;

class SyncWordBookEducationLevelsSeeder extends Seeder
{
    /**
     * 根据单词书名称关联教育级别
     */
    public function run(): void
    {
        if (!class_exists(Book::class) || !class_exists(EducationLevel::class)) {
            return;
        }

        $levels = EducationLevel::all()->keyBy('code');
        $books = Book::all();
        $synced = 0;

        foreach ($books as $book) {
            $bookName = strtolower($book->name);
            $levelIds = [];

            if (str_contains($bookName, '初中') || str_contains($bookName, 'junior')) {
                $levelIds[] = $levels->get('junior_high')?->id;
            }
            if (str_contains($bookName, '高中') || str_contains($bookName, 'senior')) {
                $levelIds[] = $levels->get('senior_high')?->id;
            }
            if (str_contains($bookName, '四级') || str_contains($bookName, 'cet4')) {
                $levelIds[] = $levels->get('cet4')?->id;
            }
            if (str_contains($bookName, '六级') || str_contains($bookName, 'cet6')) {
                $levelIds[] = $levels->get('cet6')?->id;
            }
            if (str_contains($bookName, '考研') || str_contains($bookName, 'postgraduate')) {
                $levelIds[] = $levels->get('postgraduate')?->id;
            }

            $levelIds = array_filter(array_unique($levelIds));
            if (!empty($levelIds)) {
                $book->educationLevels()->sync($levelIds);
                $synced++;
            }
        }

        $this->command?->info("已为 {$synced} 本单词书关联教育级别");
    }
}
