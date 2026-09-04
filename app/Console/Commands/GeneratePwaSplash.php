<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Generate per-device iOS launch images for PWA cold start.
 *
 * Solid-bg PNG with the app icon centered, referenced from
 * partials/_apple-splash.blade.php via media queries so iOS Safari picks the
 * right one when launching from the home screen — removes the white flash
 * that appears before the SPA hydrates.
 *
 *   php artisan pwa:splash
 */
class GeneratePwaSplash extends Command
{
    protected $signature = 'pwa:splash';

    protected $description = 'Generate iOS launch images into public/icons/splash';

    private const BG_RGB = [33, 33, 33];
    private const FG_RGB = [250, 250, 250];

    /** [file, w, h] — PNG pixels = device pixels. */
    private const DEVICES = [
        ['file' => 'splash-750x1334.png',  'w' => 750,  'h' => 1334],  // iPhone SE 2/3 + 8
        ['file' => 'splash-1242x2208.png', 'w' => 1242, 'h' => 2208], // iPhone 8 Plus
        ['file' => 'splash-1125x2436.png', 'w' => 1125, 'h' => 2436], // iPhone X/XS/11 Pro, 12/13 mini
        ['file' => 'splash-828x1792.png',  'w' => 828,  'h' => 1792], // iPhone XR / 11
        ['file' => 'splash-1170x2532.png', 'w' => 1170, 'h' => 2532], // iPhone 12/13/14
        ['file' => 'splash-1284x2778.png', 'w' => 1284, 'h' => 2778], // iPhone 12/13 Pro Max, 14 Plus
        ['file' => 'splash-1179x2556.png', 'w' => 1179, 'h' => 2556], // iPhone 14 Pro
        ['file' => 'splash-2556x2778.png', 'w' => 2556, 'h' => 2778], // iPhone 14 Pro Max
        ['file' => 'splash-744x1133.png',  'w' => 744,  'h' => 1133], // iPad mini 6
        ['file' => 'splash-820x1180.png',  'w' => 820,  'h' => 1180], // iPad 10
        ['file' => 'splash-834x1194.png',  'w' => 834,  'h' => 1194], // iPad Pro 11
        ['file' => 'splash-1024x1366.png', 'w' => 1024, 'h' => 1366], // iPad Pro 12.9
    ];

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('GD extension required.');

            return self::FAILURE;
        }

        $outDir = public_path('icons/splash');
        if (! is_dir($outDir) && ! File::makeDirectory($outDir, 0775, true) && ! is_dir($outDir)) {
            $this->error("Failed to create {$outDir}");

            return self::FAILURE;
        }

        $appName = (string) (config('branding.app_name') ?? 'App');
        $letter = mb_strtoupper(mb_substr(trim($appName), 0, 1)) ?: 'S';
        $fontPath = $this->findFont();

        foreach (self::DEVICES as $device) {
            $img = $this->drawSplash($device['w'], $device['h'], $letter, $fontPath);
            imagepng($img, $outDir.'/'.$device['file'], 9);
            imagedestroy($img);
            $this->line("wrote {$outDir}/{$device['file']}");
        }

        $this->info('iOS splash images generated in '.$outDir);

        return self::SUCCESS;
    }

    /**
     * Solid-bg canvas with a squircle icon centered, ~22% of shorter edge —
     * matches iOS launch screen visual rhythm.
     */
    private function drawSplash(int $w, int $h, string $letter, ?string $fontPath)
    {
        $img = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocate($img, self::BG_RGB[0], self::BG_RGB[1], self::BG_RGB[2]);
        imagefill($img, 0, 0, $bg);

        $iconSize = (int) (min($w, $h) * 0.22);
        $iconX = (int) (($w - $iconSize) / 2);
        $iconY = (int) (($h - $iconSize) / 2);

        // Squircle slightly darker than splash bg for subtle contrast.
        $sqColor = imagecolorallocate($img, 25, 25, 25);
        $radius = (int) ($iconSize / 2 * 0.42);
        $this->roundedRect($img, $iconX, $iconY, $iconX + $iconSize, $iconY + $iconSize, $radius, $sqColor);

        $fg = imagecolorallocate($img, self::FG_RGB[0], self::FG_RGB[1], self::FG_RGB[2]);
        $fontSize = (int) ($iconSize * 0.55);
        if ($fontPath !== null) {
            $box = imageftbbox($fontSize, 0, $fontPath, $letter);
            $boxW = $box[2] - $box[0];
            $boxH = $box[1] - $box[7];
            $tx = (int) ($iconX + ($iconSize - $boxW) / 2 - $box[0]);
            $ty = (int) ($iconY + ($iconSize - $boxH) / 2 - $box[7]);
            imagefttext($img, $fontSize, 0, $tx, $ty, $fg, $fontPath, $letter);
        } else {
            imagestring($img, 5, $iconX + (int) ($iconSize / 2 - 8), $iconY + (int) ($iconSize / 2 - 12), $letter, $fg);
        }

        return $img;
    }

    private function roundedRect($img, int $x1, int $y1, int $x2, int $y2, int $r, $color): void
    {
        $r = max(0, $r);
        imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($img, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        imagefilledellipse($img, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($img, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($img, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $color);
        imagefilledellipse($img, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $color);
    }

    private function findFont(): ?string
    {
        $candidates = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
            '/Library/Fonts/Arial Bold.ttf',
            'C:/Windows/Fonts/arialbd.ttf',
        ];
        foreach ($candidates as $c) {
            if (is_file($c) && is_readable($c)) {
                return $c;
            }
        }

        return null;
    }
}
