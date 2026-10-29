<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Support\MediaSeo;
use App\Support\ResponsiveImages;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ResponsiveImagesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function image(string $path = 'posts/screen.png', int $width = 800, int $height = 600): string
    {
        Storage::fake('public');
        Storage::fake('local');
        UploadedFile::fake()->image(basename($path), $width, $height)->storeAs(dirname($path), basename($path), 'public');

        return Storage::disk('public')->path($path);
    }

    public function test_generated_webp_variants_are_smaller_never_upscaled_and_keep_the_original_unchanged(): void
    {
        $path = $this->image();
        $before = hash_file('sha256', $path);
        $images = app(ResponsiveImages::class);
        $entry = $images->build('posts/screen.png');
        $this->assertNotEmpty($entry['variants']);
        foreach ($entry['variants'] as $variant) {
            $this->assertLessThan(filesize($path), $variant['bytes']);
            $this->assertLessThanOrEqual(800, $variant['width']);
            $this->assertSame(IMAGETYPE_WEBP, getimagesize(Storage::disk('public')->path($variant['path']))[2]);
        }
        $images->save(['version' => 1, 'images' => ['posts/screen.png' => $entry]]);
        $this->assertNotEmpty($images->candidates('posts/screen.png'));
        $this->assertSame($before, hash_file('sha256', $path));
    }

    public function test_inline_content_uses_responsive_copies_while_preserving_alt_and_original_markdown(): void
    {
        $this->image();
        $images = app(ResponsiveImages::class);
        $images->save(['version' => 1, 'images' => ['posts/screen.png' => $images->build('posts/screen.png')]]);
        $post = Post::factory()->published()->create(['body' => '![Linux dashboard](/storage/posts/screen.png)']);
        $this->get('/blog/'.$post->slug)->assertOk()->assertSee('type="image/webp"', false)
            ->assertSee('srcset=', false)->assertSee('alt="Linux dashboard"', false)
            ->assertSee('src="/storage/posts/screen.png"', false);
        $this->assertSame('![Linux dashboard](/storage/posts/screen.png)', $post->fresh()->body);
    }

    public function test_changed_sources_missing_variants_or_invalid_manifests_fall_back_to_originals(): void
    {
        $path = $this->image();
        $images = app(ResponsiveImages::class);
        $entry = $images->build('posts/screen.png');
        $images->save(['version' => 1, 'images' => ['posts/screen.png' => $entry]]);
        foreach ($entry['variants'] as $variant) {
            Storage::disk('public')->delete($variant['path']);
        }
        $this->assertSame([], $images->candidates('posts/screen.png'));
        touch($path, filemtime($path) + 5);
        clearstatcache(true, $path);
        $this->assertSame([], $images->candidates('posts/screen.png'));
        $images->save(['version' => 1, 'images' => ['posts/screen.png' => ['mtime' => filemtime($path), 'bytes' => filesize($path), 'variants' => [['path' => '../private.webp', 'width' => 640]]]]]);
        $this->assertSame([], $images->candidates('posts/screen.png'));
        $html = MediaSeo::body('![Screen](/storage/posts/screen.png)');
        $this->assertStringContainsString('src="/storage/posts/screen.png"', $html);
        $this->assertStringNotContainsString('<picture', $html);
    }

    public static function unsafePaths(): array
    {
        return [['../private.png'], ['posts/../../private.png'], ['https://external.example/image.png'], ['/etc/passwd'], ['profile/script.php']];
    }

    #[DataProvider('unsafePaths')]
    public function test_only_existing_allowlisted_local_images_can_be_processed(string $path): void
    {
        $this->assertNull(app(ResponsiveImages::class)->source($path));
    }

    public function test_animated_png_and_exif_rotated_jpeg_are_retained_without_flattening_or_rotation(): void
    {
        $path = $this->image();
        $bytes = file_get_contents($path);
        $data = pack('NN', 2, 0);
        $chunk = 'acTL'.$data;
        file_put_contents($path, substr($bytes, 0, 33).pack('N', strlen($data)).$chunk.pack('H*', hash('crc32b', $chunk)).substr($bytes, 33));
        clearstatcache(true, $path);
        $images = app(ResponsiveImages::class);
        $animated = $images->build('posts/screen.png');
        $this->assertSame([], $animated['variants']);
        $this->assertStringContainsString('Animated', $animated['skipped']);
        UploadedFile::fake()->image('photo.jpg', 800, 600)->storeAs('posts', 'photo.jpg', 'public');
        $photo = Storage::disk('public')->path('posts/photo.jpg');
        $jpeg = file_get_contents($photo);
        $exif = "Exif\0\0II\x2a\0\x08\0\0\0".pack('v', 1).pack('vvVv', 0x112, 3, 1, 6)."\0\0".pack('V', 0);
        file_put_contents($photo, substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2));
        clearstatcache(true, $photo);
        $rotated = $images->build('posts/photo.jpg');
        $this->assertSame([], $rotated['variants']);
        $this->assertStringContainsString('orientation', $rotated['skipped']);
    }

    public function test_processing_budget_rejects_huge_dimensions_before_decoding(): void
    {
        $path = $this->image();
        $bytes = file_get_contents($path);
        file_put_contents($path, substr_replace($bytes, pack('NN', 10000, 10000), 16, 8));
        clearstatcache(true, $path);
        $entry = app(ResponsiveImages::class)->build('posts/screen.png');
        $this->assertSame([], $entry['variants']);
        $this->assertStringContainsString('budget', $entry['skipped']);
    }

    public function test_command_processes_pending_sources_in_batches_and_can_rebuild_missing_copies(): void
    {
        $this->image();
        UploadedFile::fake()->image('other.png', 800, 600)->storeAs('posts', 'other.png', 'public');
        $arguments = ['--path' => ['posts/screen.png', 'posts/other.png'], '--limit' => 1];
        $this->artisan('portfolio:optimize-media', $arguments)->expectsOutputToContain('pending 1')->assertSuccessful();
        $this->artisan('portfolio:optimize-media', $arguments)->expectsOutputToContain('pending 0')->assertSuccessful();
        $this->artisan('portfolio:optimize-media', ['--path' => ['posts/screen.png'], '--force' => true])->assertSuccessful();
        $this->artisan('portfolio:optimize-media', ['--limit' => 0])->assertFailed();
    }

    public function test_existing_media_lock_prevents_a_second_batch(): void
    {
        $this->image();
        $lock = Cache::lock('portfolio-optimize-media', 3600);
        $this->assertTrue($lock->get());
        try {
            $this->artisan('portfolio:optimize-media', ['--path' => ['posts/screen.png']])->assertFailed();
        } finally {
            $lock->release();
        }
    }
}
