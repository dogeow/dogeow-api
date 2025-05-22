<?php

namespace App\Services;

use App\Models\Thing\Item;
use App\Models\Thing\ItemImage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager; // Keep for now, might be used in other methods or future direct operations
use Intervention\Image\Drivers\Gd\Driver; // Keep for now, might be used in other methods or future direct operations
use App\Jobs\GenerateThumbnailForItemImageJob; // Added

class ImageUploadService
{
    /**
     * Process uploaded images, save them, and create thumbnails.
     *
     * @param array $uploadedImages Array of uploaded files.
     * @param Item $item The item to associate images with.
     * @return int Count of successfully processed images.
     */
    public function processUploadedImages(array $uploadedImages, Item $item): int
    {
        $sortOrder = ItemImage::where('item_id', $item->id)->max('sort_order') ?? 0;
        // $manager = new ImageManager(new Driver()); // Manager not used directly here anymore
        $successCount = 0;

        // Ensure storage directory exists
        $dirPath = storage_path('app/public/items/' . $item->id);
        if (!file_exists($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        foreach ($uploadedImages as $image) {
            try {
                $sortOrder++;
                $basename = uniqid();
                $ext = $image->getClientOriginalExtension() ?: 'jpg';
                $filename = $basename . '.' . $ext;
                // $thumbnailFilename = $basename . '-thumb.' . $ext; // Not generated here
                $relativePath = 'items/' . $item->id . '/' . $filename;
                // $relativeThumbPath = 'items/' . $item->id . '/' . $thumbnailFilename; // Not generated here

                if ($image->move($dirPath, $filename)) {
                    $isPrimary = ($sortOrder === 1 && !ItemImage::where('item_id', $item->id)
                        ->where('is_primary', true)->exists());

                    $itemImage = ItemImage::create([
                        'item_id' => $item->id,
                        'path' => $relativePath,
                        'thumbnail_path' => null, // Will be updated by the job
                        'is_primary' => $isPrimary,
                        'sort_order' => $sortOrder,
                    ]);

                    GenerateThumbnailForItemImageJob::dispatch($itemImage);
                    $successCount++;
                    
                } else {
                    throw new \Exception('Moving image file failed');
                }
            } catch (\Exception $e) {
                Log::error('Image processing error: ' . $e->getMessage(), [
                    'file' => $image->getClientOriginalName(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        return $successCount;
    }

    /**
     * Process image paths from 'uploads' directory, move them to item's directory, and create thumbnails.
     *
     * @param array $imagePaths Array of image paths (e.g., 'uploads/tempfile.jpg').
     * @param Item $item The item to associate images with.
     */
    public function processImagePaths(array $imagePaths, Item $item): void
    {
        $dirPath = storage_path('app/public/items/' . $item->id);
        if (!file_exists($dirPath)) {
            mkdir($dirPath, 0755, true);
        }
        // $manager = new ImageManager(new Driver()); // Manager not used directly here anymore
        $currentMaxSortOrder = ItemImage::where('item_id', $item->id)->max('sort_order') ?? 0;

        foreach ($imagePaths as $index => $originPath) {
            if (!str_starts_with($originPath, 'uploads/')) continue;

            $originAbsPath = storage_path('app/public/' . $originPath);
            if (!file_exists($originAbsPath)) continue;

            $ext = pathinfo($originAbsPath, PATHINFO_EXTENSION) ?: 'jpg';
            $basename = uniqid();
            $filename = $basename . '.' . $ext;
            // $thumbFilename = $basename . '-thumb.' . $ext; // Not generated here
            $itemPath = 'items/' . $item->id . '/' . $filename;
            // $itemThumbPath = 'items/' . $item->id . '/' . $thumbFilename; // Not generated here
            $absItemPath = $dirPath . '/' . $filename;
            // $absThumbPath = $dirPath . '/' . $thumbFilename; // Not generated here

            if (rename($originAbsPath, $absItemPath)) {
                $currentMaxSortOrder++;
                // Set as primary only if it's the first image being added AND no other primary image exists for this item
                $isPrimary = ($currentMaxSortOrder === 1 && !ItemImage::where('item_id', $item->id)->where('is_primary', true)->exists());

                $itemImage = ItemImage::create([
                    'item_id' => $item->id,
                    'path' => $itemPath,
                    'thumbnail_path' => null, // Will be updated by the job
                    'is_primary' => $isPrimary,
                    'sort_order' => $currentMaxSortOrder,
                    // 'origin_path' => $originPath, // If you want to track the original path from uploads
                ]);

                GenerateThumbnailForItemImageJob::dispatch($itemImage);

            } else {
                Log::error('Failed to move image file from uploads.', ['origin_path' => $originPath, 'destination' => $absItemPath]);
            }
        }
    }


    /**
     * Update the sort order of images for an item.
     *
     * @param array $imageOrder Array where key is sort order (0-indexed) and value is image ID.
     * @param Item $item The item whose images are being reordered.
     */
    public function updateImageOrder(array $imageOrder, Item $item): void
    {
        foreach ($imageOrder as $order => $imageId) {
            ItemImage::where('id', $imageId)
                ->where('item_id', $item->id)
                ->update(['sort_order' => $order + 1]);
        }
    }

    /**
     * Set a specific image as the primary image for an item.
     *
     * @param int $primaryImageId The ID of the image to set as primary.
     * @param Item $item The item for which to set the primary image.
     */
    public function setPrimaryImage(int $primaryImageId, Item $item): void
    {
        // Reset current primary image (if any)
        ItemImage::where('item_id', $item->id)
            ->update(['is_primary' => false]);

        // Set new primary image
        ItemImage::where('id', $primaryImageId)
            ->where('item_id', $item->id)
            ->update(['is_primary' => true]);
    }

    /**
     * Delete specified images by their IDs and remove their files.
     *
     * @param array $imageIdsToDelete Array of image IDs to delete.
     * @param Item $item The item from which images are being deleted.
     */
    public function deleteImagesByIds(array $imageIdsToDelete, Item $item): void
    {
        $imagesToDelete = ItemImage::whereIn('id', $imageIdsToDelete)
            ->where('item_id', $item->id)
            ->get();

        foreach ($imagesToDelete as $image) {
            Storage::disk('public')->delete($image->path);
            if ($image->thumbnail_path) {
                Storage::disk('public')->delete($image->thumbnail_path);
            }
            $image->delete();
        }
    }

    /**
     * Delete all images (files and records) associated with an item.
     * This is typically used when deleting the item itself.
     *
     * @param Item $item The item whose images are to be deleted.
     */
    public function deleteAllItemImages(Item $item): void
    {
        $images = $item->images; // Assumes 'images' relationship is loaded or loads lazily
        foreach ($images as $image) {
            Storage::disk('public')->delete($image->path);
            if ($image->thumbnail_path) {
                Storage::disk('public')->delete($image->thumbnail_path);
            }
        }
        // After deleting files, delete the records
        ItemImage::where('item_id', $item->id)->delete();
    }
}
