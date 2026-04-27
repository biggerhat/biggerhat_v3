<?php

namespace App\Traits\TOS;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HandlesTosImageUpload
{
    /**
     * Persist an uploaded image to the public disk and return its stored
     * path (relative to storage/app/public). Mirrors the Malifaux Miniature
     * admin pattern. Returns null when `$file` is null — caller decides how
     * to merge the result into the validated payload.
     *
     * JPEG and PNG uploads are resized so the longest side fits
     * `$maxLongestSide` (default 1600px) and re-encoded preserving format —
     * this keeps card art readable at the sizes the UI shows it while
     * trimming a typical 4-6 MB phone-camera upload to ~150-400 KB. SVG, GIF,
     * and anything GD can't decode falls through and stores raw, so logos +
     * other vector assets aren't damaged.
     */
    protected function storeTosImage(?UploadedFile $file, string $folder, int $maxLongestSide = 1600): ?string
    {
        if (! $file) {
            return null;
        }

        $compressed = $this->compressTosImage($file, $maxLongestSide);

        if ($compressed !== null) {
            [$bytes, $extension] = $compressed;
            $fileName = sprintf('%s.%s', Str::uuid(), $extension);
            $path = trim($folder, '/').'/'.$fileName;
            Storage::disk('public')->put($path, $bytes);

            return $path;
        }

        $fileName = sprintf('%s.%s', Str::uuid(), $file->extension());
        $path = trim($folder, '/').'/'.$fileName;
        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

    /**
     * Decode a JPEG or PNG upload, resize it so its longest side does not
     * exceed `$maxLongestSide`, and re-encode preserving format. Returns
     * `[bytes, extension]` or null when the file isn't a JPEG/PNG GD can
     * load (callers fall back to storing raw bytes).
     *
     * @return array{0: string, 1: string}|null
     */
    private function compressTosImage(UploadedFile $file, int $maxLongestSide): ?array
    {
        $info = @getimagesize($file->getRealPath());
        if ($info === false) {
            Log::warning('TOS image upload: getimagesize failed; storing raw bytes.', [
                'mime' => $file->getMimeType(),
                'name' => $file->getClientOriginalName(),
            ]);

            return null;
        }

        $type = $info[2];
        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file->getRealPath()),
            IMAGETYPE_PNG => @imagecreatefrompng($file->getRealPath()),
            default => null,
        };

        if (! $source) {
            Log::warning('TOS image upload: GD decode failed; storing raw bytes.', [
                'mime' => $file->getMimeType(),
                'image_type' => $type,
                'name' => $file->getClientOriginalName(),
            ]);

            return null;
        }

        $srcWidth = imagesx($source);
        $srcHeight = imagesy($source);
        $longest = max($srcWidth, $srcHeight);

        if ($longest > $maxLongestSide) {
            $scale = $maxLongestSide / $longest;
            $targetWidth = (int) round($srcWidth * $scale);
            $targetHeight = (int) round($srcHeight * $scale);
            $resized = imagecreatetruecolor($targetWidth, $targetHeight);
            // Preserve PNG transparency through the resize step.
            if ($type === IMAGETYPE_PNG) {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefilledrectangle($resized, 0, 0, $targetWidth, $targetHeight, $transparent);
            }
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $srcWidth, $srcHeight);
            imagedestroy($source);
            $source = $resized;
        }

        ob_start();
        if ($type === IMAGETYPE_PNG) {
            imagepng($source, null, 6);
            $extension = 'png';
        } else {
            imagejpeg($source, null, 85);
            $extension = 'jpg';
        }
        $bytes = ob_get_clean();
        imagedestroy($source);

        return [(string) $bytes, $extension];
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
