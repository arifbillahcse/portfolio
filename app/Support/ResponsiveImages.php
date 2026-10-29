<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;

class ResponsiveImages
{
    public const MANIFEST = 'seo/responsive-images.json';

    /** @var array<string, mixed>|null */
    private ?array $manifest = null;

    /** @var array<string, string> */
    private array $hashes = [];

    /** @return array{absolute: string, root: string, asset: bool}|null */
    public function source(string $path): ?array
    {
        $asset = (bool) preg_match('~\Aassets/images/[a-zA-Z0-9_-]+\.(png|jpe?g|webp)\z~i', $path);
        if (! $asset && (! PublicContent::image($path) || config('filesystems.disks.public.driver') !== 'local')) {
            return null;
        }
        $root = realpath($asset ? public_path() : Storage::disk('public')->path(''));
        $absolute = realpath($asset ? public_path($path) : Storage::disk('public')->path($path));
        if (! $root || ! $absolute || ! is_file($absolute) || ! str_starts_with($absolute, $root.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return compact('absolute', 'root', 'asset');
    }

    /** @return list<array{url: string, width: int}> */
    public function candidates(string $path): array
    {
        $source = $this->source($path);
        if (! $source) {
            return [];
        }
        $entry = $this->manifest()['images'][$path] ?? null;
        if (! $this->fresh($entry, $source['absolute'])) {
            $entry = null;
            if ($path === 'assets/images/arif-formal.png') {
                $bundle = resource_path('catalogue/responsive-portrait.json');
                $bundled = is_file($bundle) ? $this->decode(File::get($bundle)) : [];
                $candidate = $bundled['images'][$path] ?? null;
                $hash = $this->hashes[$path] ??= hash_file('sha256', $source['absolute']);
                if (is_array($candidate) && ($candidate['sha256'] ?? null) === $hash) {
                    $entry = $candidate;
                }
            }
        }
        $variants = [];
        foreach (is_array($entry['variants'] ?? null) ? $entry['variants'] : [] as $variant) {
            $relative = $variant['path'] ?? '';
            if (! is_string($relative) || ! preg_match('~\A(?:assets/images|posts|projects|profile)/responsive/[a-f0-9]{16}-[a-f0-9]{12}-[0-9]{2,4}\.webp\z~', $relative)
                || ! is_int($variant['width'] ?? null) || $variant['width'] < 1
                || ($source['asset'] !== str_starts_with($relative, 'assets/'))) {
                continue;
            }
            $absolute = $source['root'].DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (is_file($absolute)) {
                $url = $source['asset'] ? PublicSeo::url('home').$relative : SeoMetadata::absoluteImage(Storage::disk('public')->url($relative));
                $variants[] = ['url' => $url, 'width' => $variant['width']];
            }
        }

        return $variants;
    }

    public function pathFromUrl(string $url): ?string
    {
        $parts = parse_url($url);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['host']) && strtolower($parts['host']) !== strtolower(parse_url(config('app.url'), PHP_URL_HOST) ?? ''))) {
            return null;
        }
        $path = rawurldecode($parts['path'] ?? '');
        $base = rtrim(parse_url(config('app.url'), PHP_URL_PATH) ?? '', '/');
        if ($base && str_starts_with($path, $base.'/')) {
            $path = substr($path, strlen($base));
        }
        $relative = str_starts_with($path, '/storage/') ? substr($path, 9) : ltrim($path, '/');

