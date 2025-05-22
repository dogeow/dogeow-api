<?php

namespace App\Jobs;

use App\Models\Thing\ItemImage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver; // Or Imagick if preferred

class GenerateThumbnailForItemImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ItemImage $itemImage;

    /**
     * Create a new job instance.
     */
    public function __construct(ItemImage $itemImage)
    {
        $this->itemImage = $itemImage;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!$this->itemImage->path || !Storage::disk('public')->exists($this->itemImage->path)) {
            Log::error("Original image not found for ItemImage ID: {$this->itemImage->id}, Path: {$this->itemImage->path}");
            return;
        }

        $originalFullPath = Storage::disk('public')->path($this->itemImage->path);
        $pathInfo = pathinfo($this->itemImage->path);
        $thumbnailFilename = $pathInfo['filename'] . '-thumb.' . ($pathInfo['extension'] ?? 'jpg');
        // Ensure item specific directory exists for thumbnail (it should, as original is there)
        $thumbnailRelativePath = $pathInfo['dirname'] . '/' . $thumbnailFilename;
        $thumbnailFullPath = Storage::disk('public')->path($thumbnailRelativePath);

        try {
            $manager = new ImageManager(new Driver()); // Consistent with ImageUploadService
            $thumbnail = $manager->read($originalFullPath);
            $thumbnail->cover(200, 200); // Same dimensions as before
            $thumbnail->save($thumbnailFullPath);

            $this->itemImage->thumbnail_path = $thumbnailRelativePath;
            $this->itemImage->save();

            Log::info("Successfully generated thumbnail for ItemImage ID: {$this->itemImage->id}, Path: {$thumbnailRelativePath}");
        } catch (\Exception $e) {
            Log::error("Thumbnail generation failed for ItemImage ID: {$this->itemImage->id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            // Optionally, you could update ItemImage with an error status or retry logic
        }
    }
}
