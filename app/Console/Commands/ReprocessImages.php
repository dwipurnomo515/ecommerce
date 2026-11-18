<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('images:reprocess')]
#[Description('Strip metadata (EXIF/GPS) from photos uploaded before processing existed and create thumbnails')]
class ReprocessImages extends Command
{
    /**
     * Safe to run again: it only touches images without a thumbnail, and
     * each one is replaced (new files, row updated, old file deleted) on
     * its own, so a crash halfway leaves no image broken.
     */
    public function handle(): int
    {
        $done = 0;
        $failed = 0;
        $disk = ProductImage::disk();

        ProductImage::query()->whereNull('thumb_path')->chunkById(50, function ($images) use ($disk, &$done, &$failed): void {
            foreach ($images as $image) {
                $temp = tempnam(sys_get_temp_dir(), 'img');

                try {
                    file_put_contents($temp, $disk->get($image->path));
                    $oldPath = $image->path;

                    $image->update(ProductImage::storeProcessed($temp, $image->product_id));

                    // Only after the row points at the new files.
                    $disk->delete($oldPath);
                    $done++;
                } catch (Throwable $e) {
                    $failed++;
                    $this->warn("Foto #{$image->id} gagal: {$e->getMessage()}");
                } finally {
                    @unlink($temp);
                }
            }
        });

        $this->info("{$done} foto diproses ulang, {$failed} gagal.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