        return $this->source($relative) ? $relative : null;
    }

    /** @return array<string, mixed> */
    public function manifest(): array
    {
        if ($this->manifest === null) {
            $disk = Storage::disk('local');
            $bytes = $disk->exists(self::MANIFEST) ? $disk->get(self::MANIFEST) : null;
            $this->manifest = is_string($bytes) ? $this->decode($bytes) : [];
        }

        return $this->manifest;
    }

    /** @return array<string, mixed> */
    private function decode(string $bytes): array
    {
        try {
            $data = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);

            return is_array($data) && ($data['version'] ?? null) === 1 && is_array($data['images'] ?? null) ? $data : [];
        } catch (JsonException) {
            return [];
        }
    }

    public function fresh(mixed $entry, string $absolute): bool
    {
        return is_array($entry) && ($entry['mtime'] ?? null) === filemtime($absolute)
            && ($entry['bytes'] ?? null) === filesize($absolute);
    }

    /** @return array<string, mixed> */
    public function build(string $path): array
    {
        $source = $this->source($path);
        if (! $source) {
            throw new RuntimeException('Choose an existing public image inside the supported directories.');
        }
        $absolute = $source['absolute'];
        $entry = ['mtime' => filemtime($absolute), 'bytes' => filesize($absolute), 'sha256' => hash_file('sha256', $absolute), 'variants' => []];
        $size = @getimagesize($absolute);
        if (! $size || ! in_array($size[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) {
            return [...$entry, 'skipped' => 'Unsupported or invalid image; original retained.'];
        }
        if (! function_exists('imagewebp') || ! (imagetypes() & IMG_WEBP)) {
            return [...$entry, 'skipped' => 'GD WebP support is unavailable; original retained.'];
        }
        if ($size[0] * $size[1] > 8000000 || $entry['bytes'] > 20000000 || ! $this->hasMemory($size[0] * $size[1])) {
            return [...$entry, 'skipped' => 'Image exceeds the safe processing budget; original retained.'];
        }
        $header = file_get_contents($absolute, false, null, 0, 65536);
        if (($size[2] === IMAGETYPE_WEBP && substr($header, 12, 4) === 'VP8X' && (ord($header[20] ?? chr(0)) & 2))
            || ($size[2] === IMAGETYPE_PNG && $this->animatedPng($absolute))) {
            return [...$entry, 'skipped' => 'Animated image retained without conversion.'];
        }
        if ($size[2] === IMAGETYPE_JPEG && str_contains($header, "Exif\0\0")) {
            $exif = function_exists('exif_read_data') ? @exif_read_data($absolute) : false;
            if (! $exif || ($exif['Orientation'] ?? 1) !== 1) {
                return [...$entry, 'skipped' => 'EXIF orientation requires review; original retained.'];
            }
        }
        $widths = array_values(array_unique(array_filter([320, 640, 960, 1280, min(1280, $size[0])], fn (int $width): bool => $width >= 320 && $width <= $size[0])));
        sort($widths);
        if (! $widths) {
            return [...$entry, 'skipped' => 'Small image already fits the responsive budget.'];
        }
        $image = match ($size[2]) {
            IMAGETYPE_PNG => @imagecreatefrompng($absolute),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absolute),
            default => @imagecreatefromwebp($absolute),
        };
        if (! $image) {
            return [...$entry, 'skipped' => 'Image decoder could not read the source.'];
        }
        $directory = $source['asset'] ? 'assets/images/responsive' : explode('/', $path)[0].'/responsive';
        $destination = $source['root'].DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $directory);
        File::ensureDirectoryExists($destination);
        try {
            foreach ($widths as $width) {
                $height = max(1, (int) round($size[1] * $width / $size[0]));
                $canvas = imagecreatetruecolor($width, $height);
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
                imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
                $temporary = tempnam($destination, '.media-');
                try {
                    if (! imagewebp($canvas, $temporary, 82)) {
                        throw new RuntimeException('WebP encoding failed.');
                    }
                    $bytes = filesize($temporary);
                    if ($bytes < $entry['bytes']) {
                        $name = substr(hash('sha256', $path), 0, 16).'-'.substr(hash_file('sha256', $temporary), 0, 12).'-'.$width.'.webp';
                        if (! rename($temporary, $destination.DIRECTORY_SEPARATOR.$name)) {
                            throw new RuntimeException('Could not publish the responsive image.');
                        }
                        chmod($destination.DIRECTORY_SEPARATOR.$name, 0644);
                        $entry['variants'][] = ['path' => $directory.'/'.$name, 'width' => $width, 'height' => $height, 'bytes' => $bytes];
                    }
                } finally {
                    imagedestroy($canvas);
                    if (is_file($temporary)) {
                        unlink($temporary);
                    }
                }
            }
        } finally {
            imagedestroy($image);
        }

        return $entry;
    }

    private function hasMemory(int $pixels): bool
    {
        $setting = ini_get('memory_limit');
        if ($setting === '-1') {
            return true;
        }
        $limit = (int) $setting;
        $limit *= match (strtolower(substr($setting, -1))) {
            'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1,
        };

        return memory_get_usage(true) + $pixels * 9 + 1280 * 1280 * 9 + 16777216 < $limit;
    }

    private function animatedPng(string $path): bool
    {
        $handle = fopen($path, 'rb');
        try {
            fseek($handle, 8);
            while (! feof($handle)) {
                $chunk = fread($handle, 8);
                if (strlen($chunk) !== 8) {
                    break;
                }
                $length = unpack('N', substr($chunk, 0, 4))[1];
                $type = substr($chunk, 4);
                if ($type === 'acTL') {
                    return true;
                }
                if (in_array($type, ['IDAT', 'IEND'], true) || $length > 20000000) {
                    break;
                }
                fseek($handle, $length + 4, SEEK_CUR);
            }
        } finally {
            fclose($handle);
        }

        return false;
    }

    /** @param array<string, mixed> $data */
    public function save(array $data): void
    {
        $path = Storage::disk('local')->path(self::MANIFEST);
        File::ensureDirectoryExists(dirname($path));
        File::replace($path, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 0600);
        $this->manifest = $data;
    }

    public function version(): string
    {
        $manifest = $this->manifest();

        return hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR));
    }
}
