<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SyncFrontendAssetsTest extends TestCase
{
    public function test_only_the_allowlisted_public_assets_are_published(): void
    {
        $disk = Storage::fake('frontend-allowlist');
        $this->app->usePublicPath($disk->path('public'));
        $disk->put('source/styles.css', 'stylesheet content');
        $disk->put('source/script.js', 'script content');
        $disk->put('source/arif-formal.png', 'portrait content');
        $disk->put('source/.env', 'private test configuration');

        $this->artisan('portfolio:assets', ['--source' => $disk->path('source')])->assertSuccessful();

        $disk->assertExists(['public/assets/css/styles.css', 'public/assets/js/script.js', 'public/assets/images/arif-formal.png']);
        $disk->assertMissing('public/assets/.env');
        $this->assertSame('stylesheet content', $disk->get('public/assets/css/styles.css'));
        $this->assertSame('script content', $disk->get('public/assets/js/script.js'));
        $this->assertSame('portrait content', $disk->get('public/assets/images/arif-formal.png'));
    }

    public function test_missing_source_asset_does_not_overwrite_existing_published_files(): void
    {
        $disk = Storage::fake('frontend-missing-source');
        $this->app->usePublicPath($disk->path('public'));
        $disk->put('source/styles.css', 'new stylesheet');
        $disk->put('public/assets/css/styles.css', 'existing stylesheet');

        $this->artisan('portfolio:assets', ['--source' => $disk->path('source')])
            ->expectsOutput('Missing source asset: script.js')
            ->assertFailed();

        $this->assertSame('existing stylesheet', $disk->get('public/assets/css/styles.css'));
        $disk->assertMissing('public/assets/js/script.js');
    }

    public function test_resync_updates_assets_after_a_frontend_edit(): void
    {
        $disk = Storage::fake('frontend-resync');
        $this->app->usePublicPath($disk->path('public'));
        $disk->put('source/styles.css', 'new stylesheet');
        $disk->put('source/script.js', 'new script');
        $disk->put('source/arif-formal.png', 'portrait');
        $disk->put('public/assets/css/styles.css', 'old stylesheet');

        $this->artisan('portfolio:assets', ['--source' => $disk->path('source')])->assertSuccessful();

        $this->assertSame('new stylesheet', $disk->get('public/assets/css/styles.css'));
    }
}
