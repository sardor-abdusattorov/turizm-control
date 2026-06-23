<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates lightweight placeholder media for the demo dataset — initials
 * avatars and "payment receipt" screenshots — so the showcase has real files
 * on disk instead of broken image links. Pure GD; degrades to a flat block of
 * colour when the bundled font is unavailable.
 */
class DemoMedia
{
    private const FONT = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

    /**
     * An initials avatar on a colour derived from the name. Returns the stored
     * relative path on the local disk (or null if GD is unavailable).
     */
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

    /**
     * A simple payment-receipt screenshot. Returns the stored relative path on
     * the local disk (or null if GD is unavailable).
     */
    public static function paymentReceipt(string $number, string $amount, string $percent, string $date): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $w = 720;
        $h = 420;
        $img = imagecreatetruecolor($w, $h);
        imagefilledrectangle($img, 0, 0, $w, $h, imagecolorallocate($img, 248, 250, 252));
        imagefilledrectangle($img, 0, 0, $w, 92, imagecolorallocate($img, 16, 185, 129));

        $white = imagecolorallocate($img, 255, 255, 255);
        $dark = imagecolorallocate($img, 30, 41, 59);
        $muted = imagecolorallocate($img, 100, 116, 139);

        self::text($img, 'ОПЛАТА ПОДТВЕРЖДЕНА', 40, 58, $white, 24);
        self::text($img, 'Договор: '.$number, 40, 165, $dark, 22);
        self::text($img, 'Сумма: '.$amount, 40, 220, $dark, 22);
        self::text($img, 'Доля платежа: '.$percent, 40, 275, $dark, 22);
        self::text($img, 'Дата: '.$date, 40, 325, $muted, 19);
        self::text($img, 'Демонстрационный чек', 40, 385, $muted, 16);
        imagerectangle($img, 0, 0, $w - 1, $h - 1, imagecolorallocate($img, 226, 232, 240));

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

    private static function text(\GdImage $img, string $text, int $x, int $baseline, int $color, int $size): void
    {
        if (is_file(self::FONT)) {
            imagettftext($img, $size, 0, $x, $baseline, $color, self::FONT, $text);

            return;
        }

        imagestring($img, 5, $x, $baseline - 16, $text, $color);
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
