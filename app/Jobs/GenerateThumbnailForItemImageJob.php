<?php

namespace App\Jobs;

use App\Models\Thing\ItemImage;
use App\Utils\FileHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class GenerateThumbnailForItemImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public int $maxExceptions = 3;

    protected ItemImage $itemImage;
    protected int $thumbnailWidth;
    protected int $thumbnailHeight;
    protected string $thumbnailSuffix;

    /**
     * Create a new job instance.
     */
    public function __construct(
        ItemImage $itemImage,
        int $thumbnailWidth = 200,
        int $thumbnailHeight = 200,
        string $thumbnailSuffix = '-thumb'
    ) {
        $this->itemImage = $itemImage;
        $this->thumbnailWidth = $thumbnailWidth;
        $this->thumbnailHeight = $thumbnailHeight;
        $this->thumbnailSuffix = $thumbnailSuffix;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Refresh model to get latest data
        $this->itemImage->refresh();

        if (!$this->validateOriginalImage()) {
            return;
        }

        $thumbnailPath = $this->generateThumbnailPath();
        
        // Skip if thumbnail already exists and is newer than original
        if ($this->thumbnailExistsAndIsNewer($thumbnailPath)) {
            Log::info("Thumbnail already exists and is up-to-date for ItemImage ID: {$this->itemImage->id}");
            return;
        }

        $this->generateThumbnail($thumbnailPath);
    }

    /**
     * Validate that the original image exists and is accessible.
     */
    protected function validateOriginalImage(): bool
    {
        if (!$this->itemImage->path) {
            Log::warning("ItemImage ID: {$this->itemImage->id} has no path set");
            return false;
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($this->itemImage->path)) {
            Log::error("Original image not found for ItemImage ID: {$this->itemImage->id}, Path: {$this->itemImage->path}");
            return false;
        }

        // Check if file is readable and not corrupted
        $originalPath = $disk->path($this->itemImage->path);
        if (!FileHelper::isValidFile($originalPath)) {
            Log::error("Original image is not readable or empty for ItemImage ID: {$this->itemImage->id}");
            return false;
        }

        return true;
    }

    /**
     * Generate the thumbnail file path.
     */
    protected function generateThumbnailPath(): string
    {
        $pathInfo = pathinfo($this->itemImage->path);
        $extension = $pathInfo['extension'] ?? 'jpg';
        $thumbnailFilename = $pathInfo['filename'] . $this->thumbnailSuffix . '.' . $extension;
        
        return $pathInfo['dirname'] . '/' . $thumbnailFilename;
    }

    /**
     * Check if thumbnail exists and is newer than the original image.
     */
    protected function thumbnailExistsAndIsNewer(string $thumbnailPath): bool
    {
        $disk = Storage::disk('public');
        
        if (!$disk->exists($thumbnailPath)) {
            return false;
        }

        $originalModified = $disk->lastModified($this->itemImage->path);
        $thumbnailModified = $disk->lastModified($thumbnailPath);

        return $thumbnailModified >= $originalModified;
    }

    /**
     * Generate the thumbnail image.
     */
    protected function generateThumbnail(string $thumbnailPath): void
    {
        $disk = Storage::disk('public');
        $originalFullPath = $disk->path($this->itemImage->path);
        $thumbnailFullPath = $disk->path($thumbnailPath);

        try {
            // Ensure directory exists
            FileHelper::ensureDirectoryExists(dirname($thumbnailFullPath));

            $manager = new ImageManager(new Driver());
            $image = $manager->read($originalFullPath);
            
            // Get original dimensions for logging
            $originalWidth = $image->width();
            $originalHeight = $image->height();
            
            // Only resize if image is larger than thumbnail dimensions
            if ($originalWidth > $this->thumbnailWidth || $originalHeight > $this->thumbnailHeight) {
                $image->cover($this->thumbnailWidth, $this->thumbnailHeight);
            }
            
            // Save with quality optimization
            $image->save($thumbnailFullPath, quality: 85);

            Log::info("Successfully generated thumbnail for ItemImage ID: {$this->itemImage->id}", [
                'original_path' => $this->itemImage->path,
                'thumbnail_path' => $thumbnailPath,
                'original_size' => "{$originalWidth}x{$originalHeight}",
                'thumbnail_size' => "{$this->thumbnailWidth}x{$this->thumbnailHeight}",
                'file_size' => FileHelper::getFormattedFileSize($thumbnailFullPath)
            ]);

        } catch (\Exception $e) {
            Log::error("Thumbnail generation failed for ItemImage ID: {$this->itemImage->id}", [
                'error' => $e->getMessage(),
                'original_path' => $this->itemImage->path,
                'thumbnail_path' => $thumbnailPath,
                'attempt' => $this->attempts(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Clean up partial file if it exists
            if (file_exists($thumbnailFullPath)) {
                unlink($thumbnailFullPath);
            }
            
            throw $e; // Re-throw to trigger retry mechanism
        }
    }



    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Thumbnail generation job failed permanently for ItemImage ID: {$this->itemImage->id}", [
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'path' => $this->itemImage->path
        ]);
    }
}
