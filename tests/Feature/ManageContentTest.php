<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Models\Category;
use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ManageContentTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function signInAsAdmin(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');
    }

    public static function publishableTypes(): array
    {
        return [
            'project' => [Project::class, CreateProject::class, EditProject::class, 'project', 'excerpt'],
            'post' => [Post::class, CreatePost::class, EditPost::class, 'post', 'excerpt'],
            'service' => [Service::class, CreateService::class, EditService::class, null, 'summary'],
        ];
    }

    private function validContent(?string $categoryType, string $summaryField): array
    {
        $data = [
            'title' => 'A practical client project', 'slug' => 'practical-client-project',
            $summaryField => 'A useful description of the project or service.',
            'body' => 'Detailed content for the client project.', 'is_published' => false,
            'sort_order' => 0,
        ];
        if ($categoryType) {
            $data['category_id'] = Category::factory()->create(['type' => $categoryType])->id;
        }

        return $data;
    }

    #[DataProvider('publishableTypes')]
    public function test_admin_can_create_edit_and_delete_content(string $model, string $createPage, string $editPage, ?string $categoryType, string $summaryField): void
    {
        $this->signInAsAdmin();
        $data = $this->validContent($categoryType, $summaryField);

        Livewire::test($createPage)->fillForm($data)->call('create')->assertHasNoFormErrors();

        $record = $model::query()->where('slug', $data['slug'])->sole();
        $this->assertSame($data['title'], $record->title);
        $this->assertFalse($record->is_published);

        Livewire::test($editPage, ['record' => $record->id])
            ->fillForm(['title' => 'Updated client project', 'is_published' => true])
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame('Updated client project', $record->fresh()->title);
        $this->assertTrue($record->fresh()->is_published);

        Livewire::test($editPage, ['record' => $record->id])->callAction('delete');

        $this->assertModelMissing($record);
    }

    public function test_project_cannot_use_a_blog_category(): void
    {
        $this->signInAsAdmin();
        $data = $this->validContent('post', 'excerpt');

        Livewire::test(CreateProject::class)->fillForm($data)->call('create')->assertHasFormErrors(['category_id']);

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_duplicate_and_invalid_slugs_are_rejected(): void
    {
        $this->signInAsAdmin();
        Project::factory()->create(['slug' => 'existing-project']);
        $data = $this->validContent('project', 'excerpt');

        Livewire::test(CreateProject::class)->fillForm([...$data, 'slug' => 'existing-project'])
            ->call('create')->assertHasFormErrors(['slug' => 'unique']);
        Livewire::test(CreateProject::class)->fillForm([...$data, 'slug' => '../invalid slug'])
            ->call('create')->assertHasFormErrors(['slug' => 'regex']);

        $this->assertDatabaseCount('projects', 1);
    }

    public function test_required_content_fields_are_validated(): void
    {
        $this->signInAsAdmin();

        Livewire::test(CreatePost::class)->fillForm(['title' => '', 'slug' => '', 'excerpt' => '', 'body' => ''])
            ->call('create')->assertHasFormErrors(['title' => 'required', 'slug' => 'required', 'excerpt' => 'required', 'body' => 'required', 'category_id' => 'required']);

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_unsafe_project_urls_are_rejected(): void
    {
        $this->signInAsAdmin();
        $data = $this->validContent('project', 'excerpt');

        Livewire::test(CreateProject::class)->fillForm([...$data, 'external_url' => 'javascript:alert(1)'])
            ->call('create')->assertHasFormErrors(['external_url']);

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_category_slugs_are_unique_within_their_content_type(): void
    {
        $this->signInAsAdmin();
        Category::factory()->create(['slug' => 'wordpress', 'type' => 'project']);

        Livewire::test(CreateCategory::class)->fillForm(['name' => 'WordPress', 'slug' => 'wordpress', 'type' => 'post', 'sort_order' => 0])
            ->call('create')->assertHasNoFormErrors();
        Livewire::test(CreateCategory::class)->fillForm(['name' => 'Duplicate', 'slug' => 'wordpress', 'type' => 'project', 'sort_order' => 0])
            ->call('create')->assertHasFormErrors(['slug' => 'unique']);

        $this->assertDatabaseCount('categories', 2);
    }

    public function test_project_image_is_stored_on_public_disk(): void
    {
        $this->signInAsAdmin();
        Storage::fake('public');
        $data = $this->validContent('project', 'excerpt');

        Livewire::test(CreateProject::class)->fillForm([...$data, 'cover_image' => UploadedFile::fake()->image('cover.png')])
            ->call('create')->assertHasNoFormErrors();

        $project = Project::query()->sole();
        $this->assertStringStartsWith('projects/', $project->cover_image);
        Storage::disk('public')->assertExists($project->cover_image);
    }

    public function test_non_image_upload_is_rejected(): void
    {
        $this->signInAsAdmin();
        Storage::fake('public');
        $data = $this->validContent('project', 'excerpt');

        Livewire::test(CreateProject::class)->fillForm([...$data, 'cover_image' => UploadedFile::fake()->create('script.php', 10, 'text/x-php')])
            ->call('create')->assertHasFormErrors(['cover_image']);

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_admin_can_upload_an_inline_blog_image_and_publish_it_in_the_article(): void
    {
        $this->signInAsAdmin();
        Storage::fake('public');
        $page = Livewire::test(CreatePost::class);
        $componentKey = $page->instance()->form->getComponentByStatePath('body')->getKey();

        $page->set('componentFileAttachments.data.body', UploadedFile::fake()->image('article.png'))
            ->call('callSchemaComponentMethod', $componentKey, 'saveUploadedFileAttachmentAndGetUrl');

        $paths = Storage::disk('public')->allFiles('posts/content');
        $this->assertCount(1, $paths);
        $url = Storage::disk('public')->url($paths[0]);
        $page->assertReturned($url);
        $page->fillForm([...$this->validContent('post', 'excerpt'), 'body' => '![Article screenshot]('.$url.')', 'is_published' => true])
            ->call('create')->assertHasNoFormErrors();

        $post = Post::query()->sole();
        $this->get('/blog/'.$post->slug)->assertOk()->assertSee('src="'.$url.'"', false)->assertSee('alt="Article screenshot"', false);
    }

    /** @return array<string, array{bool}> */
    public static function invalidInlineImages(): array
    {
        return ['executable attachment' => [false], 'image larger than 4 MB' => [true]];
    }

    #[DataProvider('invalidInlineImages')]
    public function test_inline_blog_image_upload_rejects_invalid_attachments(bool $oversized): void
    {
        $this->signInAsAdmin();
        Storage::fake('public');
        $page = Livewire::test(CreatePost::class);
        $componentKey = $page->instance()->form->getComponentByStatePath('body')->getKey();
        $file = $oversized ? UploadedFile::fake()->image('large.png')->size(5000) : UploadedFile::fake()->create('script.php', 10, 'text/x-php');

        $page->set('componentFileAttachments.data.body', $file)->call('callSchemaComponentMethod', $componentKey, 'saveUploadedFileAttachmentAndGetUrl')
            ->assertReturned(null);

        $this->assertSame([], Storage::disk('public')->allFiles('posts/content'));
    }

    public function test_admin_title_is_escaped_in_the_table(): void
    {
        $this->signInAsAdmin();
        Project::factory()->create(['title' => '<script>alert("content")</script>']);

        $this->get('/admin/projects')->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert("content")</script>', false);
    }

    public function test_oversized_image_upload_is_rejected(): void
    {
        $this->signInAsAdmin();
        Storage::fake('public');
        $data = $this->validContent('project', 'excerpt');

        Livewire::test(CreateProject::class)->fillForm([...$data, 'cover_image' => UploadedFile::fake()->image('large.png')->size(5000)])
            ->call('create')->assertHasFormErrors(['cover_image']);

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_service_group_and_icon_cannot_be_arbitrary_values(): void
    {
        $this->signInAsAdmin();
        $data = $this->validContent(null, 'summary');

        Livewire::test(CreateService::class)->fillForm([...$data, 'group' => 'invalid', 'icon' => '<script>'])
            ->call('create')->assertHasFormErrors(['group', 'icon']);

        $this->assertDatabaseCount('services', 0);
    }
}
