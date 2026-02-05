<?php

namespace App\Console\Commands;

use App\Models\Word\Word;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchWordFromIcibaCommand extends Command
{
    protected $signature = 'word:fetch-iciba 
                            {--book= : 指定单词书ID（仅处理该书中的单词）}
                            {--limit=50 : 每次处理的单词数量}
                            {--sleep=800 : 每个请求之间的间隔(毫秒)}
                            {--force : 强制更新已有数据的单词}';

    protected $description = '从词典API获取单词的音标、中文释义和例句';

    private int $successCount = 0;
    private int $failCount = 0;
    private ?array $localDict = null;

    public function handle(): int
    {
        $bookId = $this->option('book');
        $limit = (int) $this->option('limit');
        $sleep = (int) $this->option('sleep');
        $force = $this->option('force');

        // 加载本地词典
        $this->loadLocalDict();

        $query = Word::query();
        
        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('explanation')
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(explanation, '$.zh')) = ''")
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(explanation, '$.zh')) LIKE '%【英】%'")
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(explanation, '$.zh')) IS NULL");
            });
        }

        // 如果指定了单词书，只处理该书中的单词
        if ($bookId) {
            $query->whereHas('books', function ($q) use ($bookId) {
                $q->where('word_books.id', $bookId);
            });
        }

        $total = $query->count();
        $this->info("找到 {$total} 个需要更新的单词");

        if ($total === 0) {
            $this->info('所有单词数据已完整');
            return Command::SUCCESS;
        }

        $words = $query->limit($limit)->get();
        $this->info("开始处理 {$words->count()} 个单词...");
        $this->newLine();

        foreach ($words as $index => $word) {
            $progress = $index + 1;
            $this->line("─────────────────────────────────────");
            $this->line("[{$progress}/{$words->count()}]");
            $this->fetchAndUpdate($word);
            usleep($sleep * 1000);
        }

        $this->newLine();
        $this->info("完成! 成功: {$this->successCount}, 失败: {$this->failCount}");

        if ($total > $limit) {
            $remaining = $total - $limit;
            $this->warn("还有 {$remaining} 个单词待处理，请再次运行此命令");
        }

        return Command::SUCCESS;
    }

    private function loadLocalDict(): void
    {
        $dictPath = database_path('data/en_zh_dict.json');
        if (file_exists($dictPath)) {
            $this->localDict = json_decode(file_get_contents($dictPath), true) ?? [];
            $this->info("已加载本地词典: " . count($this->localDict) . " 个词条");
        } else {
            $this->localDict = [];
            $this->warn("本地词典文件不存在: {$dictPath}");
        }
    }

    private function fetchAndUpdate(Word $word): void
    {
        try {
            $phonetic = null;
            $zhMeaning = '';
            $enMeaning = '';
            $examples = [];
            $source = '';
            
            // 1. 优先尝试有道 API
            $youdaoData = $this->fetchFromYoudao($word->content);
            
            if ($youdaoData) {
                $phonetic = $youdaoData['phonetic'];
                $zhMeaning = $youdaoData['zh_meaning'];
                $examples = $youdaoData['examples'];
                $source = '有道';
            }
            
            // 2. 如果有道失败，尝试本地词典 + Free Dictionary
            if (empty($zhMeaning)) {
                $localMeaning = $this->localDict[strtolower($word->content)] ?? null;
                $apiData = $this->fetchFromFreeDictionary($word->content);
                
                // 只从 Free Dictionary 获取音标，不用它的例句（没有中文翻译）
                $phonetic = $phonetic ?: ($apiData['phonetic'] ?? null);
                $enMeaning = $apiData['en_meaning'] ?? '';
                // 不使用 Free Dictionary 的例句，因为没有中文
                $examples = [];
                
                if ($localMeaning) {
                    $zhMeaning = $localMeaning;
                    $source = '本地词典';
                } elseif ($enMeaning) {
                    $zhMeaning = "【英】{$enMeaning}";
                    $source = 'Free Dictionary';
                }
            }
            
            if (!empty($zhMeaning) || !empty($phonetic)) {
                $oldZh = $word->explanation['zh'] ?? '(无)';
                
                // 只有当新例句有中文翻译时才更新
                $newExamples = $word->example_sentences ?? [];
                if (!empty($examples)) {
                    // 检查例句是否有中文翻译
                    $hasChineseExamples = false;
                    foreach ($examples as $ex) {
                        if (!empty($ex['zh'])) {
                            $hasChineseExamples = true;
                            break;
                        }
                    }
                    if ($hasChineseExamples) {
                        $newExamples = $examples;
                    }
                }
                
                $word->update([
                    'phonetic_us' => $phonetic ?? $word->phonetic_us,
                    'explanation' => [
                        'zh' => $zhMeaning ?: ($word->explanation['zh'] ?? ''),
                        'en' => $enMeaning,
                    ],
                    'example_sentences' => $newExamples,
                ]);
                
                $this->newLine();
                $this->info("✓ {$word->content} [{$source}]");
                if ($phonetic) {
                    $this->line("  音标: /{$phonetic}/");
                }
                $this->line("  释义: {$zhMeaning}");
                if (!empty($newExamples) && !empty($newExamples[0]['zh'])) {
                    $this->line("  例句: " . count($newExamples) . " 条（含中文）");
                }
                
                $this->successCount++;
            } else {
                $this->newLine();
                $this->warn("✗ {$word->content} - 未找到数据");
                $this->failCount++;
            }
        } catch (\Exception $e) {
            $this->newLine();
            $this->error("✗ {$word->content} - 异常: {$e->getMessage()}");
            Log::error("获取单词数据异常: {$word->content}", ['error' => $e->getMessage()]);
            $this->failCount++;
        }
    }

    private function fetchFromYoudao(string $word): ?array
    {
        try {
            $response = Http::timeout(10)
                ->get("https://dict.youdao.com/jsonapi_s", [
                    'q' => $word,
                    'le' => 'en',
                    'client' => 'mobile',
                ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            
            // 验证返回的单词是否匹配
            $input = strtolower($data['input'] ?? '');
            if ($input !== strtolower($word)) {
                Log::warning("有道API单词不匹配: 请求 {$word}, 返回 {$input}");
                return null;
            }
            
            $wordData = $data['ec']['word'][0] ?? $data['simple']['word'][0] ?? null;
            if (!$wordData) {
                return null;
            }
            
            // 二次验证
            $returnPhrase = strtolower($wordData['return-phrase']['l']['i'] ?? '');
            if ($returnPhrase && $returnPhrase !== strtolower($word)) {
                Log::warning("有道API单词不匹配(二次): 请求 {$word}, 返回 {$returnPhrase}");
                return null;
            }

            // 解析音标
            $phonetic = $wordData['usphone'] ?? $wordData['ukphone'] ?? null;

            // 解析释义
            $meanings = [];
            foreach ($wordData['trs'] ?? [] as $tr) {
                $items = $tr['tr'][0]['l']['i'] ?? [];
                if (is_array($items)) {
                    foreach ($items as $item) {
                        if (is_string($item) && trim($item)) {
                            $meanings[] = trim($item);
                        }
                    }
                }
            }

            $zhMeaning = implode('；', array_unique(array_slice($meanings, 0, 5)));
            
            if (empty($zhMeaning)) {
                return null;
            }

            // 解析例句
            $examples = [];
            foreach (array_slice($data['blng_sents_part']['sentence-pair'] ?? [], 0, 2) as $sent) {
                if (!empty($sent['sentence']) && !empty($sent['sentence-translation'])) {
                    $examples[] = [
                        'en' => strip_tags($sent['sentence']),
                        'zh' => strip_tags($sent['sentence-translation']),
                    ];
                }
            }

            return [
                'phonetic' => $phonetic,
                'zh_meaning' => $zhMeaning,
                'examples' => $examples,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    private function fetchFromFreeDictionary(string $word): array
    {
        try {
            $response = Http::timeout(10)
                ->get("https://api.dictionaryapi.dev/api/v2/entries/en/" . urlencode($word));

            if (!$response->successful()) {
                return [];
            }

            $data = $response->json();
            
            if (empty($data) || !is_array($data) || isset($data['title'])) {
                return [];
            }

            $entry = $data[0] ?? null;
            if (!$entry) {
                return [];
            }

            // 验证返回的单词是否匹配
            if (isset($entry['word']) && strtolower($entry['word']) !== strtolower($word)) {
                return [];
            }

            // 提取音标
            $phonetic = $entry['phonetic'] ?? null;
            if (!$phonetic && !empty($entry['phonetics'])) {
                foreach ($entry['phonetics'] as $p) {
                    if (!empty($p['text'])) {
                        $phonetic = $p['text'];
                        break;
                    }
                }
            }
            // 清理音标格式
            if ($phonetic) {
                $phonetic = trim($phonetic, '/');
            }

            // 提取英文释义和例句
            $meanings = [];
            $examples = [];
            
            if (!empty($entry['meanings'])) {
                foreach ($entry['meanings'] as $meaning) {
                    $partOfSpeech = $meaning['partOfSpeech'] ?? '';
                    $definitions = $meaning['definitions'] ?? [];
                    
                    foreach (array_slice($definitions, 0, 2) as $def) {
                        $definition = $def['definition'] ?? '';
                        if ($definition) {
                            $meanings[] = ($partOfSpeech ? "({$partOfSpeech}) " : '') . $definition;
                        }
                        
                        // 提取例句
                        if (!empty($def['example']) && count($examples) < 2) {
                            $examples[] = [
                                'en' => $def['example'],
                                'zh' => '',
                            ];
                        }
                    }
                }
            }

            return [
                'phonetic' => $phonetic,
                'en_meaning' => implode('; ', array_slice($meanings, 0, 3)),
                'examples' => $examples,
            ];
        } catch (\Exception $e) {
            return [];
        }
    }
}
