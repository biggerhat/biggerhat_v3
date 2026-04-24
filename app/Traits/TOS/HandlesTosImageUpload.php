<?php

namespace App\Traits\TOS;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HandlesTosImageUpload
{
    /**
     * Persist an uploaded image to the public disk under the given folder and
     * return the stored path (relative to storage/app/public). Mirrors the
     * Malifaux Miniature admin pattern.
     *
     * Returns null when `$file` is null — caller decides how to merge the
     * result into the validated payload (`unset()` on update to leave the
     * existing column untouched, or `null` on store).
     */
    protected function storeTosImage(?UploadedFile $file, string $folder): ?string
    {
        if (! $file) {
            return null;
        }

        $fileName = sprintf('%s.%s', Str::uuid(), $file->extension());
        $path = trim($folder, '/').'/'.$fileName;
        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

    /**
     * Delete a previously-uploaded image from the public disk. Safe to call
     * with null or a missing file.
     */
    protected function deleteTosImage(?string $path): void
    {
        if (! $path) {
            return;
        }
        Storage::disk('public')->delete($path);
    }

    /**
     * Merge a front/back JPEG pair into a single wide combination image on the
     * public disk and return the stored path. Mirrors the Malifaux Miniature
     * combo pattern (resize both sides to 550x950, place side-by-side).
     */
    protected function generateTosComboImage(string $frontPath, string $backPath, string $folder): string
    {
        $targetWidth = 550;
        $targetHeight = 950;

        $frontSrc = imagecreatefromjpeg(Storage::disk('public')->path($frontPath));
        $backSrc = imagecreatefromjpeg(Storage::disk('public')->path($backPath));

        $front = $this->resizeTosImage($frontSrc, $targetWidth, $targetHeight);
        $back = $this->resizeTosImage($backSrc, $targetWidth, $targetHeight);

        imagedestroy($frontSrc);
        imagedestroy($backSrc);

        $output = imagecreatetruecolor($targetWidth * 2, $targetHeight);
        imagecopy($output, $front, 0, 0, 0, 0, $targetWidth, $targetHeight);
        imagecopy($output, $back, $targetWidth, 0, 0, 0, $targetWidth, $targetHeight);

        imagedestroy($front);
        imagedestroy($back);

        $path = trim($folder, '/').'/'.Str::uuid().'.jpg';
        imagejpeg($output, Storage::disk('public')->path($path));
        imagedestroy($output);

        return $path;
    }

    private function resizeTosImage(\GdImage $source, int $targetWidth, int $targetHeight): \GdImage
    {
        $srcWidth = imagesx($source);
        $srcHeight = imagesy($source);

        if ($srcWidth === $targetWidth && $srcHeight === $targetHeight) {
            return $source;
        }

        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $srcWidth, $srcHeight);

        return $resized;
    }
}
