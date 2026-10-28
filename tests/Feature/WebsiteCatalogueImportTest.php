<?php

namespace Tests\Feature;

use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Models\Category;
use App\Models\Project;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebsiteCatalogueImportTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function cataloguePath(): string
    {
        return resource_path('catalogue/web-development.json');
    }

    /** @return array<string, mixed> */
    private function catalogue(): array
    {
        return json_decode(file_get_contents($this->cataloguePath()), true, 64, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $catalogue */
    private function fixture(array $catalogue): string
    {
        $disk = Storage::fake('catalogue-input');
        $disk->put('websites.json', json_encode($catalogue, JSON_THROW_ON_ERROR));

        return $disk->path('websites.json');
    }

    public function test_only_22_reviewed_websites_import_as_drafts_without_changing_cloud_projects(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        $this->artisan('portfolio:import-catalogue')->assertSuccessful();
        $cloud = Project::query()->where('catalogue_key', 'arif-cloud-portfolio:9')->sole();
        $cloud->update(['is_published' => true, 'body' => 'My edited cloud case study.']);
        $before = Project::query()->orderBy('id')->get()->map->getRawOriginal()->all();

        $this->artisan('portfolio:import-catalogue', ['file' => $this->cataloguePath()])
            ->expectsOutput('Imported 22 draft projects; skipped 0 previously imported entries.')->assertSuccessful();

        $this->assertDatabaseCount('projects', 69);
        $this->assertSame($before, Project::query()->where('catalogue_key', 'like', 'arif-cloud-portfolio:%')->orderBy('id')->get()->map->getRawOriginal()->all());
        $websites = Project::query()->where('catalogue_key', 'like', 'arif-web-development:%')->get();
        $this->assertCount(22, $websites);
        foreach ($websites as $project) {
            $this->assertFalse($project->is_published);
            $this->assertFalse($project->is_featured);
            $this->assertFalse($project->is_demo);
            $this->assertNull($project->published_at);
            $this->assertNull($project->video_url);
            $this->assertNull($project->cover_image);
            $this->assertSame([], $project->gallery);
            $this->assertSame([], $project->tools);
            $this->assertNotEmpty($project->external_url);
        }
        $upc = $websites->firstWhere('catalogue_key', 'arif-web-development:web-002');
        $this->assertSame('UPC Mart', $upc->title);
        $this->assertSame('https://www.upcmartbd.com/', $upc->external_url);
        $this->assertSame('Website Development.docx', $upc->catalogue_metadata['source_file']);
        $this->assertStringContainsString('Source skill claims (unverified)', $upc->catalogue_metadata['source_details']);
        foreach (['web-030', 'web-038', 'web-071'] as $heldId) {
            $this->assertDatabaseMissing('projects', ['catalogue_key' => 'arif-web-development:'.$heldId]);
        }
        $this->assertCount(9, Storage::disk('public')->allFiles('projects/catalogue'));
    }

    public function test_website_dry_run_validates_without_creating_categories_records_or_images(): void
    {
        Storage::fake('public');

        $this->artisan('portfolio:import-catalogue', ['file' => $this->cataloguePath(), '--dry-run' => true])
            ->expectsOutput('Valid catalogue: 22 entries. No records or files were changed.')->assertSuccessful();

        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('categories', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_reimport_preserves_edited_websites_existing_categories_and_colliding_projects(): void
    {
        $category = Category::factory()->create(['type' => 'project', 'slug' => 'commerce', 'name' => 'My store projects', 'sort_order' => 99]);
        $existing = Project::factory()->published()->create(['slug' => 'upc-mart', 'category_id' => $category->id]);
        $existingBefore = $existing->fresh()->getRawOriginal();
        $categoryBefore = $category->fresh()->getRawOriginal();
        $this->artisan('portfolio:import-catalogue', ['file' => $this->cataloguePath()])->assertSuccessful();
        $imported = Project::query()->where('catalogue_key', 'arif-web-development:web-002')->sole();
        $this->assertSame('upc-mart-catalogue-web-002', $imported->slug);
        $imported->update(['title' => 'My reviewed store', 'external_url' => 'https://example.com/reviewed', 'is_published' => true,
            'cover_image' => 'projects/my-cover.webp', 'gallery' => ['projects/my-gallery.webp'], 'tools' => ['Laravel']]);
        $importedBefore = $imported->fresh()->getRawOriginal();

        $this->artisan('portfolio:import-catalogue', ['file' => $this->cataloguePath()])
            ->expectsOutput('Imported 0 draft projects; skipped 22 previously imported entries.')->assertSuccessful();

        $this->assertDatabaseCount('projects', 23);
        $this->assertSame($existingBefore, $existing->fresh()->getRawOriginal());
        $this->assertSame($categoryBefore, $category->fresh()->getRawOriginal());
        $this->assertSame($importedBefore, $imported->fresh()->getRawOriginal());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidWebsiteEntries(): array
    {
        return [
            'missing website' => [['external_url' => null]],
            'unsafe scheme' => [['external_url' => 'javascript:alert(1)']],
            'FTP destination' => [['external_url' => 'ftp://example.com/']],
            'credentials' => [['external_url' => 'https://user:password@example.com/', 'review.candidate_url' => 'https://user:password@example.com/']],
            'admin URL' => [['external_url' => 'https://example.com/wp-admin/', 'review.candidate_url' => 'https://example.com/wp-admin/']],
            'encoded admin URL' => [['external_url' => 'https://example.com/%77p-admin/', 'review.candidate_url' => 'https://example.com/%77p-admin/']],
            'login URL' => [['external_url' => 'https://example.com/wp-login.php', 'review.candidate_url' => 'https://example.com/wp-login.php']],
            'unreviewed destination' => [['external_url' => 'https://different.example/']],
            'held status' => [['review.status' => 'hold_link_verification']],
            'unverified video' => [['video_url' => 'https://youtu.be/FU4Zd3GTgYs']],
            'unverified thumbnail' => [['thumbnail' => 'FU4Zd3GTgYs.jpg']],
            'publication injection' => [['is_published' => true]],
            'identity injection' => [['catalogue_key' => 'arif-cloud-portfolio:9']],
            'invalid source identity' => [['source_id' => 2]],
            'duplicate source identity' => [['source_id' => 'web-002']],
        ];
    }

    #[DataProvider('invalidWebsiteEntries')]
    public function test_invalid_website_catalogues_fail_before_any_records_or_files_are_written(array $changes): void
    {
        Storage::fake('public');
        $catalogue = $this->catalogue();
        foreach ($changes as $field => $value) {
            data_set($catalogue['entries'][1], $field, $value);
        }

        $this->artisan('portfolio:import-catalogue', ['file' => $this->fixture($catalogue)])->assertFailed();

        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('categories', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_phase_one_review_file_is_not_mistaken_for_an_import_manifest(): void
    {
        $this->artisan('portfolio:import-catalogue', ['file' => resource_path('catalogue/web-development-review.json')])->assertFailed();

        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_admin_edits_website_details_without_overwriting_source_notes(): void
    {
        $this->artisan('portfolio:import-catalogue', ['file' => $this->cataloguePath()])->assertSuccessful();
        $project = Project::query()->where('catalogue_key', 'arif-web-development:web-002')->sole();
        $metadata = $project->catalogue_metadata;
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');

        Livewire::test(EditProject::class, ['record' => $project->id])->fillForm([
            'title' => 'Reviewed store website', 'external_url' => 'https://example.com/store', 'tools' => ['WordPress'],
            'catalogue_metadata' => ['source_details' => 'tampered', 'notes' => 'tampered'],
        ])->call('save')->assertHasNoFormErrors();

        $this->assertSame('Reviewed store website', $project->fresh()->title);
        $this->assertSame('https://example.com/store', $project->fresh()->external_url);
        $this->assertSame(['WordPress'], $project->fresh()->tools);
        $this->assertSame($metadata, $project->fresh()->catalogue_metadata);
        $this->assertFalse($project->fresh()->is_published);
    }

    public function test_admin_can_filter_website_imports_without_including_cloud_or_manual_projects(): void
    {
        $website = Project::factory()->create();
        $website->forceFill(['catalogue_key' => 'arif-web-development:web-002'])->save();
        $cloud = Project::factory()->create();
        $cloud->forceFill(['catalogue_key' => 'arif-cloud-portfolio:9'])->save();
        $manual = Project::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');

        Livewire::test(ListProjects::class)->filterTable('catalogue_source', 'arif-web-development')
            ->assertCanSeeTableRecords([$website])->assertCanNotSeeTableRecords([$cloud, $manual])
            ->filterTable('catalogue_source', 'arif-cloud-portfolio')
            ->assertCanSeeTableRecords([$cloud])->assertCanNotSeeTableRecords([$website, $manual]);
    }
}
