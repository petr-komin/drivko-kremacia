<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\OrderPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Ukládání fotek k zakázkám a jejich zmenšené náhledy.
 *
 * Fotky leží na privátním disku (storage/app/private) – jde o podklady
 * k zemřelým zvířatům, ven je vydává jen aplikace přihlášeným uživatelům.
 * Originál z telefonu se nikdy nemění (dílna ho potřebuje v plném rozlišení),
 * na web se posílají jen varianty:
 *   thumb  480 px, WebP – mřížky a seznamy
 *   large 1600 px, WebP – prohlížení na mobilu
 *
 * Varianty leží vedle originálu: orders/7/abc.jpg → orders/7/variants/abc-thumb.webp.
 *
 * Náhledy dělá vipsthumbnail (v Docker obrazu) – zvládne i obří fotky a HEIC z iPhonu,
 * protože obrázek zpracovává po částech. Bez něj (např. testy mimo Docker) se použije GD:
 * to načte celý obrázek do paměti, takže má limit MAX_PIXELS a HEIC neumí – taková
 * fotka pak náhled nemá a nabízí se ke stažení.
 */
final class Photos
{
    public const DISK = 'local';

    /**
     * Přípustné přípony nahraných fotek – sdílené validací i formuláři.
     * SVG (místo fotky tlapky třeba nakreslený obrázek): náhled vyrenderuje vips, originál jde jen ke stažení
     * – v prohlížeči by mohl spustit skript.
     */
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'svg'];

    /** Max. velikost jedné fotky v kB (musí sedět s upload_max_filesize v Dockerfile). */
    public const MAX_KB = 50 * 1024;

    /** Max. počet fotek v jednom odeslání. */
    public const MAX_FILES = 10;

    /** varianta => [nejdelší strana v px, kvalita WebP] */
    public const VARIANTS = [
        'thumb' => [480, 78],
        'large' => [1600, 82],
    ];

    /** Nad tímto počtem pixelů GD náhled nedělá (potřebovalo by stovky MB paměti); vips limit nemá. */
    private const MAX_PIXELS = 50_000_000;

    /** Validační pravidla pro pole fotek. */
    public static function rules(bool $required = false): array
    {
        return [
            'photos'   => [$required ? 'required' : 'nullable', 'array', 'max:' . self::MAX_FILES],
            'photos.*' => ['file', 'mimes:' . implode(',', self::EXTENSIONS), 'max:' . self::MAX_KB],
        ];
    }

    /**
     * Uloží nahrané soubory k zakázce.
     *
     * @param  UploadedFile[]  $files
     * @return OrderPhoto[]
     */
    public static function store(Order $order, array $files, string $kind, ?OrderMessage $message = null): array
    {
        $stored = [];

        foreach ($files as $file) {
            $ext  = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');
            $path = $file->storeAs('orders/' . $order->id, Str::uuid() . '.' . $ext, self::DISK);

            $stored[] = $order->photos()->create([
                'order_message_id' => $message?->id,
                'user_id'          => Auth::id(),
                'kind'             => $kind,
                'path'             => $path,
                'original_name'    => Str::limit($file->getClientOriginalName(), 200, ''),
                'mime'             => $file->getMimeType() ?: 'application/octet-stream',
                'size'             => $file->getSize(),
                'has_preview'      => self::generate($path),
            ]);
        }

        return $stored;
    }

    public static function variantPath(string $path, string $variant): string
    {
        $info = pathinfo($path);

        return $info['dirname'] . '/variants/' . $info['filename'] . '-' . $variant . '.webp';
    }

    public static function delete(string $path): void
    {
        Storage::disk(self::DISK)->delete([
            $path,
            ...array_map(fn ($v) => self::variantPath($path, $v), array_keys(self::VARIANTS)),
        ]);
    }

    /**
     * Vytvoří náhledy. Chyba se jen zaloguje – fotka zůstane nahraná
     * a nabídne se ke stažení.
     */
    public static function generate(string $path): bool
    {
        $disk = Storage::disk(self::DISK);
        $disk->makeDirectory(dirname(self::variantPath($path, 'thumb')));

        return self::vips() ? self::generateWithVips($path) : self::generateWithGd($path);
    }

    private static function vips(): ?string
    {
        static $binary = false;

        return $binary === false ? $binary = (new ExecutableFinder)->find('vipsthumbnail') : $binary;
    }

    /** vipsthumbnail sám otočí podle EXIF; „>“ = jen zmenšovat, nikdy nezvětšit (vektor SVG se vykreslí rovnou v cílové velikosti). */
    private static function generateWithVips(string $path): bool
    {
        $disk   = Storage::disk(self::DISK);
        $vector = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'svg';

        foreach (self::VARIANTS as $variant => [$maxSide, $quality]) {
            $result = Process::timeout(120)->run([
                self::vips(), $disk->path($path),
                '--size', "{$maxSide}x{$maxSide}" . ($vector ? '' : '>'),
                '-o', $disk->path(self::variantPath($path, $variant)) . "[Q={$quality},keep=none]",
            ]);

            if ($result->failed()) {
                Log::error('Photos (vips): ' . trim($result->errorOutput()), ['path' => $path]);

                return false;
            }
        }

        return true;
    }

    private static function generateWithGd(string $path): bool
    {
        $disk   = Storage::disk(self::DISK);
        $source = $disk->path($path);

        $size = @getimagesize($source);
        if (! $size || $size[0] * $size[1] > self::MAX_PIXELS) {
            return false;
        }

        $previousLimit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        try {
            $image = match ($size[2]) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
                IMAGETYPE_PNG  => @imagecreatefrompng($source),
                IMAGETYPE_WEBP => @imagecreatefromwebp($source),
                default        => false,
            };

            if (! $image) {
                return false;
            }

            $image = self::applyExifOrientation($image, $source, $size[2]);

            foreach (self::VARIANTS as $variant => [$maxSide, $quality]) {
                $resized = self::resize($image, $maxSide);
                $ok = imagewebp($resized, $disk->path(self::variantPath($path, $variant)), $quality);

                if ($resized !== $image) {
                    imagedestroy($resized);
                }
                if (! $ok) {
                    throw new \RuntimeException("Uložení varianty {$variant} selhalo");
                }
            }

            imagedestroy($image);

            return true;
        } catch (\Throwable $e) {
            Log::error('Photos: ' . $e->getMessage(), ['path' => $path]);

            return false;
        } finally {
            ini_set('memory_limit', $previousLimit);
        }
    }

    /** Fotky z telefonu nesou otočení jen v EXIF – po přeuložení by byly na boku. */
    private static function applyExifOrientation(\GdImage $image, string $source, int $type): \GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($source)['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3, 4    => imagerotate($image, 180, 0),
            5, 6    => imagerotate($image, -90, 0),
            7, 8    => imagerotate($image, 90, 0),
            default => $image,
        };

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($rotated, IMG_FLIP_HORIZONTAL);
        }

        if ($rotated !== $image) {
            imagedestroy($image);
        }

        return $rotated;
    }

    /** Zmenší tak, aby delší strana měla nejvýš $maxSide (nikdy nezvětšuje). */
    private static function resize(\GdImage $image, int $maxSide): \GdImage
    {
        $w     = imagesx($image);
        $h     = imagesy($image);
        $scale = min(1, $maxSide / max($w, $h));

        if ($scale === 1) {
            return $image;
        }

        $nw  = max(1, (int) round($w * $scale));
        $nh  = max(1, (int) round($h * $scale));
        $out = imagecreatetruecolor($nw, $nh);

        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagecopyresampled($out, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $out;
    }
}
