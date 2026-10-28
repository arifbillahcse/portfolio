<?php

namespace Tests\Feature;

use App\Filament\Pages\WordPressImportPage;
use App\Jobs\ImportWordPressItem;
use App\Models\Post;
use App\Models\User;
use App\Models\WordPressImport;
use App\Models\WordPressImportItem;
use App\Support\PublicContent;
use App\Support\WordPressImporter;
use App\Support\WordPressMarkdown;
use App\Support\WordPressMedia;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class WordPressImportTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function export(string $html = '<h2>Hello</h2><p>My article.</p>', bool $image = false): UploadedFile
    {
        $attachment = $image ? '<item><wp:post_type>attachment</wp:post_type><wp:post_id>90</wp:post_id><wp:attachment_url>https://arifbillah.com/wp-content/uploads/2024/photo.png</wp:attachment_url></item>' : '';
        $thumbnail = $image ? '<wp:postmeta><wp:meta_key>_thumbnail_id</wp:meta_key><wp:meta_value>90</wp:meta_value></wp:postmeta>' : '';

        return UploadedFile::fake()->createWithContent('wordpress.xml', '<?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0" xmlns:wp="http://wordpress.org/export/1.2/" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/">
            <channel><item><title>Cloud computing guide</title><link>https://arifbillah.com/2024/cloud-guide/</link>
            <wp:post_id>42</wp:post_id><wp:post_name>cloud-guide</wp:post_name><wp:post_type>post</wp:post_type><wp:status>publish</wp:status>
            <wp:post_date_gmt>2024-05-06 09:30:00</wp:post_date_gmt><category domain="category" nicename="cloud">Cloud</category>
            <content:encoded><![CDATA['.$html.']]></content:encoded><excerpt:encoded><![CDATA[An introduction to cloud computing.]]></excerpt:encoded>'.$thumbnail.'</item>'.$attachment.'</channel></rss>');
    }

    private function start(UploadedFile $file): WordPressImport
    {
        return app(WordPressImporter::class)->start($file->getRealPath(), $file->getClientOriginalName());
    }

    private function finish(WordPressImport $import): WordPressImportItem
    {
        $item = $import->items()->firstOrFail();
        for ($step = 0; $step < 20 && ! in_array($item->status, ['imported', 'warnings', 'skipped'], true); $step++) {
            (new ImportWordPressItem($item->id))->handle(app(WordPressImporter::class));
            $item->refresh();
        }

        return $item;
    }

    public function test_admin_can_upload_xml_and_view_a_private_import_report(): void
    {
        Queue::fake([ImportWordPressItem::class]);
        $this->get('/admin/wordpress-import')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get('/admin/wordpress-import')->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');
        $this->get('/admin/wordpress-import')->assertOk();
        Livewire::test(WordPressImportPage::class)->set('xml', $this->export())->call('startImport')
            ->assertHasNoErrors()->assertSee('Cloud computing guide')->assertSee('Queued');
        $this->assertDatabaseHas('word_press_imports', ['total' => 1]);
        Queue::assertPushed(ImportWordPressItem::class, fn (ImportWordPressItem $job): bool => $job->connection === 'database' && $job->queue === 'default');
    }

    public function test_posts_import_as_drafts_and_existing_edits_are_preserved_on_reimport(): void
    {
        Queue::fake([ImportWordPressItem::class]);
        $item = $this->finish($this->start($this->export()));
        $post = Post::query()->findOrFail($item->post_id);
        $this->assertSame('imported', $item->status);
        $this->assertFalse($post->is_published);
        $this->assertSame('Cloud', $post->category->name);
        $this->assertSame('2024-05-06 09:30:00', $post->published_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('An introduction to cloud computing.', $post->excerpt);
        $this->assertStringContainsString('## Hello', $post->body);
        $post->update(['title' => 'My edited title', 'body' => 'My edited content']);
        $repeated = $this->finish($this->start($this->export()));
        $this->assertSame('skipped', $repeated->status);
        $this->assertSame(1, Post::query()->count());
        $this->assertSame('My edited content', $post->fresh()->body);
        Queue::assertPushed(ImportWordPressItem::class);
    }

    public function test_inline_and_featured_images_are_downloaded_once_and_saved_locally(): void
    {
        Queue::fake([ImportWordPressItem::class]);
        Storage::fake('public');
        Http::preventStrayRequests();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jL1sAAAAASUVORK5CYII=');
        Http::fake(['https://old.arifbillah.com/wp-content/uploads/2024/photo.png' => Http::response($png, 200)]);
        $item = $this->finish($this->start($this->export('<p>বাংলা লেখা</p><img src="https://arifbillah.com/wp-content/uploads/2024/photo.png" alt="Cloud">', true)));
        $post = Post::query()->findOrFail($item->post_id);
        Storage::disk('public')->assertExists($post->cover_image);
        $this->assertSame('public', Storage::disk('public')->getVisibility('posts'));
        $this->assertSame('public', Storage::disk('public')->getVisibility('posts/wordpress'));
        $this->assertSame('public', Storage::disk('public')->getVisibility($post->cover_image));
        $this->assertStringContainsString('/storage/posts/wordpress/', $post->body);
        $this->assertStringContainsString('বাংলা লেখা', $post->body);
        $this->assertStringNotContainsString('https://arifbillah.com/wp-content/uploads/', $post->body);
        Http::assertSentCount(1);
        Queue::assertPushed(ImportWordPressItem::class);
    }

    public function test_failed_images_can_be_retried_without_overwriting_edited_post_content(): void
    {
        Queue::fake([ImportWordPressItem::class]);
        Storage::fake('public');
        Http::preventStrayRequests();
        $url = 'https://old.arifbillah.com/wp-content/uploads/2024/photo.png';
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jL1sAAAAASUVORK5CYII=');
        Http::fake([$url => Http::sequence()->push('unavailable', 503)->push($png, 200)]);
        $import = $this->start($this->export('<p>Original</p><img src="https://arifbillah.com/wp-content/uploads/2024/photo.png">', true));
        $item = $this->finish($import);
        $this->assertSame('warnings', $item->status);
        $this->assertStringContainsString('HTTP 503', $item->error);
        $post = Post::query()->findOrFail($item->post_id);
        $post->update(['title' => 'Edited', 'body' => $post->body."\n\nMy additional paragraph.", 'is_published' => true]);
        app(WordPressImporter::class)->resume($import);
        $item = $this->finish($import);
        $this->assertSame('imported', $item->status);
        $this->assertSame('Edited', $post->fresh()->title);
        $this->assertTrue($post->fresh()->is_published);
        $this->assertStringContainsString('My additional paragraph.', $post->fresh()->body);
        $this->assertStringContainsString('/storage/posts/wordpress/', $post->fresh()->body);
        Http::assertSentCount(2);
        Queue::assertPushed(ImportWordPressItem::class);
    }

    #[DataProvider('unsafeXml')]
    public function test_invalid_or_unsafe_xml_is_rejected_without_creating_imports(string $xml): void
    {
        Queue::fake([ImportWordPressItem::class]);
        try {
            $this->start(UploadedFile::fake()->createWithContent('bad.xml', $xml));
            $this->fail('Unsafe XML was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('xml', $exception->errors());
        }
        $this->assertDatabaseCount('word_press_imports', 0);
        Queue::assertNothingPushed();
    }

    public static function unsafeXml(): array
    {
        return [
            'malformed' => ['<rss><broken>'],
            'wrong format' => ['<rss><channel/></rss>'],
            'empty export' => ['<rss xmlns:wp="http://wordpress.org/export/1.2/"><channel/></rss>'],
            'external entity' => ['<!DOCTYPE rss [<!ENTITY secret SYSTEM "file:///etc/passwd">]><rss>&secret;</rss>'],
        ];
    }

    #[DataProvider('unsafeImages')]
    public function test_image_downloader_refuses_untrusted_urls_before_making_a_request(string $url): void
    {
        Http::preventStrayRequests();
        Http::fake();
        try {
            app(WordPressMedia::class)->download($url);
            $this->fail('Unsafe image URL was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('configured WordPress site', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public static function unsafeImages(): array
    {
        return [
            ['http://127.0.0.1/wp-content/uploads/a.png'],
            ['https://evil.test/wp-content/uploads/a.png'],
            ['https://old.arifbillah.com.evil.test/wp-content/uploads/a.png'],
            ['https://old.arifbillah.com:8080/wp-content/uploads/a.png'],
            ['https://user:secret@old.arifbillah.com/wp-content/uploads/a.png'],
            ['https://old.arifbillah.com/wp-content/uploads/%2e%2e/.env'],
            ['https://old.arifbillah.com/.env'],
            ['file:///etc/passwd'],
        ];
    }

    public function test_html_scripts_and_unsafe_links_are_not_rendered_after_conversion(): void
    {
        $markdown = app(WordPressMarkdown::class)->convert('<h2>Safe</h2><script>alert(1)</script><a href="javascript:alert(2)">Link</a><img src="data:text/html,test" onerror="alert(3)"><p>[unsafe](javascript:alert(4))</p>', []);
        $html = PublicContent::markdown($markdown);
        $this->assertStringContainsString('<h2>Safe</h2>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
        $this->assertStringNotContainsString('onerror=', $html);
    }

    public function test_existing_slug_is_preserved_and_legacy_urls_redirect_only_after_publication(): void
    {
        Queue::fake([ImportWordPressItem::class]);
        $existing = Post::factory()->create(['slug' => 'cloud-guide', 'title' => 'Unrelated article']);
        $item = $this->finish($this->start($this->export()));
        $post = Post::query()->findOrFail($item->post_id);
        $this->assertSame('cloud-guide-wp-42', $post->slug);
        $this->assertSame('Unrelated article', $existing->fresh()->title);
        $this->get('/2024/cloud-guide/')->assertNotFound();
        $post->update(['is_published' => true]);
        $this->get('/2024/cloud-guide/')->assertStatus(301)->assertRedirect(route('blog.show', ['slug' => 'cloud-guide-wp-42']));
        $this->get('/?p=42')->assertStatus(301)->assertRedirect(route('blog.show', ['slug' => 'cloud-guide-wp-42']));
        $this->get('/not-a-real-post')->assertNotFound();
        Queue::assertPushed(ImportWordPressItem::class);
    }

    public function test_image_responses_must_be_valid_raster_images_and_redirects_are_not_followed(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Http::fake([
            'https://old.arifbillah.com/wp-content/uploads/redirect.png' => Http::response('', 302, ['Location' => 'http://127.0.0.1/private']),
            'https://old.arifbillah.com/wp-content/uploads/fake.png' => Http::response('<svg onload="alert(1)"></svg>', 200),
        ]);
        foreach (['redirect.png', 'fake.png'] as $file) {
            try {
                app(WordPressMedia::class)->download('https://old.arifbillah.com/wp-content/uploads/'.$file);
                $this->fail('Invalid image accepted.');
            } catch (RuntimeException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
        $this->assertSame([], Storage::disk('public')->allFiles());
        Http::assertSentCount(2);
    }

    public function test_each_job_downloads_at_most_one_image_before_requeuing(): void
    {
        Queue::fake([ImportWordPressItem::class]);
        Storage::fake('public');
        Http::preventStrayRequests();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jL1sAAAAASUVORK5CYII=');
        Http::fake([
            'https://old.arifbillah.com/wp-content/uploads/first.png' => Http::response($png),
            'https://old.arifbillah.com/wp-content/uploads/second.png' => Http::response($png),
        ]);
        $import = $this->start($this->export('<img src="/wp-content/uploads/first.png"><img src="/wp-content/uploads/second.png">'));
        $item = $import->items()->firstOrFail();
        (new ImportWordPressItem($item->id))->handle(app(WordPressImporter::class));
        Http::assertSentCount(1);
        $this->assertDatabaseCount('posts', 0);
        $this->assertSame('processing', $item->fresh()->status);
        Queue::assertPushed(ImportWordPressItem::class, 2);
        $this->finish($import);
        $this->assertDatabaseCount('posts', 1);
        Http::assertSentCount(2);
    }

    public function test_invalid_xml_upload_shows_an_error_without_starting_an_import(): void
    {
        Queue::fake([ImportWordPressItem::class]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');
        Livewire::test(WordPressImportPage::class)
            ->set('xml', UploadedFile::fake()->createWithContent('invalid.xml', '<not-wordpress/>'))
            ->call('startImport')->assertHasErrors(['xml']);
        $this->assertDatabaseCount('word_press_imports', 0);
        Queue::assertNothingPushed();
    }

    public function test_admin_can_resume_a_failed_import_from_the_report(): void
    {
        Queue::fake([ImportWordPressItem::class]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');
        $import = $this->start($this->export());
        $item = $import->items()->firstOrFail();
        (new ImportWordPressItem($item->id))->failed(null);
        $this->assertSame('failed', $item->fresh()->status);
        Livewire::test(WordPressImportPage::class)->call('resumeImport')->assertSee('Queued');
        $this->assertSame('queued', $item->fresh()->status);
        $this->assertSame('imported', $this->finish($import)->status);
        Queue::assertPushed(ImportWordPressItem::class, 2);
    }

    public function test_oversized_images_are_reported_without_saving_a_file(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Http::fake(['https://old.arifbillah.com/wp-content/uploads/large.png' => Http::response(str_repeat('x', 8 * 1024 * 1024 + 1))]);
        try {
            app(WordPressMedia::class)->download('https://old.arifbillah.com/wp-content/uploads/large.png');
            $this->fail('Oversized image accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('8 MB', $exception->getMessage());
        }
        $this->assertSame([], Storage::disk('public')->allFiles());
        Http::assertSentCount(1);
    }
}
