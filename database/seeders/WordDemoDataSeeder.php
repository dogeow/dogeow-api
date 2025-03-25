<?php

namespace Database\Seeders;

use App\Models\Word\Category;
use App\Models\Word\Book;
use App\Models\Word\Word;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;
use Illuminate\Support\Str;

class WordDemoDataSeeder extends Seeder
{
    /**
     * 运行数据库种子
     */
    public function run(): void
    {
        $faker = Faker::create();
        $faker->addProvider(new \Faker\Provider\en_US\Text($faker));
        
        // 关闭外键约束检查，提高插入速度
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        // 清空现有数据
        Word::truncate();
        Book::truncate();
        Category::truncate();
        
        // 创建分类
        $categories = $this->createCategories();
        
        // 创建单词书
        $books = $this->createBooks($categories);
        
        // 批量创建单词
        $this->createWords($books);
        
        // 恢复外键约束检查
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        
        $this->command->info('成功生成单词数据！');
    }
    
    /**
     * 创建单词分类
     */
    private function createCategories(): array
    {
        $this->command->info('正在创建单词分类...');
        
        $categories = [
            ['name' => '基础英语', 'description' => '适合初学者的基础英语词汇', 'sort_order' => 1],
            ['name' => '中级英语', 'description' => '适合提高的中级英语词汇', 'sort_order' => 2],
            ['name' => '高级英语', 'description' => '适合进阶的高级英语词汇', 'sort_order' => 3],
            ['name' => '商务英语', 'description' => '商务场景中常用的英语词汇', 'sort_order' => 4],
            ['name' => '考试英语', 'description' => '各类考试中高频出现的英语词汇', 'sort_order' => 5],
        ];
        
        $categoryIds = [];
        foreach ($categories as $category) {
            $cat = Category::create($category);
            $categoryIds[] = $cat->id;
        }
        
        $this->command->info('成功创建 ' . count($categories) . ' 个单词分类');
        
        return $categoryIds;
    }
    
    /**
     * 创建单词书
     */
    private function createBooks(array $categoryIds): array
    {
        $this->command->info('正在创建单词书...');
        
        $faker = Faker::create();
        $books = [];
        $bookIds = [];
        
        $bookNames = [
            '初级英语必备1000词',
            '中级英语核心2000词',
            '高级英语精选3000词',
            '雅思词汇精选5000词',
            '托福核心词汇4000词', 
            '商务英语必备词汇2500词',
            '考研英语高频词汇3500词',
            '职场英语词汇2000词',
            '英语日常交流3000词',
            '学术英语词汇4500词'
        ];
        
        foreach ($bookNames as $index => $name) {
            $categoryId = $categoryIds[array_rand($categoryIds)];
            $difficulty = rand(1, 5);
            $book = Book::create([
                'word_category_id' => $categoryId,
                'name' => $name,
                'description' => $faker->paragraph(),
                'difficulty' => $difficulty,
                'total_words' => 0, // 稍后更新
                'sort_order' => $index + 1
            ]);
            
            $books[] = [
                'id' => $book->id,
                'name' => $book->name,
                'word_count' => rand(5000, 15000) // 每本书分配5000-15000个单词
            ];
            
            $bookIds[] = $book->id;
        }
        
        $this->command->info('成功创建 ' . count($books) . ' 本单词书');
        
        return $books;
    }
    
