<?php

namespace Tests\Unit\Services;

use App\Models\Thing\Item;
use App\Models\Thing\ItemImage;
use App\Services\ImageUploadService;
use App\Jobs\GenerateThumbnailForItemImageJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue; // For job dispatch assertion
use Illuminate\Foundation\Testing\RefreshDatabase; // If interacting with DB
use Tests\TestCase;

class ImageUploadServiceTest extends TestCase
{
    use RefreshDatabase; // Use this if your test actually writes to an in-memory DB

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public'); // Use Laravel's fake storage
        // Log::shouldReceive('error')->andReturnNull(); // Optional: Mock Log facade
        Queue::fake(); // Fake the queue to test job dispatching
    }

    public function testProcessUploadedImagesSuccess()
    {
        // Attempt to create an Item. If factory is not configured, this might fail.
        // For robustness in this subtask, let's create it manually if factory fails.
        try {
            // Ensure a user exists for the item's user_id
            $user = \App\Models\User::factory()->create();
            $item = Item::factory()->create(['user_id' => $user->id]);
        } catch (\Exception $e) {
            // Fallback if factories are not set up or fail
            if (!\App\Models\User::find(1)) { // Check if user with ID 1 exists
                 \App\Models\User::factory()->create(['id' => 1]); // Create user if not exists
            }
             // Ensure all required fields for Item are provided for ::create method
             // Adjust these fields based on your actual Item model's fillable attributes and constraints
            $item = Item::create([
                'id' => 1, // Assuming ID can be manually set or RefreshDatabase handles it
                'user_id' => 1, 
                'name' => 'Test Item', 
                'is_public' => true,
                // Add any other required fields here, e.g., category_id, spot_id if they don't have defaults
                // 'category_id' => null, // Example if nullable
                // 'spot_id' => null, // Example if nullable
            ]);
        }


        // Create mock UploadedFile
        $file1 = UploadedFile::fake()->image('photo1.jpg');
        $file2 = UploadedFile::fake()->image('photo2.png');
        $mockedFiles = [$file1, $file2];

        $service = new ImageUploadService();
        $successCount = $service->processUploadedImages($mockedFiles, $item);

        $this->assertEquals(2, $successCount);

        $itemImageRecords = ItemImage::where('item_id', $item->id)->get();
        $this->assertCount(2, $itemImageRecords);

        foreach ($itemImageRecords as $record) {
            Storage::disk('public')->assertExists($record->path); // Check original image
        }

        Queue::assertPushed(GenerateThumbnailForItemImageJob::class, 2);

        // To check if the job was dispatched with the correct ItemImage instance
        foreach ($itemImageRecords as $itemImageRecord) {
            Queue::assertPushed(GenerateThumbnailForItemImageJob::class, function ($job) use ($itemImageRecord) {
                return $job->itemImage->id === $itemImageRecord->id;
            });
        }
    }
}
