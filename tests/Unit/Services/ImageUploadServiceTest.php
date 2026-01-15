<?php

namespace Tests\Unit\Services;

use App\Models\Thing\Item;
use App\Models\Thing\ItemImage;
use App\Services\ImageUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImageUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ImageUploadService $imageUploadService;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->imageUploadService = new ImageUploadService();
        $this->item = Item::factory()->create();
        
        // Fake the queue to avoid actual job processing
        Queue::fake();
        
        // Create storage directory
        Storage::fake('public');
    }

    public function test_process_uploaded_images_successfully()
    {
        $uploadedImages = [
            UploadedFile::fake()->image('test1.jpg'),
            UploadedFile::fake()->image('test2.png'),
        ];

        $result = $this->imageUploadService->processUploadedImages($uploadedImages, $this->item);

        $this->assertEquals(2, $result);
        $this->assertDatabaseHas('thing_item_images', [
            'item_id' => $this->item->id,
            'path' => 'items/' . $this->item->id . '/test1.jpg',
            'is_primary' => true,
            'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('thing_item_images', [
            'item_id' => $this->item->id,
            'path' => 'items/' . $this->item->id . '/test2.png',
            'is_primary' => false,
            'sort_order' => 2,
        ]);
    }

    public function test_process_uploaded_images_with_existing_images()
    {
        // Create existing image
        ItemImage::create([
            'item_id' => $this->item->id,
            'path' => 'items/' . $this->item->id . '/existing.jpg',
            'is_primary' => true,
            'sort_order' => 1,
        ]);

        $uploadedImages = [
            UploadedFile::fake()->image('new.jpg'),
        ];

        $result = $this->imageUploadService->processUploadedImages($uploadedImages, $this->item);

        $this->assertEquals(1, $result);
        $this->assertDatabaseHas('thing_item_images', [
            'item_id' => $this->item->id,
            'path' => 'items/' . $this->item->id . '/new.jpg',
            'is_primary' => false,
            'sort_order' => 2,
        ]);
    }

    public function test_process_uploaded_images_handles_file_move_failure()
    {
        // Mock a file that will fail to move
        $mockFile = $this->createMock(UploadedFile::class);
        $mockFile->method('getClientOriginalName')->willReturn('test.jpg');
        $mockFile->method('move')->willThrowException(new \Exception('移动图片文件失败'));

        $uploadedImages = [$mockFile];

        $result = $this->imageUploadService->processUploadedImages($uploadedImages, $this->item);

        $this->assertEquals(0, $result);
        $this->assertDatabaseMissing('thing_item_images', [
            'item_id' => $this->item->id,
        ]);
    }

    public function test_process_image_paths_successfully()
    {
        // Create temporary files
        $tempDir = storage_path('app/public/uploads');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        $tempFile = $tempDir . '/temp.jpg';
        file_put_contents($tempFile, 'fake image content');

        $imagePaths = ['uploads/temp.jpg'];

        $this->imageUploadService->processImagePaths($imagePaths, $this->item);

        $this->assertDatabaseHas('thing_item_images', [
            'item_id' => $this->item->id,
            'path' => 'items/' . $this->item->id . '/temp.jpg',
            'is_primary' => true,
            'sort_order' => 1,
        ]);

        // Clean up
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }

    public function test_process_image_paths_with_thumbnail()
    {
        // Create temporary files including thumbnail
        $tempDir = storage_path('app/public/uploads');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        $tempFile = $tempDir . '/temp.jpg';
        $thumbFile = $tempDir . '/temp-thumb.jpg';
        file_put_contents($tempFile, 'fake image content');
        file_put_contents($thumbFile, 'fake thumbnail content');

        $imagePaths = ['uploads/temp.jpg'];

        $this->imageUploadService->processImagePaths($imagePaths, $this->item);

        $this->assertDatabaseHas('thing_item_images', [
            'item_id' => $this->item->id,
            'path' => 'items/' . $this->item->id . '/temp.jpg',
        ]);

        // Clean up
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
        if (file_exists($thumbFile)) {
            unlink($thumbFile);
        }
    }

    public function test_process_image_paths_ignores_invalid_paths()
    {
        $imagePaths = ['invalid/path.jpg', 'uploads/temp.jpg'];

        $result = $this->imageUploadService->processImagePaths($imagePaths, $this->item);

        // Should not create any images since both paths are invalid
        $this->assertDatabaseMissing('thing_item_images', [
            'item_id' => $this->item->id,
        ]);
    }

    public function test_update_image_order()
    {
        // Create test images
        $image1 = ItemImage::create([
            'item_id' => $this->item->id,
            'path' => 'items/' . $this->item->id . '/image1.jpg',
            'sort_order' => 1,
        ]);
        $image2 = ItemImage::create([
            'item_id' => $this->item->id,
            'path' => 'items/' . $this->item->id . '/image2.jpg',
            'sort_order' => 2,
        ]);

        $imageOrder = [$image2->id, $image1->id];

        $this->imageUploadService->updateImageOrder($imageOrder, $this->item);

        $this->assertEquals(1, $image2->fresh()->sort_order);
        $this->assertEquals(2, $image1->fresh()->sort_order);
    }

    public function test_set_primary_image()
    {
        // Create test images
        $image1 = ItemImage::create([
            'item_id' => $this->item->id,
            'path' => 'items/' . $this->item->id . '/image1.jpg',
            'is_primary' => true,
        ]);
        $image2 = ItemImage::create([
            'item_id' => $this->item->id,
            'path' => 'items/' . $this->item->id . '/image2.jpg',
            'is_primary' => false,
        ]);

        $this->imageUploadService->setPrimaryImage($image2->id, $this->item);

        $this->assertFalse($image1->fresh()->is_primary);
        $this->assertTrue($image2->fresh()->is_primary);
    }

    public function test_delete_images_by_ids()
    {
        // Create test images
        $image1 = ItemImage::create([
            'item_id' => $this->item->id,
            'path' => 'items/' . $this->item->id . '/image1.jpg',
        ]);
        $image2 = ItemImage::create([
            'item_id' => $this->item->id,
            'path' => 'items/' . $this->item->id . '/image2.jpg',
        ]);

        $imageIdsToDelete = [$image1->id];

        $this->imageUploadService->deleteImagesByIds($imageIdsToDelete, $this->item);

        $this->assertDatabaseMissing('thing_item_images', ['id' => $image1->id]);
        $this->assertDatabaseHas('thing_item_images', ['id' => $image2->id]);
    }

    public function test_delete_all_item_images()
    {
        // Create test images
        ItemImage::create([
            'item_id' => $this->item->id,
            'path' => 'items/' . $this->item->id . '/image1.jpg',
        ]);
        ItemImage::create([
            'item_id' => $this->item->id,
            'path' => 'items/' . $this->item->id . '/image2.jpg',
        ]);

        $this->imageUploadService->deleteAllItemImages($this->item);

        $this->assertDatabaseMissing('thing_item_images', [
            'item_id' => $this->item->id,
        ]);
    }
}
