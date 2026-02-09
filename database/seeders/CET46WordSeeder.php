<?php

namespace Database\Seeders;

use App\Models\Word\Book;
use App\Models\Word\Category;
use App\Models\Word\Word;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CET46WordSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('开始导入单词数据...');
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // 创建分类和单词书
        $categories = [
            ['name' => '小学英语', 'description' => '小学英语核心词汇', 'sort_order' => 1],
            ['name' => '初中英语', 'description' => '初中英语核心词汇', 'sort_order' => 2],
            ['name' => '高中英语', 'description' => '高中英语核心词汇', 'sort_order' => 3],
            ['name' => '英语四级', 'description' => '大学英语四级词汇', 'sort_order' => 4],
            ['name' => '英语六级', 'description' => '大学英语六级词汇', 'sort_order' => 5],
        ];

        foreach ($categories as $catData) {
            $category = Category::firstOrCreate(['name' => $catData['name']], $catData);
            
            $bookName = $catData['name'] . '词汇';
            $difficulty = $catData['sort_order'];
            
            $book = Book::firstOrCreate(
                ['name' => $bookName],
                [
                    'word_category_id' => $category->id,
                    'description' => $catData['description'],
                    'difficulty' => $difficulty,
                    'sort_order' => $catData['sort_order'],
                ]
            );

            $words = $this->getWordsForLevel($catData['name']);
            $this->importWords($book, $words, $catData['name']);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $this->command->info('单词数据导入完成！');
    }

    private function importWords(Book $book, array $words, string $type): void
    {
        $this->command->info("正在导入{$type}单词 (" . count($words) . " 个)...");
        
        // 获取该书已关联的单词
        $existingWordIds = $book->words()->pluck('words.id')->toArray();
        $existingContents = Word::whereIn('id', $existingWordIds)->pluck('content')->toArray();
        
        $count = 0;
        $wordIdsToAttach = [];

        foreach ($words as $wordData) {
            $content = $wordData['word'] ?? $wordData['content'] ?? '';
            if (empty($content)) continue;
            
            // 跳过已关联到该书的单词
            if (in_array($content, $existingContents)) continue;

            // 查找或创建单词（全局唯一）
            $word = Word::firstOrCreate(
                ['content' => $content],
                [
                    'phonetic_us' => $wordData['phonetic_us'] ?? null,
                    'explanation' => $wordData['meaning'] ?? '',
                    'example_sentences' => [],
                    'difficulty' => $book->difficulty,
                    'frequency' => 3,
                ]
            );

            $wordIdsToAttach[] = $word->id;
            $count++;
        }

        // 批量关联单词到书籍
        if (!empty($wordIdsToAttach)) {
            // 分批附加，避免一次性操作太多数据
            foreach (array_chunk($wordIdsToAttach, 500) as $chunk) {
                $book->words()->syncWithoutDetaching($chunk);
            }
        }

        $book->updateWordCount();
        $this->command->info("已导入 {$count} 个{$type}单词");
    }

    private function getWordsForLevel(string $level): array
    {
        return match($level) {
            '小学英语' => $this->getPrimaryWords(),
            '初中英语' => $this->getJuniorWords(),
            '高中英语' => $this->getSeniorWords(),
            '英语四级' => $this->getCET4Words(),
            '英语六级' => $this->getCET6Words(),
            default => [],
        };
    }

    private function getPrimaryWords(): array
    {
        // 从 JSON 文件加载小学词汇
        $jsonPath = database_path('data/primary_words.json');
        if (file_exists($jsonPath)) {
            return json_decode(file_get_contents($jsonPath), true) ?? [];
        }
        
        // 备用：内置词汇
        $words = [
            // 基础词汇
            'a', 'an', 'the', 'I', 'you', 'he', 'she', 'it', 'we', 'they',
            'my', 'your', 'his', 'her', 'its', 'our', 'their', 'this', 'that', 'these',
            'am', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has',
            'had', 'do', 'does', 'did', 'will', 'would', 'can', 'could', 'may', 'might',
            'must', 'shall', 'should', 'and', 'or', 'but', 'if', 'because', 'so', 'when',
            // 数字
            'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
            'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen', 'twenty',
            'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety', 'hundred', 'thousand', 'first',
            'second', 'third', 'fourth', 'fifth', 'sixth', 'seventh', 'eighth', 'ninth', 'tenth', 'last',
            // 颜色
            'red', 'blue', 'green', 'yellow', 'black', 'white', 'pink', 'orange', 'purple', 'brown',
            'grey', 'gold', 'silver', 'color', 'dark', 'light', 'bright', 'colorful',
            // 家庭
            'family', 'father', 'mother', 'parent', 'brother', 'sister', 'son', 'daughter', 'grandfather', 'grandmother',
            'uncle', 'aunt', 'cousin', 'baby', 'child', 'children', 'boy', 'girl', 'man', 'woman',
            // 身体
            'body', 'head', 'face', 'eye', 'ear', 'nose', 'mouth', 'tooth', 'teeth', 'hair',
            'hand', 'arm', 'leg', 'foot', 'feet', 'finger', 'toe', 'neck', 'back', 'heart',
            // 动物
            'animal', 'dog', 'cat', 'bird', 'fish', 'horse', 'cow', 'pig', 'sheep', 'chicken',
            'duck', 'rabbit', 'mouse', 'tiger', 'lion', 'elephant', 'monkey', 'bear', 'snake', 'frog',
            'ant', 'bee', 'butterfly', 'spider', 'wolf', 'fox', 'deer', 'panda', 'whale', 'shark',
            // 食物
            'food', 'rice', 'noodle', 'bread', 'cake', 'egg', 'meat', 'fish', 'chicken', 'beef',
            'pork', 'vegetable', 'fruit', 'apple', 'banana', 'orange', 'grape', 'strawberry', 'watermelon', 'peach',
            'tomato', 'potato', 'carrot', 'onion', 'milk', 'water', 'juice', 'tea', 'coffee', 'ice',
            'sugar', 'salt', 'butter', 'cheese', 'soup', 'salad', 'pizza', 'hamburger', 'sandwich', 'candy',
            // 饮料和餐具
            'drink', 'cup', 'glass', 'bottle', 'bowl', 'plate', 'dish', 'spoon', 'fork', 'knife',
            'chopstick', 'breakfast', 'lunch', 'dinner', 'meal', 'hungry', 'thirsty', 'full', 'delicious', 'taste',
            // 学校
            'school', 'classroom', 'teacher', 'student', 'class', 'lesson', 'homework', 'book', 'pen', 'pencil',
            'ruler', 'eraser', 'desk', 'chair', 'blackboard', 'chalk', 'paper', 'notebook', 'bag', 'schoolbag',
            'English', 'Chinese', 'math', 'music', 'art', 'PE', 'science', 'history', 'geography', 'computer',
            // 时间
            'time', 'day', 'week', 'month', 'year', 'today', 'tomorrow', 'yesterday', 'morning', 'afternoon',
            'evening', 'night', 'noon', 'hour', 'minute', 'second', 'clock', 'watch', 'calendar', 'birthday',
            'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday', 'weekend',
            'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December',
            'spring', 'summer', 'autumn', 'fall', 'winter', 'season', 'weather', 'sunny', 'cloudy', 'rainy',
            'windy', 'snowy', 'hot', 'cold', 'warm', 'cool',
            // 地点
            'place', 'home', 'house', 'room', 'bedroom', 'bathroom', 'kitchen', 'living', 'garden', 'door',
            'window', 'wall', 'floor', 'roof', 'stairs', 'city', 'town', 'village', 'street', 'road',
            'park', 'zoo', 'farm', 'shop', 'store', 'supermarket', 'market', 'restaurant', 'hotel', 'hospital',
            'library', 'museum', 'cinema', 'theater', 'bank', 'post', 'office', 'station', 'airport', 'bus',
            // 交通
            'car', 'bus', 'train', 'plane', 'bike', 'bicycle', 'boat', 'ship', 'taxi', 'subway',
            'traffic', 'light', 'drive', 'ride', 'fly', 'walk', 'run', 'stop', 'go', 'turn',
            // 动作
            'come', 'go', 'get', 'give', 'take', 'make', 'put', 'see', 'look', 'watch',
            'hear', 'listen', 'say', 'speak', 'talk', 'tell', 'ask', 'answer', 'read', 'write',
            'draw', 'sing', 'dance', 'play', 'work', 'study', 'learn', 'teach', 'think', 'know',
            'understand', 'remember', 'forget', 'try', 'help', 'want', 'need', 'like', 'love', 'hate',
            'open', 'close', 'turn', 'start', 'begin', 'end', 'finish', 'wait', 'find', 'lose',
            'buy', 'sell', 'pay', 'cost', 'spend', 'save', 'use', 'eat', 'drink', 'sleep',
            'wake', 'stand', 'sit', 'lie', 'jump', 'swim', 'climb', 'throw', 'catch', 'kick',
            'hit', 'pull', 'push', 'carry', 'hold', 'drop', 'pick', 'cut', 'break', 'fix',
            'clean', 'wash', 'brush', 'cook', 'bake', 'grow', 'plant', 'water', 'feed', 'keep',
            // 形容词
            'good', 'bad', 'great', 'nice', 'fine', 'beautiful', 'pretty', 'ugly', 'cute', 'lovely',
            'big', 'small', 'large', 'little', 'tall', 'short', 'long', 'high', 'low', 'wide',
            'new', 'old', 'young', 'fast', 'slow', 'quick', 'early', 'late', 'easy', 'hard',
            'difficult', 'simple', 'happy', 'sad', 'angry', 'afraid', 'tired', 'sick', 'healthy', 'strong',
            'weak', 'busy', 'free', 'full', 'empty', 'rich', 'poor', 'cheap', 'expensive', 'same',
            'different', 'special', 'important', 'interesting', 'boring', 'funny', 'strange', 'wonderful', 'terrible', 'wrong',
            'right', 'true', 'false', 'real', 'ready', 'sure', 'safe', 'dangerous', 'clean', 'dirty',
            // 方位
            'up', 'down', 'in', 'out', 'on', 'off', 'over', 'under', 'above', 'below',
            'front', 'back', 'left', 'right', 'middle', 'center', 'side', 'corner', 'top', 'bottom',
            'inside', 'outside', 'near', 'far', 'here', 'there', 'where', 'everywhere', 'somewhere', 'nowhere',
            // 其他常用词
            'thing', 'stuff', 'way', 'kind', 'type', 'part', 'piece', 'bit', 'lot', 'all',
            'some', 'any', 'no', 'none', 'much', 'many', 'more', 'most', 'few', 'little',
            'enough', 'too', 'very', 'really', 'quite', 'just', 'only', 'also', 'again', 'still',
            'already', 'yet', 'ever', 'never', 'always', 'usually', 'often', 'sometimes', 'seldom', 'hardly',
            'maybe', 'perhaps', 'probably', 'certainly', 'of course', 'yes', 'no', 'not', 'please', 'thank',
            'sorry', 'excuse', 'hello', 'hi', 'bye', 'goodbye', 'welcome', 'OK', 'well', 'wow',
            // 节日和活动
            'holiday', 'vacation', 'festival', 'Christmas', 'Easter', 'Halloween', 'party', 'game', 'sport', 'football',
            'basketball', 'tennis', 'ping-pong', 'swimming', 'running', 'skating', 'skiing', 'fishing', 'camping', 'trip',
            'travel', 'visit', 'picnic', 'gift', 'present', 'card', 'photo', 'picture', 'movie', 'show',
            // 自然
            'nature', 'world', 'earth', 'sky', 'sun', 'moon', 'star', 'cloud', 'rain', 'snow',
            'wind', 'air', 'fire', 'water', 'sea', 'ocean', 'river', 'lake', 'mountain', 'hill',
            'tree', 'flower', 'grass', 'leaf', 'forest', 'field', 'land', 'island', 'beach', 'sand',
            'stone', 'rock', 'wood', 'metal', 'gold', 'silver', 'iron', 'glass', 'plastic', 'paper',
            // 衣物
            'clothes', 'coat', 'jacket', 'shirt', 'T-shirt', 'sweater', 'dress', 'skirt', 'pants', 'jeans',
            'shorts', 'sock', 'shoe', 'boot', 'hat', 'cap', 'scarf', 'glove', 'glasses', 'umbrella',
            'bag', 'pocket', 'button', 'zipper', 'belt', 'tie', 'uniform', 'fashion', 'style', 'wear',
            // 职业
            'job', 'work', 'worker', 'doctor', 'nurse', 'teacher', 'driver', 'farmer', 'cook', 'waiter',
            'police', 'fireman', 'soldier', 'artist', 'singer', 'actor', 'writer', 'scientist', 'engineer', 'pilot',
        ];

        return array_map(fn($w) => ['content' => $w, 'meaning' => ''], array_unique($words));
    }

    private function getJuniorWords(): array
    {
        // 初中核心词汇 ~1600词 (在小学基础上扩展)
        $words = [
            // 动词扩展
            'accept', 'achieve', 'act', 'add', 'admit', 'advise', 'afford', 'agree', 'allow', 'appear',
            'apply', 'argue', 'arrange', 'arrive', 'attack', 'attend', 'attract', 'avoid', 'beat', 'become',
            'believe', 'belong', 'blow', 'borrow', 'bother', 'build', 'burn', 'call', 'care', 'cause',
            'celebrate', 'change', 'charge', 'chase', 'check', 'choose', 'claim', 'clear', 'collect', 'combine',
            'comfort', 'communicate', 'compare', 'compete', 'complain', 'complete', 'concern', 'conclude', 'confirm', 'connect',
            'consider', 'contain', 'continue', 'control', 'convince', 'copy', 'correct', 'count', 'cover', 'create',
            'cross', 'cry', 'damage', 'deal', 'decide', 'declare', 'decorate', 'decrease', 'defend', 'delay',
            'deliver', 'demand', 'depend', 'describe', 'design', 'desire', 'destroy', 'determine', 'develop', 'die',
            'dig', 'direct', 'disappear', 'discover', 'discuss', 'divide', 'doubt', 'dream', 'dress', 'earn',
            'enable', 'encourage', 'enjoy', 'enter', 'escape', 'examine', 'exchange', 'excite', 'exist', 'expect',
            'experience', 'experiment', 'explain', 'explore', 'express', 'extend', 'face', 'fail', 'fall', 'fear',
            'feel', 'fight', 'fill', 'fit', 'flow', 'focus', 'follow', 'force', 'form', 'gain',
            'gather', 'generate', 'grab', 'graduate', 'greet', 'grow', 'guard', 'guess', 'guide', 'hang',
            'happen', 'hide', 'hope', 'hunt', 'hurt', 'identify', 'ignore', 'imagine', 'improve', 'include',
            'increase', 'indicate', 'influence', 'inform', 'insist', 'inspire', 'intend', 'introduce', 'invent', 'invite',
            'involve', 'join', 'judge', 'kill', 'knock', 'lack', 'land', 'last', 'laugh', 'lay',
            'lead', 'leave', 'lend', 'let', 'lift', 'limit', 'link', 'list', 'live', 'load',
            'lock', 'manage', 'mark', 'match', 'matter', 'measure', 'meet', 'mention', 'mind', 'miss',
            'mix', 'move', 'notice', 'obtain', 'occur', 'offer', 'operate', 'order', 'organize', 'own',
            'pack', 'park', 'pass', 'perform', 'permit', 'persuade', 'place', 'plan', 'point', 'pollute',
            'post', 'pour', 'practice', 'praise', 'predict', 'prefer', 'prepare', 'present', 'press', 'pretend',
            'prevent', 'print', 'produce', 'promise', 'pronounce', 'protect', 'prove', 'provide', 'publish', 'punish',
            'purchase', 'pursue', 'raise', 'reach', 'react', 'realize', 'receive', 'recognize', 'recommend', 'record',
            'recover', 'reduce', 'reflect', 'refuse', 'regard', 'regret', 'relate', 'relax', 'release', 'remain',
            'remind', 'remove', 'repair', 'repeat', 'replace', 'reply', 'report', 'represent', 'request', 'require',
            'research', 'respect', 'respond', 'rest', 'return', 'reveal', 'review', 'rise', 'risk', 'roll',
            'rush', 'satisfy', 'scare', 'search', 'seat', 'seem', 'select', 'send', 'sense', 'separate',
            'serve', 'set', 'settle', 'shake', 'shape', 'share', 'shine', 'shock', 'shoot', 'shout',
            'show', 'shut', 'sign', 'signal', 'sink', 'smell', 'smile', 'solve', 'sort', 'sound',
            'spare', 'spell', 'split', 'spread', 'steal', 'stick', 'store', 'strike', 'struggle', 'succeed',
            'suffer', 'suggest', 'suit', 'supply', 'support', 'suppose', 'surprise', 'surround', 'survive', 'suspect',
            'sweep', 'swing', 'switch', 'target', 'test', 'thank', 'threaten', 'tie', 'touch', 'track',
            'train', 'transfer', 'translate', 'trap', 'treat', 'trick', 'trust', 'type', 'unite', 'update',
            'upset', 'urge', 'value', 'vary', 'view', 'vote', 'warn', 'waste', 'weigh', 'win',
            'wish', 'wonder', 'worry', 'wrap', 'yell',
            // 名词扩展
            'ability', 'accident', 'account', 'achievement', 'action', 'activity', 'addition', 'address', 'adult', 'advantage',
            'adventure', 'advertisement', 'advice', 'affair', 'affect', 'age', 'agreement', 'aim', 'alarm', 'album',
            'amount', 'ancestor', 'anger', 'angle', 'announcement', 'anxiety', 'apartment', 'appearance', 'application', 'appointment',
            'area', 'argument', 'army', 'arrangement', 'arrival', 'article', 'artist', 'aspect', 'assignment', 'assistant',
            'association', 'atmosphere', 'attention', 'attitude', 'audience', 'author', 'authority', 'average', 'award', 'background',
            'balance', 'band', 'base', 'basis', 'battle', 'beauty', 'beginning', 'behavior', 'belief', 'benefit',
            'birth', 'blank', 'block', 'blood', 'board', 'bomb', 'bone', 'border', 'boss', 'bottom',
            'brain', 'branch', 'brand', 'breath', 'brick', 'bridge', 'broadcast', 'budget', 'building', 'bullet',
            'burden', 'bus', 'bush', 'business', 'cable', 'camera', 'camp', 'campaign', 'cancer', 'candidate',
            'capital', 'captain', 'career', 'carpet', 'cartoon', 'case', 'cash', 'cast', 'castle', 'cause',
            'ceiling', 'cell', 'century', 'ceremony', 'chain', 'chair', 'challenge', 'champion', 'chance', 'channel',
            'chapter', 'character', 'charge', 'charity', 'chart', 'check', 'chest', 'childhood', 'choice', 'circle',
            'circuit', 'citizen', 'claim', 'climate', 'clinic', 'cloth', 'clothes', 'cloud', 'club', 'clue',
            'coach', 'coal', 'coast', 'code', 'coin', 'collection', 'college', 'colony', 'column', 'comedy',
            'comment', 'committee', 'communication', 'community', 'companion', 'company', 'comparison', 'competition', 'complaint', 'complex',
            'computer', 'concentration', 'concept', 'concern', 'conclusion', 'condition', 'conference', 'confidence', 'conflict', 'confusion',
            'connection', 'consequence', 'consideration', 'construction', 'contact', 'content', 'contest', 'context', 'contract', 'contrast',
            'contribution', 'control', 'conversation', 'cook', 'cookie', 'copy', 'corner', 'cost', 'cottage', 'cotton',
            'council', 'counter', 'country', 'couple', 'courage', 'course', 'court', 'cousin', 'cover', 'cow',
            'crash', 'cream', 'creation', 'creature', 'credit', 'crime', 'criminal', 'crisis', 'criticism', 'crop',
            'crowd', 'crown', 'culture', 'cup', 'cure', 'curiosity', 'currency', 'custom', 'customer', 'cycle',
            // 形容词扩展
            'absent', 'abstract', 'academic', 'acceptable', 'accessible', 'accurate', 'active', 'actual', 'additional', 'adequate',
            'advanced', 'afraid', 'aggressive', 'alive', 'alone', 'amazing', 'ancient', 'annoyed', 'annual', 'anxious',
            'apparent', 'appropriate', 'artificial', 'ashamed', 'asleep', 'attractive', 'available', 'average', 'aware', 'awful',
            'backward', 'bare', 'basic', 'bitter', 'blind', 'bold', 'bored', 'boring', 'brave', 'brief',
            'brilliant', 'broad', 'broken', 'calm', 'capable', 'careful', 'careless', 'casual', 'central', 'certain',
            'cheap', 'chemical', 'chief', 'civil', 'classic', 'classical', 'clever', 'close', 'comfortable', 'common',
            'complete', 'complex', 'concerned', 'confident', 'confused', 'conscious', 'constant', 'contemporary', 'content', 'convenient',
            'cool', 'correct', 'crazy', 'creative', 'critical', 'cruel', 'cultural', 'curious', 'current', 'daily',
            'damp', 'dead', 'deaf', 'dear', 'decent', 'deep', 'definite', 'delicate', 'democratic', 'dependent',
            'desperate', 'detailed', 'determined', 'digital', 'direct', 'dirty', 'disabled', 'disappointed', 'distant', 'distinct',
            'double', 'dramatic', 'drunk', 'dry', 'due', 'dull', 'eager', 'economic', 'educational', 'effective',
            'efficient', 'elderly', 'electric', 'electronic', 'elegant', 'elementary', 'embarrassed', 'emotional', 'empty', 'entire',
            'environmental', 'equal', 'essential', 'even', 'eventual', 'evident', 'evil', 'exact', 'excellent', 'excited',
            'exciting', 'existing', 'expensive', 'experienced', 'expert', 'extra', 'extraordinary', 'extreme', 'fair', 'false',
            'familiar', 'famous', 'fancy', 'fantastic', 'fat', 'fatal', 'favorite', 'federal', 'fellow', 'female',
            'fierce', 'final', 'financial', 'firm', 'fit', 'fixed', 'flat', 'flexible', 'fluent', 'fond',
            'foolish', 'foreign', 'formal', 'former', 'fortunate', 'forward', 'fragile', 'frank', 'free', 'frequent',
            'fresh', 'friendly', 'frightened', 'frustrated', 'fundamental', 'funny', 'further', 'future', 'general', 'generous',
            'gentle', 'genuine', 'giant', 'glad', 'global', 'good', 'gorgeous', 'gradual', 'grand', 'grateful',
            'gray', 'greedy', 'gross', 'growing', 'guilty', 'handsome', 'handy', 'harmful', 'healthy', 'heavy',
            // 副词扩展
            'absolutely', 'accidentally', 'accordingly', 'actually', 'additionally', 'admittedly', 'afterwards', 'almost', 'altogether', 'anyway',
            'apparently', 'approximately', 'automatically', 'badly', 'barely', 'basically', 'briefly', 'broadly', 'carefully', 'certainly',
            'clearly', 'closely', 'commonly', 'completely', 'constantly', 'currently', 'deeply', 'definitely', 'deliberately', 'directly',
            'easily', 'effectively', 'efficiently', 'entirely', 'equally', 'especially', 'essentially', 'eventually', 'exactly', 'extremely',
            'fairly', 'finally', 'firmly', 'firstly', 'formerly', 'fortunately', 'frankly', 'freely', 'frequently', 'fully',
            'generally', 'gently', 'gradually', 'greatly', 'happily', 'hardly', 'heavily', 'highly', 'honestly', 'hopefully',
            'immediately', 'importantly', 'increasingly', 'indeed', 'independently', 'initially', 'instead', 'largely', 'lately', 'lightly',
            'likely', 'literally', 'locally', 'mainly', 'merely', 'mostly', 'naturally', 'nearly', 'necessarily', 'normally',
            'notably', 'obviously', 'occasionally', 'officially', 'originally', 'otherwise', 'partly', 'particularly', 'perfectly', 'permanently',
            'personally', 'physically', 'possibly', 'potentially', 'practically', 'precisely', 'preferably', 'previously', 'primarily', 'probably',
            // 其他词汇
            'abroad', 'absence', 'accent', 'access', 'accommodation', 'accompany', 'according', 'accurate', 'accuse', 'acre',
            'adapt', 'addition', 'adequate', 'adjust', 'administration', 'admire', 'admission', 'adolescent', 'adopt', 'advance',
            'advantage', 'advertise', 'affect', 'affection', 'agency', 'agent', 'agriculture', 'aid', 'AIDS', 'aircraft',
            'airline', 'alcohol', 'alien', 'alliance', 'allocate', 'alongside', 'alternative', 'altogether', 'aluminum', 'amateur',
            'ambassador', 'ambition', 'amendment', 'amid', 'analysis', 'analyze', 'ancient', 'anniversary', 'announce', 'annual',
            'anticipate', 'anxiety', 'apart', 'apologize', 'apparent', 'appeal', 'appetite', 'applause', 'applicable', 'appreciation',
            'approach', 'appropriate', 'approval', 'approximate', 'architect', 'architecture', 'arise', 'arithmetic', 'armed', 'arrangement',
        ];

        return array_map(fn($w) => ['content' => $w, 'meaning' => ''], array_unique($words));
    }

    private function getSeniorWords(): array
    {
        // 高中核心词汇 ~3500词
        $words = [
            'abandon', 'abnormal', 'abolish', 'abortion', 'abound', 'abrupt', 'absence', 'absolute', 'absorb', 'abstract',
            'absurd', 'abundance', 'abuse', 'academic', 'accelerate', 'accent', 'acceptable', 'acceptance', 'access', 'accessible',
            'accident', 'accommodation', 'accompany', 'accomplish', 'accord', 'accordance', 'account', 'accountant', 'accumulate', 'accuracy',
            'accurate', 'accuse', 'accustom', 'ache', 'achieve', 'achievement', 'acid', 'acknowledge', 'acquaint', 'acquire',
            'acquisition', 'acre', 'act', 'action', 'activate', 'active', 'activity', 'actor', 'actress', 'actual',
            'acute', 'adapt', 'adaptation', 'add', 'addict', 'addition', 'additional', 'address', 'adequate', 'adhere',
            'adjacent', 'adjective', 'adjust', 'adjustment', 'administer', 'administration', 'admirable', 'admire', 'admission', 'admit',
            'adolescent', 'adopt', 'adoption', 'adult', 'advance', 'advanced', 'advantage', 'adventure', 'adverb', 'adverse',
            'advertise', 'advertisement', 'advice', 'advisable', 'advise', 'adviser', 'advocate', 'aerial', 'aesthetic', 'affair',
            'affect', 'affection', 'affiliate', 'affirm', 'afford', 'afraid', 'afterward', 'age', 'agency', 'agenda',
            'agent', 'aggravate', 'aggressive', 'agony', 'agree', 'agreement', 'agriculture', 'ahead', 'aid', 'aim',
            'aircraft', 'airline', 'airport', 'alarm', 'album', 'alcohol', 'alert', 'alien', 'alike', 'alive',
            'alliance', 'allocate', 'allow', 'allowance', 'ally', 'almost', 'alone', 'alongside', 'aloud', 'alphabet',
            'already', 'also', 'alter', 'alternative', 'although', 'altitude', 'altogether', 'aluminum', 'always', 'amateur',
            'amaze', 'ambassador', 'ambiguous', 'ambition', 'ambulance', 'amend', 'amid', 'among', 'amount', 'ample',
            'amuse', 'analyze', 'analysis', 'analyst', 'ancestor', 'anchor', 'ancient', 'anger', 'angle', 'angry',
            'anguish', 'animal', 'ankle', 'anniversary', 'announce', 'announcement', 'annoy', 'annual', 'anonymous', 'another',
            'answer', 'ant', 'anticipate', 'antique', 'anxiety', 'anxious', 'any', 'anybody', 'anyhow', 'anyone',
            'anything', 'anyway', 'anywhere', 'apart', 'apartment', 'apologize', 'apology', 'apparatus', 'apparent', 'appeal',
            'appear', 'appearance', 'appetite', 'applaud', 'applause', 'apple', 'appliance', 'applicable', 'applicant', 'application',
            'apply', 'appoint', 'appointment', 'appreciate', 'appreciation', 'approach', 'appropriate', 'approval', 'approve', 'approximate',
            'arbitrary', 'architect', 'architecture', 'area', 'argue', 'argument', 'arise', 'arithmetic', 'arm', 'armed',
            'army', 'around', 'arouse', 'arrange', 'arrangement', 'array', 'arrest', 'arrival', 'arrive', 'arrogant',
            'arrow', 'art', 'article', 'artificial', 'artist', 'artistic', 'as', 'ascend', 'ash', 'ashamed',
            'aside', 'ask', 'aspect', 'aspire', 'assemble', 'assembly', 'assert', 'assess', 'assessment', 'asset',
            'assign', 'assignment', 'assist', 'assistance', 'assistant', 'associate', 'association', 'assume', 'assumption', 'assure',
            'astonish', 'astronaut', 'astronomy', 'at', 'athlete', 'athletic', 'atmosphere', 'atom', 'atomic', 'attach',
            'attack', 'attain', 'attempt', 'attend', 'attendance', 'attention', 'attitude', 'attorney', 'attract', 'attraction',
            'attractive', 'attribute', 'auction', 'audience', 'audio', 'audit', 'author', 'authority', 'authorize', 'auto',
            'automatic', 'automobile', 'autonomous', 'autumn', 'auxiliary', 'available', 'avenue', 'average', 'avoid', 'await',
            'awake', 'award', 'aware', 'awareness', 'away', 'awful', 'awkward', 'ax', 'axis', 'baby',
            'bachelor', 'back', 'background', 'backward', 'bacteria', 'bad', 'badge', 'badly', 'bag', 'baggage',
            'bake', 'balance', 'balcony', 'ball', 'balloon', 'ballot', 'ban', 'banana', 'band', 'bandage',
            'bang', 'bank', 'banker', 'bankrupt', 'banner', 'bar', 'barber', 'bare', 'barely', 'bargain',
            'bark', 'barn', 'barrel', 'barrier', 'base', 'baseball', 'basement', 'basic', 'basin', 'basis',
            'basket', 'basketball', 'bat', 'batch', 'bath', 'bathe', 'bathroom', 'battery', 'battle', 'bay',
            'beach', 'bead', 'beam', 'bean', 'bear', 'beard', 'beast', 'beat', 'beautiful', 'beauty',
            'because', 'become', 'bed', 'bedroom', 'bee', 'beef', 'beer', 'before', 'beg', 'beggar',
            'begin', 'beginning', 'behalf', 'behave', 'behavior', 'behind', 'being', 'belief', 'believe', 'bell',
            'belong', 'beloved', 'below', 'belt', 'bench', 'bend', 'beneath', 'beneficial', 'benefit', 'beside',
            'besides', 'best', 'bet', 'betray', 'better', 'between', 'beyond', 'bias', 'Bible', 'bicycle',
            'bid', 'big', 'bike', 'bill', 'billion', 'bind', 'biography', 'biology', 'bird', 'birth',
            'birthday', 'biscuit', 'bit', 'bite', 'bitter', 'black', 'blade', 'blame', 'blank', 'blanket',
            'blast', 'blaze', 'bleed', 'blend', 'bless', 'blind', 'block', 'blood', 'bloody', 'bloom',
            'blow', 'blue', 'board', 'boast', 'boat', 'body', 'boil', 'bold', 'bolt', 'bomb',
            'bond', 'bone', 'bonus', 'book', 'boom', 'boost', 'boot', 'booth', 'border', 'bore',
            'boring', 'born', 'borrow', 'boss', 'both', 'bother', 'bottle', 'bottom', 'bounce', 'bound',
            'boundary', 'bow', 'bowl', 'box', 'boy', 'boycott', 'brain', 'brake', 'branch', 'brand',
            'brass', 'brave', 'bread', 'breadth', 'break', 'breakdown', 'breakfast', 'breakthrough', 'breast', 'breath',
            'breathe', 'breed', 'breeze', 'brick', 'bride', 'bridge', 'brief', 'bright', 'brilliant', 'bring',
            'brisk', 'broad', 'broadcast', 'brochure', 'broke', 'broken', 'bronze', 'brook', 'broom', 'brother',
            'brow', 'brown', 'bruise', 'brush', 'brutal', 'bubble', 'bucket', 'bud', 'budget', 'bug',
            'build', 'building', 'bulk', 'bullet', 'bulletin', 'bump', 'bunch', 'bundle', 'burden', 'bureau',
            'bureaucracy', 'burn', 'burst', 'bury', 'bus', 'bush', 'business', 'busy', 'but', 'butter',
            'button', 'buy', 'buyer', 'by', 'bypass', 'cab', 'cabbage', 'cabin', 'cabinet', 'cable',
            'cafe', 'cage', 'cake', 'calculate', 'calculation', 'calendar', 'call', 'calm', 'camel', 'camera',
            'camp', 'campaign', 'campus', 'can', 'canal', 'cancel', 'cancer', 'candidate', 'candle', 'candy',
            'cannon', 'canoe', 'canvas', 'cap', 'capable', 'capacity', 'capital', 'captain', 'capture', 'car',
            'carbon', 'card', 'care', 'career', 'careful', 'careless', 'cargo', 'carnival', 'carpet', 'carriage',
            'carrier', 'carrot', 'carry', 'cart', 'cartoon', 'carve', 'case', 'cash', 'cashier', 'casino',
            'cast', 'castle', 'casual', 'cat', 'catalog', 'catch', 'category', 'cater', 'cathedral', 'cattle',
            'cause', 'caution', 'cautious', 'cave', 'cease', 'ceiling', 'celebrate', 'celebration', 'celebrity', 'cell',
            'cellar', 'cement', 'cemetery', 'census', 'cent', 'center', 'central', 'century', 'ceremony', 'certain',
            'certainly', 'certainty', 'certificate', 'chain', 'chair', 'chairman', 'chalk', 'challenge', 'chamber', 'champion',
            'championship', 'chance', 'change', 'channel', 'chaos', 'chapter', 'character', 'characteristic', 'charge', 'charity',
            'charm', 'chart', 'chase', 'chat', 'cheap', 'cheat', 'check', 'cheek', 'cheer', 'cheerful',
            'cheese', 'chemical', 'chemist', 'chemistry', 'cheque', 'cherry', 'chess', 'chest', 'chew', 'chicken',
            'chief', 'child', 'childhood', 'childish', 'chill', 'chimney', 'chin', 'china', 'chip', 'chocolate',
            'choice', 'choir', 'choke', 'choose', 'chop', 'chorus', 'Christian', 'Christmas', 'church', 'cigar',
            'cigarette', 'cinema', 'circle', 'circuit', 'circulate', 'circulation', 'circumstance', 'circus', 'cite', 'citizen',
            'citizenship', 'city', 'civil', 'civilian', 'civilization', 'claim', 'clap', 'clarify', 'clarity', 'clash',
            'clasp', 'class', 'classic', 'classical', 'classify', 'classmate', 'classroom', 'clause', 'claw', 'clay',
            'clean', 'clear', 'clerk', 'clever', 'click', 'client', 'cliff', 'climate', 'climb', 'cling',
            'clinic', 'clip', 'cloak', 'clock', 'clone', 'close', 'closet', 'cloth', 'clothe', 'clothes',
            'clothing', 'cloud', 'cloudy', 'club', 'clue', 'clumsy', 'cluster', 'coach', 'coal', 'coarse',
            'coast', 'coastal', 'coat', 'code', 'coffee', 'cognitive', 'coherent', 'coil', 'coin', 'coincide',
            'coincidence', 'cold', 'collapse', 'collar', 'colleague', 'collect', 'collection', 'collective', 'college', 'collide',
            'collision', 'colonel', 'colonial', 'colony', 'color', 'column', 'comb', 'combat', 'combination', 'combine',
            'come', 'comedy', 'comfort', 'comfortable', 'comic', 'command', 'commander', 'comment', 'commerce', 'commercial',
            'commission', 'commit', 'commitment', 'committee', 'commodity', 'common', 'communicate', 'communication', 'communist', 'community',
            'companion', 'company', 'comparable', 'comparative', 'compare', 'comparison', 'compartment', 'compass', 'compel', 'compensate',
            'compensation', 'compete', 'competent', 'competition', 'competitive', 'competitor', 'compile', 'complain', 'complaint', 'complement',
            'complete', 'complex', 'complexity', 'compliance', 'complicate', 'complicated', 'component', 'compose', 'composer', 'composition',
            'compound', 'comprehensive', 'comprise', 'compromise', 'compulsory', 'compute', 'computer', 'conceal', 'concede', 'conceive',
            'concentrate', 'concentration', 'concept', 'conception', 'concern', 'concerning', 'concert', 'conclude', 'conclusion', 'concrete',
            'condemn', 'condition', 'conduct', 'conductor', 'conference', 'confess', 'confession', 'confidence', 'confident', 'confine',
            'confirm', 'conflict', 'conform', 'confront', 'confuse', 'confusion', 'congratulate', 'congress', 'conjunction', 'connect',
            'connection', 'conquer', 'conquest', 'conscience', 'conscious', 'consciousness', 'consensus', 'consent', 'consequence', 'consequent',
            'conservative', 'consider', 'considerable', 'considerate', 'consideration', 'consist', 'consistent', 'console', 'constant', 'constitute',
            'constitution', 'construct', 'construction', 'consult', 'consultant', 'consume', 'consumer', 'consumption', 'contact', 'contain',
            'container', 'contemporary', 'contempt', 'content', 'contest', 'context', 'continent', 'continual', 'continue', 'continuous',
            'contract', 'contradict', 'contrary', 'contrast', 'contribute', 'contribution', 'control', 'controversial', 'controversy', 'convenience',
            'convenient', 'convention', 'conventional', 'conversation', 'convert', 'convey', 'convict', 'conviction', 'convince', 'cook',
            'cookie', 'cool', 'cooperate', 'cooperation', 'cooperative', 'coordinate', 'cope', 'copper', 'copy', 'copyright',
            'coral', 'cord', 'core', 'corn', 'corner', 'corporate', 'corporation', 'correct', 'correction', 'correspond',
            'correspondence', 'correspondent', 'corresponding', 'corridor', 'corrupt', 'corruption', 'cost', 'costly', 'costume', 'cottage',
            'cotton', 'couch', 'cough', 'could', 'council', 'count', 'counter', 'counterpart', 'country', 'countryside',
            'county', 'couple', 'courage', 'courageous', 'course', 'court', 'courtesy', 'cousin', 'cover', 'coverage',
            'cow', 'coward', 'crack', 'cradle', 'craft', 'crash', 'crawl', 'crazy', 'cream', 'create',
            'creation', 'creative', 'creature', 'credit', 'creep', 'crew', 'crime', 'criminal', 'crisis', 'crisp',
            'criterion', 'critic', 'critical', 'criticism', 'criticize', 'crop', 'cross', 'crowd', 'crown', 'crucial',
            'crude', 'cruel', 'cruise', 'crush', 'cry', 'crystal', 'cube', 'cucumber', 'cultivate', 'cultural',
            'culture', 'cunning', 'cup', 'cupboard', 'curb', 'cure', 'curiosity', 'curious', 'curl', 'currency',
            'current', 'curriculum', 'curse', 'curtain', 'curve', 'cushion', 'custom', 'customer', 'customs', 'cut',
            'cute', 'cycle', 'dad', 'daily', 'dairy', 'dam', 'damage', 'damp', 'dance', 'danger',
            'dangerous', 'dare', 'dark', 'darkness', 'darling', 'dash', 'data', 'database', 'date', 'daughter',
            'dawn', 'day', 'daylight', 'dead', 'deadline', 'deadly', 'deaf', 'deal', 'dealer', 'dear',
            'death', 'debate', 'debt', 'decade', 'decay', 'deceive', 'December', 'decent', 'decide', 'decision',
            'deck', 'declaration', 'declare', 'decline', 'decorate', 'decoration', 'decrease', 'dedicate', 'deed', 'deem',
            'deep', 'deer', 'defeat', 'defect', 'defend', 'defense', 'defensive', 'deficiency', 'deficit', 'define',
            'definite', 'definitely', 'definition', 'degree', 'delay', 'delegate', 'delete', 'deliberate', 'delicate', 'delicious',
            'delight', 'deliver', 'delivery', 'demand', 'democracy', 'democratic', 'demonstrate', 'demonstration', 'denial', 'dense',
            'density', 'deny', 'depart', 'department', 'departure', 'depend', 'dependent', 'deposit', 'depress', 'depression',
            'deprive', 'depth', 'deputy', 'derive', 'descend', 'describe', 'description', 'desert', 'deserve', 'design',
            'designate', 'designer', 'desirable', 'desire', 'desk', 'desperate', 'despite', 'destination', 'destiny', 'destroy',
            'destruction', 'detail', 'detailed', 'detect', 'detective', 'determination', 'determine', 'develop', 'development', 'device',
            'devil', 'devise', 'devote', 'devotion', 'diagnose', 'diagnosis', 'diagram', 'dial', 'dialect', 'dialogue',
            'diameter', 'diamond', 'diary', 'dictate', 'dictation', 'dictionary', 'die', 'diet', 'differ', 'difference',
            'different', 'differentiate', 'difficult', 'difficulty', 'dig', 'digest', 'digital', 'dignity', 'dilemma', 'dimension',
            'diminish', 'dine', 'dinner', 'dinosaur', 'dioxide', 'dip', 'diploma', 'diplomat', 'diplomatic', 'direct',
            'direction', 'directly', 'director', 'directory', 'dirt', 'dirty', 'disable', 'disadvantage', 'disagree', 'disappear',
            'disappoint', 'disappointment', 'disaster', 'discard', 'discharge', 'discipline', 'disclose', 'discount', 'discourse', 'discover',
            'discovery', 'discrete', 'discrimination', 'discuss', 'discussion', 'disease', 'disguise', 'dish', 'disk', 'dislike',
            'dismiss', 'disorder', 'dispatch', 'disperse', 'display', 'disposal', 'dispose', 'dispute', 'dissolve', 'distance',
            'distant', 'distinct', 'distinction', 'distinguish', 'distort', 'distract', 'distress', 'distribute', 'distribution', 'district',
            'disturb', 'ditch', 'dive', 'diverse', 'diversity', 'divide', 'divine', 'division', 'divorce', 'do',
            'dock', 'doctor', 'document', 'documentary', 'dog', 'doll', 'dollar', 'domain', 'domestic', 'dominant',
            'dominate', 'donate', 'donation', 'donkey', 'door', 'dormitory', 'dose', 'dot', 'double', 'doubt',
            'doubtful', 'dough', 'down', 'download', 'downstairs', 'downtown', 'dozen', 'draft', 'drag', 'dragon',
            'drain', 'drama', 'dramatic', 'draw', 'drawer', 'drawing', 'dread', 'dream', 'dress', 'drift',
            'drill', 'drink', 'drip', 'drive', 'driver', 'drop', 'drought', 'drown', 'drug', 'drum',
            'drunk', 'dry', 'duck', 'due', 'dull', 'dumb', 'dump', 'duplicate', 'durable', 'duration',
            'during', 'dusk', 'dust', 'dusty', 'duty', 'dwell', 'dynamic', 'each', 'eager', 'eagle',
            'ear', 'early', 'earn', 'earnest', 'earnings', 'earth', 'earthquake', 'ease', 'easily', 'east',
            'eastern', 'easy', 'eat', 'echo', 'eclipse', 'ecology', 'economic', 'economical', 'economics', 'economist',
            'economy', 'edge', 'edit', 'edition', 'editor', 'educate', 'education', 'educational', 'effect', 'effective',
            'efficiency', 'efficient', 'effort', 'egg', 'ego', 'eight', 'eighteen', 'eighty', 'either', 'elaborate',
            'elastic', 'elbow', 'elder', 'elderly', 'elect', 'election', 'electric', 'electrical', 'electricity', 'electron',
            'electronic', 'elegant', 'element', 'elementary', 'elephant', 'elevate', 'elevator', 'eleven', 'eliminate', 'elite',
            'else', 'elsewhere', 'email', 'embark', 'embarrass', 'embassy', 'embrace', 'emerge', 'emergency', 'emission',
            'emit', 'emotion', 'emotional', 'emperor', 'emphasis', 'emphasize', 'empire', 'employ', 'employee', 'employer',
            'employment', 'empty', 'enable', 'encounter', 'encourage', 'end', 'endeavor', 'ending', 'endless', 'endure',
            'enemy', 'energy', 'enforce', 'engage', 'engagement', 'engine', 'engineer', 'engineering', 'English', 'enhance',
            'enjoy', 'enjoyable', 'enormous', 'enough', 'enrich', 'enroll', 'ensure', 'enter', 'enterprise', 'entertain',
            'entertainment', 'enthusiasm', 'enthusiastic', 'entire', 'entirely', 'entitle', 'entity', 'entrance', 'entry', 'envelope',
            'environment', 'environmental', 'envy', 'episode', 'equal', 'equality', 'equally', 'equate', 'equation', 'equip',
            'equipment', 'equivalent', 'era', 'erase', 'erect', 'error', 'erupt', 'escape', 'especially', 'essay',
            'essence', 'essential', 'establish', 'establishment', 'estate', 'estimate', 'eternal', 'ethical', 'ethics', 'ethnic',
            'evaluate', 'evaluation', 'eve', 'even', 'evening', 'event', 'eventual', 'eventually', 'ever', 'every',
            'everybody', 'everyday', 'everyone', 'everything', 'everywhere', 'evidence', 'evident', 'evil', 'evolution', 'evolve',
            'exact', 'exactly', 'exaggerate', 'exam', 'examination', 'examine', 'example', 'exceed', 'excellent', 'except',
            'exception', 'excess', 'excessive', 'exchange', 'excite', 'excitement', 'exciting', 'exclaim', 'exclude', 'exclusive',
            'excuse', 'execute', 'executive', 'exercise', 'exert', 'exhaust', 'exhibit', 'exhibition', 'exist', 'existence',
            'existing', 'exit', 'exotic', 'expand', 'expansion', 'expect', 'expectation', 'expedition', 'expense', 'expensive',
            'experience', 'experiment', 'experimental', 'expert', 'expertise', 'explain', 'explanation', 'explicit', 'explode', 'exploit',
            'exploration', 'explore', 'explosion', 'export', 'expose', 'exposure', 'express', 'expression', 'extend', 'extension',
            'extensive', 'extent', 'external', 'extra', 'extract', 'extraordinary', 'extreme', 'extremely', 'eye', 'eyebrow',
        ];

        return array_map(fn($w) => ['content' => $w, 'meaning' => ''], array_unique($words));
    }

    private function getCET4Words(): array
    {
        // 四级核心词汇 (在高中基础上扩展)
        $words = [
            'fabricate', 'fabric', 'fabulous', 'facade', 'facet', 'facilitate', 'facility', 'faction', 'faculty', 'fade',
            'Fahrenheit', 'failure', 'faint', 'fair', 'fairy', 'faith', 'faithful', 'fake', 'fame', 'familiar',
            'famine', 'fan', 'fancy', 'fantastic', 'fantasy', 'far', 'fare', 'farewell', 'farm', 'farmer',
            'fascinate', 'fashion', 'fashionable', 'fast', 'fasten', 'fat', 'fatal', 'fate', 'fatigue', 'fault',
            'faulty', 'favor', 'favorable', 'favorite', 'fax', 'feasible', 'feast', 'feat', 'feather', 'feature',
            'federal', 'federation', 'fee', 'feeble', 'feed', 'feedback', 'feel', 'feeling', 'fellow', 'fellowship',
            'female', 'feminine', 'fence', 'ferry', 'fertile', 'fertilizer', 'festival', 'fetch', 'fever', 'fiber',
            'fiction', 'field', 'fierce', 'fifteen', 'fifth', 'fifty', 'fig', 'fight', 'figure', 'file',
            'fill', 'film', 'filter', 'final', 'finance', 'financial', 'find', 'finding', 'fine', 'finger',
            'finish', 'finite', 'fire', 'fireplace', 'firm', 'first', 'fiscal', 'fish', 'fisherman', 'fist',
            'fit', 'fitness', 'five', 'fix', 'flag', 'flame', 'flap', 'flare', 'flash', 'flask',
            'flat', 'flatter', 'flavor', 'flaw', 'flee', 'fleet', 'flesh', 'flexibility', 'flexible', 'flight',
            'fling', 'float', 'flock', 'flood', 'floor', 'flour', 'flourish', 'flow', 'flower', 'flu',
            'fluctuate', 'fluent', 'fluid', 'flush', 'flutter', 'fly', 'foam', 'focus', 'fog', 'foggy',
            'fold', 'folk', 'follow', 'follower', 'following', 'fond', 'food', 'fool', 'foolish', 'foot',
            'football', 'footprint', 'footstep', 'for', 'forbid', 'force', 'forecast', 'forehead', 'foreign', 'foreigner',
            'foremost', 'foresee', 'forest', 'forever', 'forge', 'forget', 'forgive', 'fork', 'form', 'formal',
            'format', 'formation', 'former', 'formerly', 'formula', 'formulate', 'fort', 'forth', 'forthcoming', 'fortunate',
            'fortune', 'forty', 'forum', 'forward', 'fossil', 'foster', 'foul', 'found', 'foundation', 'founder',
            'fountain', 'four', 'fourteen', 'fourth', 'fowl', 'fox', 'fraction', 'fracture', 'fragile', 'fragment',
            'fragrant', 'frame', 'framework', 'frank', 'frankly', 'fraud', 'free', 'freedom', 'freely', 'freeze',
            'freight', 'French', 'frequency', 'frequent', 'frequently', 'fresh', 'friction', 'Friday', 'fridge', 'friend',
            'friendly', 'friendship', 'fright', 'frighten', 'fringe', 'frog', 'from', 'front', 'frontier', 'frost',
            'frown', 'fruit', 'fruitful', 'frustrate', 'fry', 'fuel', 'fulfill', 'full', 'fully', 'fun',
            'function', 'functional', 'fund', 'fundamental', 'funeral', 'funny', 'fur', 'furious', 'furnace', 'furnish',
            'furniture', 'further', 'furthermore', 'fury', 'fuse', 'fusion', 'fuss', 'future', 'fuzzy',
            'gadget', 'gain', 'galaxy', 'gallery', 'gallon', 'gamble', 'game', 'gang', 'gap', 'garage',
            'garbage', 'garden', 'garlic', 'garment', 'gas', 'gasoline', 'gasp', 'gate', 'gather', 'gauge',
            'gay', 'gaze', 'gear', 'gender', 'gene', 'general', 'generally', 'generate', 'generation', 'generator',
            'generous', 'genetic', 'genius', 'gentle', 'gentleman', 'gently', 'genuine', 'geographic', 'geography', 'geology',
            'geometry', 'germ', 'gesture', 'get', 'ghost', 'giant', 'gift', 'gifted', 'gigantic', 'girl',
            'give', 'glad', 'glance', 'glare', 'glass', 'gleam', 'glide', 'glimpse', 'glitter', 'global',
            'globe', 'gloomy', 'glorious', 'glory', 'glove', 'glow', 'glue', 'go', 'goal', 'goat',
            'god', 'gold', 'golden', 'golf', 'good', 'goodness', 'goods', 'goose', 'gorgeous', 'gospel',
            'gossip', 'govern', 'government', 'governor', 'gown', 'grab', 'grace', 'graceful', 'gracious', 'grade',
            'gradual', 'graduate', 'graduation', 'grain', 'gram', 'grammar', 'grand', 'grandchild', 'grandfather', 'grandmother',
            'grandparent', 'grant', 'grape', 'graph', 'graphic', 'grasp', 'grass', 'grateful', 'gratitude', 'grave',
            'gravity', 'gray', 'grease', 'great', 'greatly', 'greed', 'greedy', 'green', 'greenhouse', 'greet',
            'greeting', 'grey', 'grief', 'grieve', 'grim', 'grin', 'grind', 'grip', 'groan', 'grocery',
            'groom', 'gross', 'ground', 'group', 'grow', 'growth', 'guarantee', 'guard', 'guardian', 'guess',
            'guest', 'guidance', 'guide', 'guideline', 'guilt', 'guilty', 'guitar', 'gulf', 'gun', 'gut',
            'guy', 'gym', 'gymnasium', 'habit', 'habitat', 'hack', 'hail', 'hair', 'haircut', 'hairy',
            'half', 'hall', 'halt', 'hamburger', 'hammer', 'hamper', 'hand', 'handful', 'handicap', 'handle',
            'handsome', 'handy', 'hang', 'happen', 'happiness', 'happy', 'harbor', 'hard', 'harden', 'hardly',
            'hardship', 'hardware', 'hardy', 'harm', 'harmful', 'harmless', 'harmony', 'harness', 'harsh', 'harvest',
            'haste', 'hasten', 'hasty', 'hat', 'hatch', 'hate', 'hatred', 'haul', 'haunt', 'have',
            'hawk', 'hay', 'hazard', 'hazardous', 'haze', 'head', 'headache', 'heading', 'headline', 'headmaster',
            'headquarters', 'heal', 'health', 'healthy', 'heap', 'hear', 'hearing', 'heart', 'heat', 'heater',
            'heating', 'heaven', 'heavenly', 'heavily', 'heavy', 'hedge', 'heel', 'height', 'heighten', 'heir',
            'helicopter', 'hell', 'hello', 'helmet', 'help', 'helpful', 'helpless', 'hemisphere', 'hen', 'hence',
            'her', 'herb', 'herd', 'here', 'heritage', 'hero', 'heroic', 'heroine', 'hers', 'herself',
            'hesitate', 'hesitation', 'hide', 'hierarchy', 'high', 'highlight', 'highly', 'highway', 'hike', 'hill',
            'him', 'himself', 'hinder', 'hindrance', 'hint', 'hip', 'hire', 'his', 'historian', 'historic',
            'historical', 'history', 'hit', 'hobby', 'hockey', 'hoist', 'hold', 'holder', 'hole', 'holiday',
            'hollow', 'holy', 'home', 'homeland', 'homeless', 'homework', 'honest', 'honesty', 'honey', 'honor',
            'honorable', 'hood', 'hook', 'hope', 'hopeful', 'hopeless', 'horizon', 'horizontal', 'horn', 'horrible',
            'horror', 'horse', 'horsepower', 'hospital', 'host', 'hostage', 'hostess', 'hostile', 'hostility', 'hot',
            'hotel', 'hound', 'hour', 'hourly', 'house', 'household', 'housewife', 'housing', 'hover', 'how',
            'however', 'hug', 'huge', 'human', 'humanitarian', 'humanity', 'humble', 'humid', 'humidity', 'humiliate',
            'humor', 'humorous', 'hundred', 'hunger', 'hungry', 'hunt', 'hunter', 'hurricane', 'hurry', 'hurt',
            'husband', 'hut', 'hydrogen', 'hygiene', 'hymn', 'hypothesis', 'hysteria',
            'identical', 'identification', 'identify', 'identity', 'ideology', 'idiom', 'idle', 'idol', 'ignorance', 'ignorant',
            'ignore', 'ill', 'illegal', 'illness', 'illuminate', 'illusion', 'illustrate', 'illustration', 'image', 'imaginary',
            'imagination', 'imaginative', 'imagine', 'imitate', 'imitation', 'immediate', 'immediately', 'immense', 'immerse', 'immigrant',
            'immigration', 'immune', 'impact', 'impair', 'impart', 'impatient', 'imperative', 'imperial', 'implement', 'implication',
            'implicit', 'imply', 'import', 'importance', 'important', 'impose', 'impossible', 'impress', 'impression', 'impressive',
            'imprison', 'improve', 'improvement', 'impulse', 'in', 'inability', 'inadequate', 'inappropriate', 'incentive', 'inch',
            'incidence', 'incident', 'incline', 'include', 'including', 'income', 'incorporate', 'increase', 'increasingly', 'incredible',
            'incur', 'indeed', 'independence', 'independent', 'index', 'indicate', 'indication', 'indicator', 'indifferent', 'indigenous',
            'indirect', 'individual', 'indoor', 'indoors', 'induce', 'indulge', 'industrial', 'industrialize', 'industry', 'inevitable',
            'infant', 'infect', 'infection', 'infer', 'inferior', 'infinite', 'inflation', 'influence', 'influential', 'inform',
            'informal', 'information', 'ingredient', 'inhabit', 'inhabitant', 'inherent', 'inherit', 'inheritance', 'initial', 'initially',
            'initiate', 'initiative', 'inject', 'injection', 'injure', 'injury', 'ink', 'inland', 'inn', 'inner',
            'innocent', 'innovation', 'innovative', 'input', 'inquire', 'inquiry', 'insect', 'insert', 'inside', 'insight',
            'insist', 'inspect', 'inspection', 'inspector', 'inspiration', 'inspire', 'install', 'installation', 'instance', 'instant',
            'instantly', 'instead', 'instinct', 'institute', 'institution', 'instruct', 'instruction', 'instructor', 'instrument', 'insufficient',
            'insult', 'insurance', 'insure', 'intact', 'intake', 'integral', 'integrate', 'integration', 'integrity', 'intellectual',
            'intelligence', 'intelligent', 'intend', 'intense', 'intensity', 'intensive', 'intent', 'intention', 'interact', 'interaction',
            'interest', 'interested', 'interesting', 'interfere', 'interference', 'interior', 'intermediate', 'internal', 'international', 'Internet',
            'interpret', 'interpretation', 'interpreter', 'interrupt', 'interval', 'intervene', 'intervention', 'interview', 'intimate', 'into',
            'introduce', 'introduction', 'invade', 'invalid', 'invasion', 'invent', 'invention', 'inventor', 'inventory', 'invest',
            'investigate', 'investigation', 'investigator', 'investment', 'investor', 'invisible', 'invitation', 'invite', 'involve', 'involved',
            'involvement', 'inward', 'iron', 'irony', 'irregular', 'irrelevant', 'irrigate', 'irrigation', 'irritate', 'island',
            'isolate', 'isolation', 'issue', 'it', 'item', 'its', 'itself', 'ivory', 'jacket', 'jail',
            'jam', 'January', 'jar', 'jaw', 'jazz', 'jealous', 'jealousy', 'jeans', 'jerk', 'jet',
            'jewel', 'jewelry', 'job', 'jog', 'join', 'joint', 'joke', 'jolly', 'journal', 'journalism',
            'journalist', 'journey', 'joy', 'joyful', 'judge', 'judgment', 'juice', 'juicy', 'July', 'jump',
            'junction', 'June', 'jungle', 'junior', 'jury', 'just', 'justice', 'justification', 'justify', 'keen',
            'keep', 'keeper', 'kernel', 'kettle', 'key', 'keyboard', 'kick', 'kid', 'kidnap', 'kidney',
            'kill', 'killer', 'kilogram', 'kilometer', 'kind', 'kindergarten', 'kindness', 'king', 'kingdom', 'kiss',
            'kit', 'kitchen', 'kite', 'knee', 'kneel', 'knife', 'knight', 'knit', 'knob', 'knock',
            'knot', 'know', 'knowledge', 'lab', 'label', 'labor', 'laboratory', 'lack', 'lad', 'ladder',
            'lady', 'lag', 'lake', 'lamb', 'lame', 'lamp', 'land', 'landing', 'landlady', 'landlord',
            'landmark', 'landscape', 'lane', 'language', 'lap', 'laptop', 'large', 'largely', 'laser', 'last',
            'lasting', 'late', 'lately', 'later', 'latest', 'latter', 'laugh', 'laughter', 'launch', 'laundry',
            'law', 'lawful', 'lawn', 'lawsuit', 'lawyer', 'lay', 'layer', 'layout', 'lazy', 'lead',
            'leader', 'leadership', 'leading', 'leaf', 'leaflet', 'league', 'leak', 'lean', 'leap', 'learn',
            'learned', 'learner', 'learning', 'lease', 'least', 'leather', 'leave', 'lecture', 'lecturer', 'left',
            'leg', 'legacy', 'legal', 'legend', 'legendary', 'legislation', 'legitimate', 'leisure', 'lemon', 'lend',
            'length', 'lens', 'less', 'lessen', 'lesson', 'let', 'letter', 'level', 'lever', 'levy',
            'liability', 'liable', 'liberal', 'liberate', 'liberation', 'liberty', 'librarian', 'library', 'license', 'lick',
            'lid', 'lie', 'life', 'lifetime', 'lift', 'light', 'lighten', 'lightning', 'like', 'likelihood',
            'likely', 'likewise', 'limb', 'lime', 'limit', 'limitation', 'limited', 'limp', 'line', 'linear',
            'linen', 'liner', 'linger', 'link', 'lion', 'lip', 'liquid', 'liquor', 'list', 'listen',
            'listener', 'literacy', 'literary', 'literate', 'literature', 'liter', 'little', 'live', 'lively', 'liver',
            'living', 'load', 'loaf', 'loan', 'lobby', 'local', 'locate', 'location', 'lock', 'locomotive',
            'lodge', 'log', 'logic', 'logical', 'lonely', 'long', 'look', 'loose', 'loosen', 'lord',
            'lorry', 'lose', 'loser', 'loss', 'lot', 'lottery', 'loud', 'loudspeaker', 'lounge', 'love',
            'lovely', 'lover', 'low', 'lower', 'loyal', 'loyalty', 'luck', 'lucky', 'luggage', 'lumber',
            'lump', 'lunar', 'lunch', 'lung', 'lure', 'luxury', 'machine', 'machinery', 'mad', 'madam',
            'madness', 'magazine', 'magic', 'magical', 'magnet', 'magnetic', 'magnificent', 'magnitude', 'maid', 'mail',
            'mailbox', 'main', 'mainland', 'mainly', 'mainstream', 'maintain', 'maintenance', 'major', 'majority', 'make',
            'maker', 'makeup', 'male', 'mall', 'mammal', 'man', 'manage', 'management', 'manager', 'managerial',
            'mandate', 'manifest', 'manipulate', 'mankind', 'manner', 'manual', 'manufacture', 'manufacturer', 'manufacturing', 'manuscript',
            'many', 'map', 'maple', 'marble', 'march', 'margin', 'marginal', 'marine', 'mark', 'marker',
            'market', 'marketing', 'marriage', 'married', 'marry', 'marsh', 'martial', 'marvel', 'marvelous', 'masculine',
            'mask', 'mass', 'massive', 'mast', 'master', 'masterpiece', 'match', 'mate', 'material', 'mathematics',
            'matter', 'mature', 'maturity', 'maximum', 'may', 'maybe', 'mayor', 'me', 'meadow', 'meal',
            'mean', 'meaning', 'meaningful', 'means', 'meantime', 'meanwhile', 'measure', 'measurement', 'meat', 'mechanic',
            'mechanical', 'mechanism', 'medal', 'media', 'medical', 'medication', 'medicine', 'medieval', 'meditate', 'medium',
            'meet', 'meeting', 'melody', 'melon', 'melt', 'member', 'membership', 'membrane', 'memo', 'memorable',
            'memorial', 'memorize', 'memory', 'menace', 'mend', 'mental', 'mentality', 'mention', 'menu', 'merchant',
            'mercy', 'mere', 'merely', 'merge', 'merit', 'merry', 'mess', 'message', 'messenger', 'messy',
            'metal', 'meter', 'method', 'metric', 'metropolitan', 'microphone', 'microscope', 'microwave', 'middle', 'midnight',
            'midst', 'might', 'mighty', 'migrate', 'migration', 'mild', 'mile', 'mileage', 'milestone', 'military',
            'milk', 'mill', 'million', 'millionaire', 'mind', 'mine', 'miner', 'mineral', 'mingle', 'miniature',
            'minimal', 'minimize', 'minimum', 'minister', 'ministry', 'minor', 'minority', 'minus', 'minute', 'miracle',
            'mirror', 'miserable', 'misery', 'misfortune', 'mislead', 'miss', 'missile', 'missing', 'mission', 'missionary',
            'mist', 'mistake', 'mistaken', 'mistress', 'misunderstand', 'mix', 'mixed', 'mixture', 'moan', 'mob',
            'mobile', 'mobility', 'mock', 'mode', 'model', 'moderate', 'modern', 'modernization', 'modernize', 'modest',
            'modify', 'moist', 'moisture', 'molecule', 'mom', 'moment', 'momentary', 'momentum', 'monarch', 'monarchy',
            'monastery', 'Monday', 'monetary', 'money', 'monitor', 'monk', 'monkey', 'monopoly', 'monster', 'month',
            'monthly', 'monument', 'mood', 'moon', 'moonlight', 'moral', 'morale', 'morality', 'more', 'moreover',
            'morning', 'mortal', 'mortgage', 'mosquito', 'moss', 'most', 'mostly', 'motel', 'moth', 'mother',
            'motion', 'motivate', 'motivation', 'motive', 'motor', 'motorist', 'motorway', 'mount', 'mountain', 'mourn',
            'mouse', 'mouth', 'move', 'movement', 'movie', 'much', 'mud', 'muddy', 'mug', 'multiple',
            'multiply', 'multitude', 'municipal', 'murder', 'murderer', 'murmur', 'muscle', 'museum', 'mushroom', 'music',
            'musical', 'musician', 'must', 'mustard', 'mute', 'mutter', 'mutual', 'my', 'myself', 'mysterious',
            'mystery', 'myth', 'mythology', 'nail', 'naive', 'naked', 'name', 'namely', 'nap', 'napkin',
            'narrative', 'narrow', 'nasty', 'nation', 'national', 'nationalism', 'nationality', 'nationwide', 'native', 'natural',
            'naturally', 'nature', 'naval', 'navigate', 'navigation', 'navy', 'near', 'nearby', 'nearly', 'neat',
            'necessarily', 'necessary', 'necessity', 'neck', 'necklace', 'need', 'needle', 'negative', 'neglect', 'negotiate',
            'negotiation', 'neighbor', 'neighborhood', 'neither', 'nephew', 'nerve', 'nervous', 'nest', 'net', 'network',
            'neutral', 'never', 'nevertheless', 'new', 'newcomer', 'newly', 'news', 'newspaper', 'next', 'nice',
            'nickel', 'nickname', 'niece', 'night', 'nightmare', 'nine', 'nineteen', 'ninety', 'nitrogen', 'no',
            'noble', 'nobody', 'nod', 'noise', 'noisy', 'nominal', 'nominate', 'nomination', 'none', 'nonetheless',
            'nonsense', 'noon', 'nor', 'norm', 'normal', 'normally', 'north', 'northeast', 'northern', 'northwest',
            'nose', 'not', 'notable', 'notably', 'note', 'notebook', 'nothing', 'notice', 'noticeable', 'notify',
            'notion', 'notorious', 'noun', 'nourish', 'novel', 'novelist', 'novelty', 'November', 'now', 'nowadays',
            'nowhere', 'nuclear', 'nucleus', 'nuisance', 'null', 'numb', 'number', 'numerous', 'nurse', 'nursery',
            'nurture', 'nut', 'nutrition', 'nylon',
        ];

        return array_map(fn($w) => ['content' => $w, 'meaning' => ''], array_unique($words));
    }

    private function getCET6Words(): array
    {
        // 六级核心词汇 (更高级的词汇)
        $words = [
            'abase', 'abash', 'abate', 'abbey', 'abbot', 'abbreviate', 'abdicate', 'abdomen', 'abduct', 'aberrant',
            'abet', 'abhor', 'abide', 'abject', 'abjure', 'ablaze', 'abnormality', 'abolition', 'abominable', 'aboriginal',
            'abort', 'abreast', 'abridge', 'abridgment', 'abruptly', 'abscond', 'absolve', 'abstain', 'abstinence', 'abstraction',
            'abstruse', 'absurdity', 'acclaimed', 'accolade', 'accomplice', 'accountability', 'accredit', 'accrue', 'accusation', 'acerbic',
            'acknowledge', 'acme', 'acoustic', 'acquiesce', 'acquittal', 'acrimonious', 'acronym', 'acuity', 'adage', 'adamant',
            'addendum', 'addictive', 'adept', 'adherent', 'adjoin', 'adjourn', 'adjunct', 'admonish', 'admonition', 'ado',
            'adoration', 'adorn', 'adrift', 'adroit', 'adulation', 'adulterate', 'advent', 'adversary', 'adversity', 'advocacy',
            'aesthetic', 'affable', 'affectation', 'affidavit', 'affiliation', 'affinity', 'affirmation', 'afflict', 'affluence', 'affluent',
            'affront', 'aftermath', 'aggrandize', 'aggregate', 'aggression', 'aghast', 'agile', 'agility', 'agitate', 'agitation',
            'agnostic', 'agrarian', 'aide', 'ailment', 'ajar', 'akin', 'alacrity', 'albeit', 'alchemy', 'alcove',
            'alias', 'alibi', 'alienate', 'align', 'alignment', 'alimony', 'allay', 'allegation', 'allege', 'allegiance',
            'allegory', 'alleviate', 'allot', 'allotment', 'alloy', 'allude', 'allure', 'allusion', 'almanac', 'aloft',
            'aloof', 'alpine', 'alteration', 'altercation', 'alternate', 'altitude', 'altruism', 'alumnus', 'amalgamate', 'amass',
            'ambiguity', 'ambivalence', 'amble', 'ambulatory', 'ameliorate', 'amenable', 'amend', 'amenity', 'amiable', 'amicable',
            'amiss', 'amnesia', 'amnesty', 'amorphous', 'ample', 'amplify', 'amputate', 'amulet', 'anachronism', 'analgesic',
            'analogous', 'analogy', 'anarchist', 'anarchy', 'anatomy', 'ancestry', 'anecdote', 'anemia', 'anesthetic', 'anguished',
            'angular', 'animate', 'animosity', 'annex', 'annihilate', 'annotate', 'annuity', 'annul', 'anomaly', 'anonymity',
            'antagonism', 'antagonist', 'ante', 'antecedent', 'anthem', 'anthology', 'anthropology', 'antibody', 'antic', 'anticipation',
            'antidote', 'antipathy', 'antiquated', 'antiseptic', 'antithesis', 'anvil', 'apathy', 'aperture', 'apex', 'aplomb',
            'apocalypse', 'apocryphal', 'apogee', 'appall', 'apparel', 'apparition', 'appease', 'append', 'appendix', 'appertain',
            'appliance', 'applicability', 'appraisal', 'appreciable', 'apprehend', 'apprehension', 'apprehensive', 'apprentice', 'apprise', 'approbation',
            'appropriation', 'aptitude', 'aquatic', 'arable', 'arbiter', 'arbitrary', 'arbitrate', 'arbor', 'arc', 'arcade',
            'arcane', 'arch', 'archaeology', 'archaic', 'archer', 'archetype', 'archipelago', 'archive', 'ardent', 'ardor',
            'arduous', 'arena', 'aristocracy', 'aristocrat', 'ark', 'armada', 'armament', 'armistice', 'armor', 'aroma',
            'aromatic', 'arraign', 'array', 'arrears', 'arrogance', 'arsenal', 'arson', 'articulate', 'artifact', 'artifice',
            'artillery', 'artisan', 'ascertain', 'ascetic', 'ascribe', 'aseptic', 'askew', 'aspersion', 'asphalt', 'asphyxiate',
            'aspirant', 'aspiration', 'aspire', 'assail', 'assassinate', 'assault', 'assemblage', 'assent', 'assertion', 'assessor',
            'assiduous', 'assimilate', 'assuage', 'asteroid', 'asthma', 'astigmatism', 'astound', 'astray', 'astringent', 'astute',
            'asylum', 'atheist', 'atone', 'atrocious', 'atrocity', 'atrophy', 'attain', 'attainment', 'attest', 'attire',
            'audacious', 'audacity', 'audible', 'audition', 'auditor', 'auditorium', 'augment', 'augur', 'aura', 'aural',
            'auspicious', 'austere', 'austerity', 'authenticate', 'authoritarian', 'authoritative', 'autism', 'autocrat', 'automaton', 'autopsy',
            'auxiliary', 'avalanche', 'avarice', 'avenge', 'averse', 'aversion', 'avert', 'aviary', 'aviation', 'avid',
            'avocation', 'await', 'awning', 'awry', 'axiom',
            'babble', 'backlash', 'backlog', 'bacterium', 'baffle', 'bait', 'ballad', 'ballast', 'bane', 'banish',
            'banquet', 'banter', 'baptism', 'baptize', 'barb', 'barbaric', 'barbarous', 'barley', 'barometer', 'baron',
            'baroque', 'barracks', 'barrage', 'barren', 'barricade', 'barter', 'basalt', 'bash', 'bask', 'bass',
            'bastard', 'batch', 'baton', 'battalion', 'bauble', 'bazaar', 'beacon', 'bead', 'beam', 'bearing',
            'beckon', 'bedrock', 'befriend', 'beguile', 'behest', 'behold', 'belie', 'believer', 'belittle', 'bellicose',
            'belligerent', 'bellow', 'bemoan', 'benchmark', 'benediction', 'benefactor', 'beneficiary', 'benevolent', 'benign', 'bequeath',
            'bequest', 'berate', 'bereave', 'bereft', 'berserk', 'beseech', 'beset', 'besiege', 'besmirch', 'bestow',
            'betray', 'betroth', 'beverage', 'bewilder', 'bicameral', 'bicker', 'biennial', 'bigot', 'bigotry', 'bilateral',
            'bilingual', 'billboard', 'binge', 'biodegradable', 'biosphere', 'bipartisan', 'bisect', 'bizarre', 'blackmail', 'blaspheme',
            'blasphemy', 'blatant', 'blaze', 'bleach', 'bleak', 'blemish', 'blight', 'bliss', 'blithe', 'blizzard',
            'bloat', 'blockade', 'blockbuster', 'bloodshed', 'blot', 'blueprint', 'bluff', 'blunder', 'blunt', 'blur',
            'blurt', 'blush', 'bode', 'bog', 'boggle', 'bogus', 'boisterous', 'bolster', 'bombardment', 'bombast',
            'bonanza', 'bondage', 'bonfire', 'boon', 'boor', 'bootleg', 'booty', 'borderline', 'botch', 'bough',
            'boulder', 'boulevard', 'bounty', 'bout', 'brace', 'bracing', 'bracket', 'brag', 'brandish', 'bravado',
            'brawl', 'brazen', 'breach', 'breadwinner', 'breakup', 'brethren', 'brevity', 'bribe', 'bribery', 'brigade',
            'brim', 'brine', 'brink', 'bristle', 'brittle', 'broach', 'brochure', 'broker', 'brood', 'browse',
            'brunt', 'brusque', 'brutality', 'brute', 'buckle', 'budge', 'buffer', 'buffet', 'bulge', 'bulky',
            'bulldoze', 'bumble', 'bumpy', 'bungle', 'buoyant', 'bureaucrat', 'burgeon', 'burglar', 'burglary', 'burlesque',
            'burnish', 'burrow', 'bust', 'bustle', 'buttress', 'bypass', 'bystander',
            'cabal', 'cache', 'cacophony', 'cadaver', 'cadence', 'cadet', 'cajole', 'calamity', 'caliber', 'calibrate',
            'callous', 'callow', 'camouflage', 'candid', 'candor', 'canine', 'canker', 'cannibal', 'canopy', 'cant',
            'canteen', 'canter', 'canvass', 'capacious', 'cape', 'capillary', 'capitulate', 'capricious', 'captivate', 'captive',
            'captivity', 'carat', 'carcass', 'carcinogen', 'cardinal', 'careen', 'caress', 'caricature', 'carnage', 'carnal',
            'carnivore', 'carol', 'carousel', 'carp', 'cartography', 'cascade', 'caste', 'castigate', 'casualty', 'cataclysm',
            'catalyst', 'catapult', 'cataract', 'catastrophe', 'categorical', 'cater', 'catharsis', 'catholic', 'caucus', 'cauldron',
            'causal', 'caustic', 'cauterize', 'cavalier', 'cavalry', 'caveat', 'cavern', 'cavity', 'cede', 'celestial',
            'celibacy', 'censor', 'censure', 'centennial', 'centralize', 'centrifugal', 'cerebral', 'ceremonial', 'cessation', 'chafe',
            'chaff', 'chagrin', 'chalet', 'chalice', 'chameleon', 'chancellor', 'chandelier', 'chant', 'chaotic', 'chapel',
            'chaperone', 'char', 'charade', 'chariot', 'charisma', 'charitable', 'charlatan', 'charter', 'chary', 'chasm',
            'chassis', 'chaste', 'chasten', 'chastise', 'chatter', 'chauvinist', 'cherish', 'cherub', 'chicanery', 'chide',
            'chiefly', 'chieftain', 'chiffon', 'chilly', 'chime', 'chisel', 'chivalry', 'chlorine', 'choke', 'cholera',
            'cholesterol', 'chord', 'chore', 'choreography', 'chortle', 'chromatic', 'chromosome', 'chronic', 'chronicle', 'chronological',
            'chubby', 'chuckle', 'chunk', 'churn', 'cider', 'cinch', 'cipher', 'circuitous', 'circumference', 'circumscribe',
            'circumspect', 'circumstantial', 'circumvent', 'cistern', 'citadel', 'citation', 'civic', 'civility', 'clad', 'clamber',
            'clammy', 'clamor', 'clamp', 'clandestine', 'clang', 'clank', 'clap', 'clarification', 'clarion', 'classification',
            'clause', 'cleanse', 'clearance', 'cleavage', 'cleave', 'cleft', 'clemency', 'clench', 'clergy', 'clerical',
            'clientele', 'climactic', 'clinch', 'clinical', 'clique', 'cloak', 'clog', 'cloister', 'clot', 'clout',
            'clown', 'cloy', 'coagulate', 'coalesce', 'coalition', 'coax', 'cobble', 'cocky', 'coddle', 'codify',
            'coerce', 'coercion', 'coexist', 'coffer', 'cogent', 'cogitate', 'cognition', 'cognitive', 'cognizant', 'cohabit',
            'cohere', 'coherence', 'cohesion', 'cohesive', 'cohort', 'coincidental', 'collaborate', 'collaboration', 'collaborative', 'collage',
            'collateral', 'colloquial', 'colloquium', 'collusion', 'colossal', 'coma', 'comatose', 'combatant', 'combustible', 'combustion',
            'comeback', 'comedian', 'comedic', 'comely', 'comet', 'comeuppance', 'commemorate', 'commencement', 'commend', 'commendable',
            'commensurate', 'commentary', 'commentator', 'commissary', 'commissioner', 'commit', 'commonwealth', 'commotion', 'communal', 'commune',
            'communicable', 'communion', 'commute', 'compact', 'compassion', 'compassionate', 'compatible', 'compatriot', 'compel', 'compelling',
            'compendium', 'competence', 'compile', 'complacency', 'complacent', 'complement', 'complementary', 'complexion', 'compliant', 'complication',
            'complicit', 'compliment', 'complimentary', 'comply', 'composite', 'compost', 'composure', 'comprehend', 'comprehension', 'compress',
            'compression', 'compulsion', 'compulsive', 'compunction', 'computation', 'comrade', 'concave', 'conceal', 'concede', 'conceit',
            'conceited', 'conceivable', 'concentrated', 'concentric', 'conceptual', 'concerted', 'concession', 'concierge', 'conciliatory', 'concise',
            'conclave', 'conclusive', 'concoct', 'concomitant', 'concord', 'concordance', 'concourse', 'concur', 'concurrent', 'concussion',
            'condemnation', 'condensation', 'condense', 'condescend', 'condescending', 'condiment', 'conditional', 'condolence', 'condone', 'conducive',
            'conduit', 'confederate', 'confer', 'conferee', 'confide', 'confidential', 'configuration', 'confine', 'confirmation', 'confiscate',
            'conflagration', 'confluence', 'conformist', 'conformity', 'confound', 'confront', 'confrontation', 'congeal', 'congenial', 'congenital',
            'congest', 'congestion', 'conglomerate', 'congratulation', 'congregate', 'congregation', 'congressional', 'congruent', 'conifer', 'conjecture',
        ];

        return array_map(fn($w) => ['content' => $w, 'meaning' => ''], array_unique($words));
    }
}