    /**
     * 创建单词
     */
    private function createWords(array $books): void
    {
        $this->command->info('正在生成单词数据，这可能需要一些时间...');
        
        $faker = Faker::create('en_US');
        $wordData = [];
        $totalWords = 0;
        $batchSize = 1000; // 每批插入1000条记录
        
        // 准备单词源数据（可以从文件或API获取真实单词）
        $baseWords = $this->getBaseWords();
        $baseWordCount = count($baseWords);
        
        // 预生成一些常见的音标格式
        $phoneticFormats = [
            'əˈnaɪs', 'ˈænsə', 'ˈæpəl', 'ˈɑːkɪtekt', 'ˈɑːmi',
            'ˈɑːtɪst', 'æz', 'æsk', 'ət', 'əˈweɪ', 'ˈbeɪbi',
            'bæk', 'bæd', 'bæɡ', 'bɔːl', 'bəˈnɑːnə', 'bæŋk',
            'ˈbɑːskɪtbɔːl', 'bɑːθ', 'biː', 'biːtʃ', 'ˈbjuːtəfəl',
            'bɪˈkɒz', 'bed', 'ˈbedruːm', 'biːf', 'bɪˈfɔː', 'bɪˈɡɪn',
            'bɪˈhaɪnd', 'ˈbelɪ', 'bɪˈlɒŋ', 'bɪˈləʊ', 'belt'
        ];
        
        // 为每本书生成单词
        foreach ($books as $book) {
            $wordCount = $book['word_count'];
            $this->command->info("正在为《{$book['name']}》生成 {$wordCount} 个单词...");
            
            $bookWords = [];
            
            for ($i = 0; $i < $wordCount; $i++) {
                // 使用基础单词库，如果用完则随机生成
                $wordIndex = ($totalWords + $i) % $baseWordCount;
                $word = $baseWords[$wordIndex];
                
                // 生成中英文释义和例句
                $definitions = [];
                $definitionCount = rand(1, 4);
                for ($j = 0; $j < $definitionCount; $j++) {
                    $definitions[] = $faker->sentence(rand(3, 8));
                }
                
                $examples = [];
                $exampleCount = rand(1, 3);
                for ($j = 0; $j < $exampleCount; $j++) {
                    $examples[] = [
                        'en' => $faker->sentence(rand(5, 12)),
                        'zh' => $faker->sentence(rand(5, 10))
                    ];
                }
                
                // 随机选择音标或生成一个
                $phoneticUK = $phoneticFormats[array_rand($phoneticFormats)];
                $phoneticUS = $phoneticFormats[array_rand($phoneticFormats)];
                
                $bookWords[] = [
                    'word_book_id' => $book['id'],
                    'content' => $word,
                    'phonetic_uk' => $phoneticUK,
                    'phonetic_us' => $phoneticUS,
                    'explanation' => json_encode([
                        'en' => implode("\n", $definitions),
                        'zh' => implode("\n", array_map(function() use ($faker) {
                            return $faker->sentence(rand(3, 8));
                        }, range(1, $definitionCount)))
                    ]),
                    'example_sentences' => json_encode($examples),
                    'synonyms' => implode(',', array_map(function() use ($baseWords) {
                        return $baseWords[array_rand($baseWords)];
                    }, range(0, rand(0, 3)))),
                    'antonyms' => implode(',', array_map(function() use ($baseWords) {
                        return $baseWords[array_rand($baseWords)];
                    }, range(0, rand(0, 2)))),
                    'difficulty' => rand(1, 5),
                    'frequency' => rand(1, 5),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                
                // 当达到批量大小时，执行插入
                if (count($bookWords) >= $batchSize) {
                    Word::insert($bookWords);
                    $this->command->info("已插入 " . count($bookWords) . " 个单词");
                    $wordData = array_merge($wordData, $bookWords);
                    $bookWords = [];
                }
            }
            
            // 插入剩余的单词
            if (count($bookWords) > 0) {
                Word::insert($bookWords);
                $this->command->info("已插入 " . count($bookWords) . " 个单词");
                $wordData = array_merge($wordData, $bookWords);
            }
            
            // 更新单词书的总单词数
            Book::where('id', $book['id'])->update(['total_words' => $wordCount]);
            
            $totalWords += $wordCount;
        }
        
        $this->command->info("总共生成了 {$totalWords} 个单词");
    }
    
    /**
     * 获取基础单词库
     */
    private function getBaseWords(): array
    {
        // 这里可以从文件或API获取真实的单词，这里为了简化，我们使用一些常见单词和随机生成的单词
        $commonWords = [
            'ability', 'able', 'about', 'above', 'accept', 'according', 'account', 'across',
            'act', 'action', 'activity', 'actually', 'add', 'address', 'administration', 'admit',
            'adult', 'affect', 'after', 'again', 'against', 'age', 'agency', 'agent',
            'ago', 'agree', 'agreement', 'ahead', 'air', 'all', 'allow', 'almost',
            'alone', 'along', 'already', 'also', 'although', 'always', 'American', 'among',
            'amount', 'analysis', 'and', 'animal', 'another', 'answer', 'any', 'anyone',
            'anything', 'appear', 'apply', 'approach', 'area', 'argue', 'arm', 'around',
            'arrive', 'art', 'article', 'artist', 'as', 'ask', 'assume', 'at',
            'attack', 'attention', 'attorney', 'audience', 'author', 'authority', 'available', 'avoid',
            'away', 'baby', 'back', 'bad', 'bag', 'ball', 'bank', 'bar',
            'base', 'be', 'beat', 'beautiful', 'because', 'become', 'bed', 'before',
            'begin', 'behavior', 'behind', 'believe', 'benefit', 'best', 'better', 'between',
            'beyond', 'big', 'bill', 'billion', 'bit', 'black', 'blood', 'blue',
            'board', 'body', 'book', 'born', 'both', 'box', 'boy', 'break',
            'bring', 'brother', 'budget', 'build', 'building', 'business', 'but', 'buy',
            'by', 'call', 'camera', 'campaign', 'can', 'cancer', 'candidate', 'capital',
            'car', 'card', 'care', 'career', 'carry', 'case', 'catch', 'cause',
            'cell', 'center', 'central', 'century', 'certain', 'certainly', 'chair', 'challenge',
            'chance', 'change', 'character', 'charge', 'check', 'child', 'choice', 'choose',
            'church', 'citizen', 'city', 'civil', 'claim', 'class', 'clear', 'clearly',
            'close', 'coach', 'cold', 'collection', 'college', 'color', 'come', 'commercial',
        ];
        
        // 生成更多随机单词
        $faker = Faker::create('en_US');
        $words = $commonWords;
        
        // 为了保证有足够的单词，生成1万个基础单词
        while (count($words) < 10000) {
            // 随机生成3-12个字母的单词
            $word = Str::lower($faker->lexify(str_repeat('?', rand(3, 12))));
            if (!in_array($word, $words)) {
                $words[] = $word;
            }
        }
        
        return $words;
    }
} 