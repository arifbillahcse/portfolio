<?php

namespace Tests\Feature;

use App\Filament\Resources\Projects\Pages\EditProject;
use App\Models\Category;
use App\Models\Project;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProjectCatalogueImportTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, mixed> */
    private function catalogue(): array
    {
        return json_decode(file_get_contents(resource_path('catalogue/cloud-portfolio.json')), true, 64, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $catalogue */
    private function fixture(array $catalogue): string
    {
        $disk = Storage::fake('catalogue-input');
        $disk->put('catalogue.json', json_encode($catalogue, JSON_THROW_ON_ERROR));

        return $disk->path('catalogue.json');
    }

    public function test_bundled_catalogue_imports_47_drafts_in_six_categories_with_only_matched_evidence(): void
    {
        Storage::fake('public');

        $this->artisan('portfolio:import-catalogue')->expectsOutput('Imported 47 draft projects; skipped 0 previously imported entries.')->assertSuccessful();

        $this->assertDatabaseCount('projects', 47);
        $this->assertDatabaseCount('categories', 6);
        $this->assertSame(0, Project::query()->where('is_published', true)->count());
        $this->assertSame(0, Project::query()->whereNotNull('published_at')->count());
        $this->assertSame(0, Project::query()->where('is_featured', true)->count());
        $this->assertSame(9, Project::query()->whereNotNull('video_url')->count());
        $this->assertCount(9, Storage::disk('public')->allFiles('projects/catalogue'));
        $migration = Project::query()->where('catalogue_key', 'arif-cloud-portfolio:9')->sole();
        $this->assertSame('WordPress Migration to Cloudways', $migration->title);
        $this->assertSame('https://www.youtube.com/watch?v=FU4Zd3GTgYs', $migration->video_url);
        $this->assertContains('Cloudways', $migration->tools);
        $this->assertStringNotContainsString('zero downtime', $migration->body);
        $this->assertSame(9, $migration->catalogue_metadata['source_project_number']);
        $this->assertSame('Arif_Cloud_Portfolio_Complete.docx', $migration->catalogue_metadata['source_file']);
        Storage::disk('public')->assertExists($migration->cover_image);
        $source = Project::query()->where('catalogue_key', 'arif-cloud-portfolio:1')->sole();
        $this->assertNull($source->video_url);
        $this->assertNull($source->cover_image);
        $this->assertSame('source_unavailable', $source->catalogue_metadata['status']);
        $this->assertSame('https://youtu.be/Y7bMa_o5TBM', $source->catalogue_metadata['original_url']);
        $this->get('/projects')->assertOk()->assertDontSeeText($migration->title);
        $this->get('/projects/'.$migration->slug)->assertNotFound();
        $this->get('/')->assertOk()->assertDontSeeText($migration->title);
        $this->get('/sitemaps/projects/1.xml')->assertNotFound();
    }

    public function test_scope_candidates_and_overlapping_entries_are_retained_without_using_candidate_links(): void
    {
        Storage::fake('public');
        $this->artisan('portfolio:import-catalogue')->assertSuccessful();

        $project = Project::query()->where('catalogue_key', 'arif-cloud-portfolio:25')->sole();
        $this->assertSame('WordPress Backup and Disaster Recovery', $project->title);
        $this->assertSame('candidate_scope_change', $project->catalogue_metadata['status']);
        $this->assertNull($project->video_url);
        $this->assertNull($project->cover_image);
        $this->assertSame('https://www.youtube.com/watch?v=_WFtwrC3ylc', $project->catalogue_metadata['candidate_url']);
        $duplicate = Project::query()->where('catalogue_key', 'arif-cloud-portfolio:5')->sole();
        $this->assertNull($duplicate->video_url);
        $this->assertStringContainsString('#46', $duplicate->catalogue_metadata['notes']);
        $this->assertDatabaseHas('projects', ['catalogue_key' => 'arif-cloud-portfolio:46']);
        $this->assertNull(Project::query()->where('catalogue_key', 'arif-cloud-portfolio:36')->sole()->repository_url);
    }

    public function test_reimport_skips_existing_entries_and_preserves_changes_and_publication_state(): void
    {
        Storage::fake('public');
        $this->artisan('portfolio:import-catalogue')->assertSuccessful();
        $project = Project::query()->where('catalogue_key', 'arif-cloud-portfolio:9')->sole();
        $project->update(['title' => 'My revised project', 'slug' => 'revised-project', 'body' => 'My edited article.',
            'video_url' => null, 'cover_image' => 'projects/custom.jpg', 'is_published' => true, 'published_at' => now()->subDay()]);
        $before = $project->fresh()->getRawOriginal();

        $this->artisan('portfolio:import-catalogue')->expectsOutput('Imported 0 draft projects; skipped 47 previously imported entries.')->assertSuccessful();

        $this->assertDatabaseCount('projects', 47);
        $this->assertDatabaseCount('categories', 6);
        $this->assertSame($before, $project->fresh()->getRawOriginal());
        $this->assertCount(9, Storage::disk('public')->allFiles('projects/catalogue'));
    }

    public function test_existing_projects_and_categories_are_not_overwritten_when_slugs_collide(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create(['type' => 'project', 'slug' => 'cloud-server-deployment', 'name' => 'My cloud category', 'sort_order' => 99]);
        $existing = Project::factory()->published()->create(['slug' => 'wordpress-deployment-on-aws-ec2', 'title' => 'Existing client project', 'category_id' => $category->id]);
        $original = $existing->fresh()->getRawOriginal();
        $originalCategory = $category->fresh()->getRawOriginal();

        $this->artisan('portfolio:import-catalogue')->assertSuccessful();

        $this->assertDatabaseCount('projects', 48);
        $this->assertSame($original, $existing->fresh()->getRawOriginal());
        $this->assertSame($originalCategory, $category->fresh()->getRawOriginal());
        $imported = Project::query()->where('catalogue_key', 'arif-cloud-portfolio:1')->sole();
        $this->assertSame('wordpress-deployment-on-aws-ec2-catalogue-1', $imported->slug);
        $this->assertSame($category->id, $imported->category_id);
    }

    public function test_dry_run_validates_the_whole_catalogue_without_writing_records_or_images(): void
    {
        Storage::fake('public');

        $this->artisan('portfolio:import-catalogue', ['--dry-run' => true])
            ->expectsOutput('Valid catalogue: 47 entries. No records or files were changed.')->assertSuccessful();

        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('categories', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidEntries(): array
    {
        return [
            'missing required title' => ['title', ''],
            'unsafe slug' => ['slug', '../outside'],
            'unsafe video scheme' => ['video_url', 'javascript:alert(1)'],
            'wrong video host' => ['video_url', 'https://youtube.com.attacker.invalid/watch?v=FU4Zd3GTgYs'],
            'thumbnail path traversal' => ['thumbnail', '../outside.jpg'],
            'missing bundled image' => ['thumbnail', 'aaaaaaaaaaa.jpg'],
            'publication injection' => ['is_published', true],
            'import identity injection' => ['catalogue_key', 'a-different-source:1'],
            'unmatched video' => ['review.status', 'source_unavailable'],
        ];
    }

    #[DataProvider('invalidEntries')]
    public function test_invalid_catalogue_is_rejected_without_partial_records_or_images(string $field, mixed $value): void
    {
        Storage::fake('public');
        $catalogue = $this->catalogue();
        data_set($catalogue['entries'][8], $field, $value);
        $path = $this->fixture($catalogue);

        $this->artisan('portfolio:import-catalogue', ['file' => $path])->assertFailed();

        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('categories', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_duplicate_source_ids_are_rejected_before_creating_projects(): void
    {
        Storage::fake('public');
        $catalogue = $this->catalogue();
        $catalogue['entries'][1]['source_id'] = 1;

        $this->artisan('portfolio:import-catalogue', ['file' => $this->fixture($catalogue)])->assertFailed();

        $this->assertDatabaseCount('projects', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_unreadable_or_malformed_input_fails_without_changing_the_database(): void
    {
        $this->artisan('portfolio:import-catalogue', ['file' => storage_path('missing-catalogue.json')])->assertFailed();
        $disk = Storage::fake('catalogue-input');
        $disk->put('broken.json', '{invalid');

        $this->artisan('portfolio:import-catalogue', ['file' => $disk->path('broken.json')])
            ->expectsOutput('The catalogue is not valid JSON. No project records were imported.')->assertFailed();

        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_storage_failure_rolls_back_project_and_category_changes(): void
    {
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->once()->andReturn(false);
        $disk->shouldReceive('put')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        $this->artisan('portfolio:import-catalogue')->expectsOutput('Catalogue import failed. No database changes were committed. Check the Laravel log and retry.')->assertFailed();

        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_admin_can_review_and_edit_drafts_without_changing_import_provenance(): void
    {
        Storage::fake('public');
        $this->artisan('portfolio:import-catalogue')->assertSuccessful();
        $project = Project::query()->where('catalogue_key', 'arif-cloud-portfolio:9')->sole();
        $metadata = $project->catalogue_metadata;
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');

        Livewire::test(EditProject::class, ['record' => $project->id])->fillForm([
            'title' => 'Reviewed WordPress migration', 'catalogue_key' => 'tampered',
            'catalogue_metadata' => ['status' => 'tampered', 'notes' => 'tampered'],
        ])->call('save')->assertHasNoFormErrors();

        $this->assertSame('Reviewed WordPress migration', $project->fresh()->title);
        $this->assertSame('arif-cloud-portfolio:9', $project->fresh()->catalogue_key);
        $this->assertSame($metadata, $project->fresh()->catalogue_metadata);
        $this->assertFalse($project->fresh()->is_published);
    }

    public function test_admin_cannot_save_unsafe_video_links(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');
        $project = Project::factory()->create();

        Livewire::test(EditProject::class, ['record' => $project->id])->fillForm(['video_url' => 'https://attacker.invalid/video'])
            ->call('save')->assertHasFormErrors(['video_url']);

        $this->assertNull($project->fresh()->video_url);
    }
}
