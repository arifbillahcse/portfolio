<?php

namespace Tests\Feature;

use App\Jobs\ImportWordPressItem;
use App\Models\Post;
use App\Models\WordPressImport;
use App\Support\WordPressImporter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class SeoMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function source(): string
    {
        $disk = Storage::fake('seo-wxr');
        $disk->put('source.xml', '<?xml version="1.0" encoding="UTF-8"?><rss xmlns:wp="http://wordpress.org/export/1.2/" xmlns:content="http://purl.org/rss/1.0/modules/content/"><channel>
        <item><title>Existing article</title><link>https://old.arifbillah.com/?p=965</link><wp:post_id>965</wp:post_id><wp:post_type>post</wp:post_type><wp:post_name>existing</wp:post_name><wp:status>publish</wp:status><content:encoded><![CDATA[<p>Existing.</p>]]></content:encoded></item>
        <item><title>S3 article</title><link>https://old.arifbillah.com/?p=1012</link><wp:post_id>1012</wp:post_id><wp:post_type>post</wp:post_type><wp:post_name>s3-media</wp:post_name><wp:status>publish</wp:status><content:encoded><![CDATA[<p>A media guide.</p>]]></content:encoded>
        <wp:postmeta><wp:meta_key>rank_math_title</wp:meta_key><wp:meta_value><![CDATA[Reviewed S3 guide]]></wp:meta_value></wp:postmeta>
        <wp:postmeta><wp:meta_key>rank_math_description</wp:meta_key><wp:meta_value><![CDATA[A useful S3 summary.]]></wp:meta_value></wp:postmeta>
        <wp:postmeta><wp:meta_key>rank_math_focus_keyword</wp:meta_key><wp:meta_value>WordPress S3</wp:meta_value></wp:postmeta></item></channel></rss>');

        return $disk->path('source.xml');
    }

    public function test_export_storage_failure_is_reported_without_claiming_a_saved_file(): void
    {
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->once()->andReturn(false);
        $disk->shouldReceive('put')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $this->artisan('portfolio:export-wordpress-redirects')
            ->expectsOutput('Could not write the private redirect export.')->assertFailed();

        $this->assertDatabaseCount('seo_redirects', 0);
    }

    public function test_targeted_recovery_queues_only_the_requested_post_and_imports_literal_seo_as_a_draft(): void
    {
        Queue::fake([ImportWordPressItem::class]);
        $existing = Post::factory()->published()->create(['wordpress_id' => 965, 'seo_title' => 'Keep my title']);
        $before = $existing->fresh()->getRawOriginal();
        $this->artisan('portfolio:import-wordpress', ['file' => $this->source(), '--only-id' => '1012'])->assertSuccessful();
        $import = WordPressImport::query()->sole();
        $this->assertSame(1, $import->total);
        Queue::assertPushed(ImportWordPressItem::class, 1);
        $item = $import->items()->sole();
        app(WordPressImporter::class)->process($item);
        $recovered = Post::query()->where('wordpress_id', 1012)->sole();
        $this->assertSame('Reviewed S3 guide', $recovered->seo_title);
        $this->assertSame('A useful S3 summary.', $recovered->seo_description);
        $this->assertSame('WordPress S3', $recovered->seo_focus_keyword);
        $this->assertFalse($recovered->is_published);
        $this->assertSame($before, $existing->fresh()->getRawOriginal());
        $this->get('/?p=1012')->assertNotFound();
        $recovered->update(['is_published' => true]);
        $this->get('/?p=1012')->assertStatus(301)->assertRedirect(route('blog.show', $recovered->slug));
    }

    public function test_targeted_validation_and_missing_id_failures_do_not_queue_or_write_records(): void
    {
        Queue::fake([ImportWordPressItem::class]);
        $source = $this->source();
        $this->artisan('portfolio:import-wordpress', ['file' => $source, '--only-id' => '1012', '--validate-only' => true])->assertSuccessful();
        $this->artisan('portfolio:import-wordpress', ['file' => $source, '--only-id' => '9999'])->assertFailed();
        $this->artisan('portfolio:import-wordpress', ['file' => $source, '--only-id' => '-1'])->assertFailed();
        $this->assertDatabaseCount('word_press_imports', 0);
        $this->assertDatabaseCount('posts', 0);
        Queue::assertNotPushed(ImportWordPressItem::class);
    }

    public function test_targeted_reimport_does_not_overwrite_an_existing_post_or_its_seo(): void
    {
        Queue::fake([ImportWordPressItem::class]);
        $post = Post::factory()->published()->create(['wordpress_id' => 1012, 'seo_title' => 'Edited SEO title']);
        $before = $post->fresh()->getRawOriginal();
        $this->artisan('portfolio:import-wordpress', ['file' => $this->source(), '--only-id' => '1012'])->assertSuccessful();
        $item = WordPressImport::query()->sole()->items()->sole();
        app(WordPressImporter::class)->process($item);
        $this->assertSame('skipped', $item->fresh()->status);
        $this->assertSame($before, $post->fresh()->getRawOriginal());
    }

    public function test_old_host_export_contains_only_published_posts_and_does_not_install_any_rules(): void
    {
        Storage::fake('local');
        config(['app.url' => 'https://portfolio.company', 'wordpress.source_host' => 'old.portfolio.company']);
        Post::factory()->published()->create(['wordpress_id' => 965, 'slug' => 'existing']);
        Post::factory()->create(['wordpress_id' => 1012, 'slug' => 'private']);
        $this->artisan('portfolio:export-wordpress-redirects', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->artisan('portfolio:export-wordpress-redirects')->assertSuccessful();
        $files = Storage::disk('local')->allFiles('seo');
        $this->assertCount(1, $files);
        $rules = Storage::disk('local')->get($files[0]);
        $this->assertStringContainsString('^p=965$', $rules);
        $this->assertStringContainsString('https://portfolio.company/blog/existing', $rules);
        $this->assertStringContainsString('^page_id=3730$', $rules);
        $this->assertStringNotContainsString('p=1012', $rules);
        $this->assertStringNotContainsString('/private', $rules);
        $this->assertDatabaseCount('seo_redirects', 0);
    }

    public function test_migration_audit_distinguishes_published_drafts_and_missing_records_without_changes(): void
    {
        Post::factory()->published()->create(['wordpress_id' => 965, 'slug' => 'existing']);
        Post::factory()->create(['wordpress_id' => 1012, 'slug' => 'draft-s3']);
        $this->artisan('portfolio:seo-migration-audit')->expectsOutput('Published 1; drafts 1; scheduled 0; missing 34.')->assertSuccessful();
        $this->assertDatabaseCount('posts', 2);
        $this->assertDatabaseCount('word_press_imports', 0);
    }

    public function test_wordpress_template_variables_are_not_imported_as_literal_search_titles(): void
    {
        Queue::fake([ImportWordPressItem::class]);
        $source = $this->source();
        $bytes = file_get_contents($source);
        file_put_contents($source, str_replace('Reviewed S3 guide', '%title% %sitename%', $bytes));
        $this->artisan('portfolio:import-wordpress', ['file' => $source, '--only-id' => '1012'])->assertSuccessful();
        $item = WordPressImport::query()->sole()->items()->sole();
        app(WordPressImporter::class)->process($item);
        $this->assertNull(Post::query()->where('wordpress_id', 1012)->sole()->seo_title);
    }
}
