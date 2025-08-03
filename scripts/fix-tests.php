<?php

/**
 * 快速修复测试脚本
 * 修复一些基本的测试问题
 */

require_once __DIR__ . '/../vendor/autoload.php';

class TestFixer
{
    public function fix(): void
    {
        echo "🔧 开始修复测试问题...\n\n";
        
        $this->fixChatServiceTest();
        $this->fixImageUploadServiceTest();
        $this->createMissingFactories();
        
        echo "✅ 测试修复完成！\n";
        echo "💡 建议运行: composer run test:coverage\n";
    }
    
    private function fixChatServiceTest(): void
    {
        $testFile = __DIR__ . '/../tests/Unit/Services/ChatServiceTest.php';
        
        if (!file_exists($testFile)) {
            echo "⚠️  ChatServiceTest.php 不存在\n";
            return;
        }
        
        $content = file_get_contents($testFile);
        
        // 修复构造函数调用
        $content = preg_replace(
            '/new ChatService\(\)/',
            'new ChatService(app(\'App\Services\ChatCacheService\'), app(\'App\Services\ChatPaginationService\'))',
            $content
        );
        
        file_put_contents($testFile, $content);
        echo "✅ 修复了 ChatServiceTest 构造函数问题\n";
    }
    
    private function fixImageUploadServiceTest(): void
    {
        $testFile = __DIR__ . '/../tests/Unit/Services/ImageUploadServiceTest.php';
        
        if (!file_exists($testFile)) {
            echo "⚠️  ImageUploadServiceTest.php 不存在\n";
            return;
        }
        
        $content = file_get_contents($testFile);
        
        // 修复工厂类引用
        $content = preg_replace(
            '/Item::factory\(\)/',
            'Item::factory()',
            $content
        );
        
        file_put_contents($testFile, $content);
        echo "✅ 修复了 ImageUploadServiceTest 工厂类问题\n";
    }
    
    private function createMissingFactories(): void
    {
        $factoriesDir = __DIR__ . '/../database/factories';
        $thingFactoriesDir = $factoriesDir . '/Thing';
        
        // 创建 Thing 目录
        if (!is_dir($thingFactoriesDir)) {
            mkdir($thingFactoriesDir, 0755, true);
        }
        
        // 创建 ItemFactory
        $itemFactoryFile = $thingFactoriesDir . '/ItemFactory.php';
        if (!file_exists($itemFactoryFile)) {
            $itemFactoryContent = '<?php

namespace Database\Factories\Thing;

use App\Models\Thing\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition()
    {
        return [
            "name" => $this->faker->sentence(3),
            "description" => $this->faker->paragraph(),
            "user_id" => User::factory(),
            "category_id" => null,
            "location_id" => null,
            "status" => "active",
            "created_at" => now(),
            "updated_at" => now(),
        ];
    }
}';
            
            file_put_contents($itemFactoryFile, $itemFactoryContent);
            echo "✅ 创建了 ItemFactory\n";
        }
        
        // 创建其他缺失的工厂类
        $missingFactories = [
            'CategoryFactory.php' => 'Category',
            'LocationFactory.php' => 'Location',
            'TagFactory.php' => 'Tag',
        ];
        
        foreach ($missingFactories as $fileName => $modelName) {
            $factoryFile = $thingFactoriesDir . '/' . $fileName;
            if (!file_exists($factoryFile)) {
                $factoryContent = "<?php

namespace Database\Factories\Thing;

use App\Models\Thing\\{$modelName};
use Illuminate\Database\Eloquent\Factories\Factory;

class {$modelName}Factory extends Factory
{
    protected \$model = {$modelName}::class;

    public function definition()
    {
        return [
            'name' => \$this->faker->word(),
            'description' => \$this->faker->sentence(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}";
                
                file_put_contents($factoryFile, $factoryContent);
                echo "✅ 创建了 {$modelName}Factory\n";
            }
        }
    }
}

// 运行修复
$fixer = new TestFixer();
$fixer->fix(); 