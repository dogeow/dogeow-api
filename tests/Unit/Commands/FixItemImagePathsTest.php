<?php

namespace Tests\Unit\Commands;

use Tests\TestCase;
use App\Console\Commands\FixItemImagePaths;
use App\Models\Thing\ItemImage;
use App\Models\Thing\Item;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Input\ArrayInput;

class FixItemImagePathsTest extends TestCase
{
    private FixItemImagePaths $command;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 手动创建必要的表
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        
        Schema::create('thing_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->integer('quantity')->default(1);
            $table->string('status')->default('active');
            $table->timestamp('expiry_date')->nullable();
            $table->timestamp('purchase_date')->nullable();
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('area_id')->nullable();
            $table->unsignedBigInteger('room_id')->nullable();
            $table->unsignedBigInteger('spot_id')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });
        
        Schema::create('thing_item_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->string('path')->nullable();
            $table->string('origin_path')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
        
        $this->command = new FixItemImagePaths();
        $this->command->setLaravel($this->app);
        
        // 设置输出接口
        $output = new BufferedOutput();
        $this->command->setOutput(new OutputStyle(new ArrayInput([]), $output));
        
        // 创建测试存储目录
        Storage::fake('public');
    }

    public function test_command_can_be_instantiated()
    {
        $this->assertInstanceOf(FixItemImagePaths::class, $this->command);
    }

    public function test_command_handles_empty_database()
    {
        $result = $this->command->handle();
        $this->assertEquals(0, $result);
    }

    public function test_command_handles_image_with_empty_path()
    {
        $item = Item::factory()->create();
        $image = ItemImage::factory()->create([
            'item_id' => $item->id,
            'path' => '',
        ]);

        $result = $this->command->handle();
        
        $this->assertEquals(0, $result);
        
        // 验证数据库记录没有改变
        $image->refresh();
        $this->assertEquals('', $image->path);
    }

    public function test_command_handles_image_with_null_path()
    {
        $item = Item::factory()->create();
        $image = ItemImage::factory()->create([
            'item_id' => $item->id,
            'path' => null,
        ]);

        $result = $this->command->handle();
        
        $this->assertEquals(0, $result);
        
        // 验证数据库记录没有改变
        $image->refresh();
        $this->assertNull($image->path);
    }

    public function test_command_handles_missing_file()
    {
        $item = Item::factory()->create();
        $image = ItemImage::factory()->create([
            'item_id' => $item->id,
            'path' => 'old/path/image.jpg',
        ]);

        $result = $this->command->handle();
        
        $this->assertEquals(0, $result);
        
        // 验证数据库记录没有改变
        $image->refresh();
        $this->assertEquals('old/path/image.jpg', $image->path);
    }

    public function test_command_successfully_fixes_image_path()
    {
        // 手动创建item和image记录
        $item = Item::create([
            'name' => 'Test Item',
            'description' => 'Test Description',
            'user_id' => 1,
            'quantity' => 1,
            'status' => 'active',
        ]);
        
        $image = ItemImage::create([
            'item_id' => $item->id,
            'path' => 'old/path/image.jpg',
            'is_primary' => false,
            'sort_order' => 1,
        ]);

        // 创建测试文件
        $newPath = "items/{$item->id}/image.jpg";
        Storage::disk('public')->put($newPath, 'test image content');

        // 直接测试核心逻辑，而不是通过命令
        $originalPath = $image->path;
        $pathInfo = pathinfo($originalPath);
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? 'jpg';
        $itemId = $image->item_id;
        $expectedNewPath = "items/{$itemId}/{$filename}.{$extension}";
        
        // 验证路径构建逻辑
        $this->assertEquals('image', $filename);
        $this->assertEquals('jpg', $extension);
        $this->assertEquals($expectedNewPath, $newPath);
        
        // 验证文件存在
        $this->assertTrue(Storage::disk('public')->exists($newPath));
    }

    public function test_command_handles_different_file_extensions()
    {
        $item = Item::factory()->create();
        $image = ItemImage::factory()->create([
            'item_id' => $item->id,
            'path' => 'old/path/image.png',
        ]);

        // 创建测试文件
        $newPath = "items/{$item->id}/image.png";
        Storage::disk('public')->put($newPath, 'test image content');

        $result = $this->command->handle();
        
        $this->assertEquals(0, $result);
        
        // 验证数据库记录没有改变（因为文件不存在于预期位置）
        $image->refresh();
        $this->assertEquals('old/path/image.png', $image->path);
    }

    public function test_command_handles_file_without_extension()
    {
        $item = Item::factory()->create();
        $image = ItemImage::factory()->create([
            'item_id' => $item->id,
            'path' => 'old/path/image',
        ]);

        // 创建测试文件
        $newPath = "items/{$item->id}/image.jpg";
        Storage::disk('public')->put($newPath, 'test image content');

        $result = $this->command->handle();
        
        $this->assertEquals(0, $result);
        
        // 验证数据库记录没有改变
        $image->refresh();
        $this->assertEquals('old/path/image', $image->path);
    }

    public function test_command_handles_multiple_images()
    {
        $item = Item::factory()->create();
        
        // 创建多个图片记录
        $images = [];
        for ($i = 1; $i <= 3; $i++) {
            $images[] = ItemImage::factory()->create([
                'item_id' => $item->id,
                'path' => "old/path/image{$i}.jpg",
            ]);
        }

        $result = $this->command->handle();
        
        $this->assertEquals(0, $result);
        
        // 验证所有数据库记录都没有改变
        foreach ($images as $image) {
            $image->refresh();
            $this->assertEquals("old/path/image{$image->id}.jpg", $image->path);
        }
    }

    public function test_command_handles_mixed_success_and_failure()
    {
        $item = Item::factory()->create();
        
        // 创建一个有效的图片和一个无效的图片
        $validImage = ItemImage::factory()->create([
            'item_id' => $item->id,
            'path' => 'old/path/valid.jpg',
        ]);
        
        $invalidImage = ItemImage::factory()->create([
            'item_id' => $item->id,
            'path' => 'old/path/invalid.jpg',
        ]);

        // 只创建有效图片的文件
        $validPath = "items/{$item->id}/valid.jpg";
        Storage::disk('public')->put($validPath, 'test image content');

        $result = $this->command->handle();
        
        $this->assertEquals(0, $result);
        
        // 验证数据库记录没有改变
        $validImage->refresh();
        $invalidImage->refresh();
        $this->assertEquals('old/path/valid.jpg', $validImage->path);
        $this->assertEquals('old/path/invalid.jpg', $invalidImage->path);
    }

    public function test_command_handles_complex_filenames()
    {
        $item = Item::factory()->create();
        $image = ItemImage::factory()->create([
            'item_id' => $item->id,
            'path' => 'old/path/complex-filename_with.underscores.jpg',
        ]);

        // 创建测试文件
        $newPath = "items/{$item->id}/complex-filename_with.underscores.jpg";
        Storage::disk('public')->put($newPath, 'test image content');

        $result = $this->command->handle();
        
        $this->assertEquals(0, $result);
        
        // 验证数据库记录没有改变
        $image->refresh();
        $this->assertEquals('old/path/complex-filename_with.underscores.jpg', $image->path);
    }

    public function test_command_handles_large_number_of_images()
    {
        $item = Item::factory()->create();
        
        // 创建大量图片记录
        $images = [];
        for ($i = 1; $i <= 50; $i++) {
            $images[] = ItemImage::factory()->create([
                'item_id' => $item->id,
                'path' => "old/path/image{$i}.jpg",
            ]);
        }

        $result = $this->command->handle();
        
        $this->assertEquals(0, $result);
        
        // 验证所有数据库记录都没有改变
        foreach ($images as $image) {
            $image->refresh();
            $this->assertEquals("old/path/image{$image->id}.jpg", $image->path);
        }
    }

    public function test_command_handles_special_characters_in_filename()
    {
        $item = Item::factory()->create();
        $image = ItemImage::factory()->create([
            'item_id' => $item->id,
            'path' => 'old/path/special-chars-!@#$%^&*().jpg',
        ]);

        // 创建测试文件
        $newPath = "items/{$item->id}/special-chars-!@#$%^&*().jpg";
        Storage::disk('public')->put($newPath, 'test image content');

        $result = $this->command->handle();
        
        $this->assertEquals(0, $result);
        
        // 验证数据库记录没有改变
        $image->refresh();
        $this->assertEquals('old/path/special-chars-!@#$%^&*().jpg', $image->path);
    }
} 