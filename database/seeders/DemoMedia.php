<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoMedia
{
    private const FONT = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

    private const FONT_REGULAR = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';

    /** @var array<string, string> */
    private const PORTRAITS = [
        'mr.silverwind1998@gmail.com' => 'man-1.jpg',
        'director@test.uz' => 'man-2.jpg',
        'legal@test.uz' => 'man-3.jpg',
        'manager@test.uz' => 'man-4.jpg',
        'accounting@test.uz' => 'woman-1.jpg',
    ];

    public static function avatarFor(string $email, string $name): ?string
    {
        $portrait = self::PORTRAITS[$email] ?? null;

        if ($portrait !== null && ($stored = self::copyPortrait($portrait, $name)) !== null) {
            return $stored;
        }

        return self::avatar($name);
    }

    private static function copyPortrait(string $file, string $name): ?string
    {
        $source = database_path('seeders/media/avatars/'.$file);

        if (! is_file($source)) {
            return null;
        }

        $extension = pathinfo($file, PATHINFO_EXTENSION) ?: 'jpg';
        $path = 'uploads/images/users/seeded/'.Str::slug(Str::ascii($name)).'.'.$extension;
        Storage::disk('local')->put($path, (string) file_get_contents($source));

        return $path;
    }

    public static function avatar(string $name): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $size = 256;
        [$r, $g, $b] = self::colorFor($name);

        $img = imagecreatetruecolor($size, $size);
        imagefilledrectangle($img, 0, 0, $size, $size, imagecolorallocate($img, $r, $g, $b));

        self::centerText($img, self::initials($name), $size, imagecolorallocate($img, 255, 255, 255), 104);

        $path = 'uploads/images/users/seeded/'.Str::slug(Str::ascii($name)).'.png';
        self::store($img, $path);

        return $path;
    }

    public static function paymentReceipt(string $number, string $amount, string $percent, string $date): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $w = 540;
        $h = 1000;
        $img = imagecreatetruecolor($w, $h);
        imageantialias($img, true);

        $bg = imagecolorallocate($img, 241, 245, 249);
        $white = imagecolorallocate($img, 255, 255, 255);
        $dark = imagecolorallocate($img, 15, 23, 42);
        $muted = imagecolorallocate($img, 100, 116, 139);
        $line = imagecolorallocate($img, 226, 232, 240);
        $green = imagecolorallocate($img, 34, 197, 94);
        $greenSoft = imagecolorallocate($img, 220, 252, 231);

        imagefilledrectangle($img, 0, 0, $w, $h, $bg);

        self::write($img, '9:41', 28, 40, $dark, 20, bold: true);
        imagerectangle($img, $w - 60, 24, $w - 34, 40, $muted);
        imagefilledrectangle($img, $w - 33, 29, $w - 30, 35, $muted);
        imagefilledrectangle($img, $w - 58, 26, $w - 42, 38, $dark);
        self::write($img, '87%', $w - 70, 40, $muted, 18, align: 'right');

        $cx = intdiv($w, 2);
        $cy = 210;
        imagefilledellipse($img, $cx, $cy, 176, 176, $greenSoft);
        imagefilledellipse($img, $cx, $cy, 124, 124, $green);
        imagesetthickness($img, 9);
        imageline($img, $cx - 29, $cy + 2, $cx - 9, $cy + 25, $white);
        imageline($img, $cx - 9, $cy + 25, $cx + 31, $cy - 23, $white);
        imagesetthickness($img, 1);

        self::write($img, 'Платёж выполнен', $cx, 360, $dark, 32, bold: true, align: 'center');
        self::write($img, 'Средства успешно зачислены', $cx, 402, $muted, 19, align: 'center');
        self::write($img, $amount, $cx, 482, $dark, self::fitSize($amount, 44, $w - 64), bold: true, align: 'center');

        self::roundedRect($img, 36, 532, $w - 36, 866, 28, $white);

        $rows = [
            ['Договор', $number],
            ['Доля платежа', $percent],
            ['Дата', $date],
            ['Транзакция', self::reference($number.$date.$percent)],
            ['Способ оплаты', '•••• '.sprintf('%04u', crc32($number) % 10000)],
        ];

        $y = 592;

        foreach ($rows as $i => [$label, $value]) {
            self::write($img, $label, 64, $y, $muted, 19);
            self::write($img, $value, $w - 64, $y, $dark, 19, bold: true, align: 'right');

            if ($i < count($rows) - 1) {
                imagefilledrectangle($img, 64, $y + 22, $w - 64, $y + 23, $line);
            }

            $y += 60;
        }

        self::roundedRect($img, 36, 912, $w - 36, 976, 24, $green);
        self::write($img, 'Готово', $cx, 952, $white, 22, bold: true, align: 'center');

        $path = 'uploads/images/payments/seeded/'.Str::uuid()->toString().'.png';
        self::store($img, $path);

        return $path;
    }

    private static function store(\GdImage $img, string $path): void
    {
        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        Storage::disk('local')->put($path, $bytes);
    }

    private static function write(\GdImage $img, string $text, int $x, int $baseline, int $color, int $size, bool $bold = false, string $align = 'left'): void
    {
        $font = $bold ? self::FONT : self::FONT_REGULAR;

        if (! is_file($font)) {
            imagestring($img, 5, $x, $baseline - 16, $text, $color);

            return;
        }

        if ($align !== 'left') {
            $bbox = imagettfbbox($size, 0, $font, $text);
            $width = $bbox[2] - $bbox[0];
            $x -= $align === 'center' ? intdiv($width, 2) : $width;
        }

        imagettftext($img, $size, 0, $x, $baseline, $color, $font, $text);
    }

    private static function roundedRect(\GdImage $img, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        imagefilledrectangle($img, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
        imagefilledrectangle($img, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagefilledellipse($img, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($img, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($img, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($img, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    }

    private static function fitSize(string $text, int $size, int $maxWidth): int
    {
        if (! is_file(self::FONT)) {
            return $size;
        }

        while ($size > 18) {
            $bbox = imagettfbbox($size, 0, self::FONT, $text);

            if (($bbox[2] - $bbox[0]) <= $maxWidth) {
                break;
            }

            $size -= 2;
        }

        return $size;
    }

    private static function reference(string $seed): string
    {
        return implode(' ', str_split(sprintf('%012u', crc32($seed) % 1_000_000_000_000), 4));
    }

    private static function centerText(\GdImage $img, string $text, int $box, int $color, int $size): void
    {
        if (is_file(self::FONT)) {
            $bbox = imagettfbbox($size, 0, self::FONT, $text);
            $x = (int) (($box - ($bbox[2] - $bbox[0])) / 2);
            $y = (int) (($box + ($bbox[1] - $bbox[7])) / 2);
            imagettftext($img, $size, 0, $x, $y, $color, self::FONT, $text);

            return;
        }

        imagestring($img, 5, (int) ($box / 2) - 18, (int) ($box / 2) - 8, $text, $color);
    }

    private static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr($parts[1] ?? '', 0, 1));

        return $initials !== '' ? $initials : '?';
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function colorFor(string $name): array
    {
        $palette = [
            [37, 99, 235], [5, 150, 105], [217, 119, 6], [219, 39, 119],
            [124, 58, 237], [8, 145, 178], [202, 138, 4], [220, 38, 38],
        ];

        return $palette[crc32($name) % count($palette)];
    }
}
