<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Generate PWA icons (PNG) from scratch using the GD extension.
 *
 * Renders a dark rounded-square icon with a white initial letter,
 * matching the app neutral palette (--primary: oklch(0.205 0 0)).
 *
 * Run once after deploy or whenever branding changes:
 *   php artisan pwa:icons
 */
class GeneratePwaIcons extends Command
{
    protected $signature = 'pwa:icons';

    protected $description = 'Generate PWA PNG icons into public/icons';

    /** Near-black to match --primary in sRGB. */
    private const BG_RGB = [33, 33, 33];

    /** Near-white to match --primary-foreground in sRGB. */
    private const FG_RGB = [250, 250, 250];

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('GD extension required.');

            return self::FAILURE;
        }

        $outDir = public_path('icons');
        if (! is_dir($outDir) && ! File::makeDirectory($outDir, 0775, true) && ! is_dir($outDir)) {
            $this->error("Failed to create {$outDir}");

            return self::FAILURE;
        }

        $appName = (string) (config('branding.app_name') ?? 'App');
        $letter = mb_strtoupper(mb_substr(trim($appName), 0, 1)) ?: 'S';
        $fontPath = $this->findFont();

        // --- Android: standard squircle icons, full size matrix. ---
        foreach ([16, 32, 48, 96, 144, 192, 512] as $size) {
            $this->save(
                $this->drawSquircle($size, 0.42, $letter, $fontPath),
                $outDir."/icon-{$size}.png"
            );
        }

        // --- Android: maskable (full-bleed bg, letter at 33% safe zone). ---
        $this->save($this->drawMaskable(512, $letter, $fontPath), $outDir.'/icon-512-maskable.png');

        // --- Android: monochrome silhouette for themed display. ---
        $this->save($this->drawMonochrome(512, $letter, $fontPath), $outDir.'/icon-512-monochrome.png');

        // --- iOS: solid square touch icons, no transparency. ---
        foreach ([152, 167, 180] as $size) {
            $this->save(
                $this->drawSolidSquare($size, $letter, $fontPath),
                $outDir."/apple-touch-icon-{$size}.png"
            );
        }
        // Keep canonical name expected by Safari (apple-touch-icon.png = 180).
        $this->save($this->drawSolidSquare(180, $letter, $fontPath), $outDir.'/apple-touch-icon.png');
        // 1024 for App Store / future Capacitor launcher asset.
        $this->save($this->drawSolidSquare(1024, $letter, $fontPath), $outDir.'/icon-1024.png');

        $this->info('PWA icons generated in '.$outDir);

        return self::SUCCESS;
    }

    private function drawSquircle(int $size, float $cornerPct, string $letter, ?string $fontPath)
    {
        $img = $this->canvas($size);
        $bg = $this->color($img, self::BG_RGB);
        $radius = (int) ($size / 2 * $cornerPct);
        $this->roundedRect($img, 0, 0, $size - 1, $size - 1, $radius, $bg);
        $this->drawLetter($img, $size, $letter, $fontPath, 0.55);

        return $img;
    }

    private function drawMaskable(int $size, string $letter, ?string $fontPath)
    {
        $img = $this->canvas($size);
        $bg = $this->color($img, self::BG_RGB);
        imagefilledrectangle($img, 0, 0, $size - 1, $size - 1, $bg);
        // Letter scaled smaller so the OS-applied mask cannot crop it.
        $this->drawLetter($img, $size, $letter, $fontPath, 0.33);

        return $img;
    }

    /**
     * Monochrome silhouette: white letter on transparent background.
     * Used by Android "themed icons" — the OS recolors it at runtime.
     */
    private function drawMonochrome(int $size, string $letter, ?string $fontPath)
    {
        $img = $this->canvas($size);
        $this->drawLetter($img, $size, $letter, $fontPath, 0.55);

        return $img;
    }

    private function drawSolidSquare(int $size, string $letter, ?string $fontPath)
    {
        $img = imagecreatetruecolor($size, $size);
        $bg = $this->color($img, self::BG_RGB);
        imagefill($img, 0, 0, $bg);
        $this->drawLetter($img, $size, $letter, $fontPath, 0.55);

        return $img;
    }

    private function drawLetter($img, int $size, string $letter, ?string $fontPath, float $sizePct): void
    {
        $fg = $this->color($img, self::FG_RGB);
        $fontSize = (int) ($size * $sizePct);
        if ($fontPath !== null) {
            $box = imageftbbox($fontSize, 0, $fontPath, $letter);
            $boxW = $box[2] - $box[0];
            $boxH = $box[1] - $box[7];
            $x = (int) (($size - $boxW) / 2 - $box[0]);
            $y = (int) (($size - $boxH) / 2 - $box[7]);
            imagefttext($img, $fontSize, 0, $x, $y, $fg, $fontPath, $letter);

            return;
        }
        // Built-in bitmap fallback (ugly but functional).
        imagestring($img, 5, (int) ($size / 2 - 8), (int) ($size / 2 - 12), $letter, $fg);
    }

    private function canvas(int $size)
    {
        $img = imagecreatetruecolor($size, $size);
        imagesavealpha($img, true);
        imagealphablending($img, true);
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefill($img, 0, 0, $transparent);

        return $img;
    }

    private function color($img, array $rgb)
    {
        return imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
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

    private function save($img, string $path): void
    {
        imagepng($img, $path, 9);
        imagedestroy($img);
        $this->line("wrote {$path}");
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
