<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Storage;

trait OptimizesStoredImages
{
    protected function optimizeStoredImage(string $storedPath, string $diskName = 'public', int $maxDimension = 1600): void
    {
        if ($storedPath === '' || ! function_exists('imagecreatefromstring')) {
            return;
        }

        $disk = Storage::disk($diskName);
        if (! $disk->exists($storedPath)) {
            return;
        }

        $fullPath = $disk->path($storedPath);
        $binary = @file_get_contents($fullPath);

        if ($binary === false) {
            return;
        }

        $image = @imagecreatefromstring($binary);
        if (! $image) {
            return;
        }

        $workingImage = $image;

        try {
            $width = imagesx($image);
            $height = imagesy($image);
            $largestSide = max($width, $height);

            if ($largestSide > $maxDimension && function_exists('imagecreatetruecolor')) {
                $scale = $maxDimension / $largestSide;
                $targetWidth = max(1, (int) round($width * $scale));
                $targetHeight = max(1, (int) round($height * $scale));

                $resized = imagecreatetruecolor($targetWidth, $targetHeight);
                if ($resized) {
                    if (function_exists('imagealphablending')) {
                        imagealphablending($resized, false);
                    }

                    if (function_exists('imagesavealpha')) {
                        imagesavealpha($resized, true);
                    }

                    $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                    imagefilledrectangle($resized, 0, 0, $targetWidth, $targetHeight, $transparent);
                    imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

                    $workingImage = $resized;
                }
            }

            $this->saveOptimizedImage($workingImage, $fullPath);
        } finally {
            if ($workingImage !== $image && (is_object($workingImage) || is_resource($workingImage))) {
                imagedestroy($workingImage);
            }

            if (is_object($image) || is_resource($image)) {
                imagedestroy($image);
            }
        }
    }

    protected function saveOptimizedImage(mixed $image, string $fullPath): void
    {
        $type = function_exists('exif_imagetype') ? @exif_imagetype($fullPath) : null;
        $saved = false;

        if ($type === IMAGETYPE_PNG && function_exists('imagepng')) {
            $saved = (bool) @imagepng($image, $fullPath, 6);
        } elseif ($type === IMAGETYPE_GIF && function_exists('imagegif')) {
            $saved = (bool) @imagegif($image, $fullPath);
        } elseif ($type === IMAGETYPE_WEBP && function_exists('imagewebp')) {
            $saved = (bool) @imagewebp($image, $fullPath, 82);
        } elseif (function_exists('imagejpeg')) {
            $saved = (bool) @imagejpeg($image, $fullPath, 86);
        }

        if (! $saved && function_exists('imagejpeg')) {
            @imagejpeg($image, $fullPath, 86);
        }
    }
}
