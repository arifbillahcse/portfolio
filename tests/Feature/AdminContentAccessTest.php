<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Project;
use App\Models\ResumeEntry;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminContentAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function adminPages(): array
    {
        return [
            'project list' => ['/admin/projects'], 'project create' => ['/admin/projects/create'],
            'blog list' => ['/admin/posts'], 'blog create' => ['/admin/posts/create'],
            'service list' => ['/admin/services'], 'service create' => ['/admin/services/create'],
            'categories' => ['/admin/categories'], 'category create' => ['/admin/categories/create'],
            'profile' => ['/admin/profiles'], 'resume' => ['/admin/resume-entries'],
            'resume create' => ['/admin/resume-entries/create'], 'settings' => ['/admin/site-settings'],
        ];
    }

    #[DataProvider('adminPages')]
    public function test_admin_can_open_content_management_pages(string $path): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get($path)->assertOk();
    }

    public static function editPages(): array
    {
        return [
            [Project::class, 'projects'], [Post::class, 'posts'], [Service::class, 'services'],
            [Category::class, 'categories'], [Profile::class, 'profiles'],
            [ResumeEntry::class, 'resume-entries'], [SiteSetting::class, 'site-settings'],
        ];
    }

    #[DataProvider('editPages')]
    public function test_admin_can_open_existing_content_editors(string $model, string $path): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $record = $model::factory()->create();

        $this->actingAs($admin)->get('/admin/'.$path.'/'.$record->getKey().'/edit')->assertOk();
    }

    public static function policyCases(): array
    {
        $cases = [];
        foreach ([Category::class, Project::class, Post::class, Service::class, ResumeEntry::class] as $model) {
            $cases[$model.' admin'] = [$model, true, true, true];
            $cases[$model.' member'] = [$model, false, false, false];
        }
        foreach ([Profile::class, SiteSetting::class] as $model) {
            $cases[$model.' admin'] = [$model, true, true, false];
            $cases[$model.' member'] = [$model, false, false, false];
        }

        return $cases;
    }

    #[DataProvider('policyCases')]
    public function test_content_policies_apply_the_permission_matrix(string $model, bool $isAdmin, bool $canEdit, bool $canCreateOrDelete): void
    {
        $user = User::factory()->make(['is_admin' => $isAdmin]);
        $record = new $model;
        $gate = Gate::forUser($user);

        $this->assertSame($canEdit, $gate->allows('viewAny', $model));
        $this->assertSame($canEdit, $gate->allows('view', $record));
        $this->assertSame($canEdit, $gate->allows('update', $record));
        $this->assertSame($canCreateOrDelete, $gate->allows('create', $model));
        $this->assertSame($canCreateOrDelete, $gate->allows('delete', $record));
        $this->assertSame($canCreateOrDelete, $gate->allows('deleteAny', $model));
    }

    public function test_content_routes_require_admin_access(): void
    {
        $project = Project::factory()->create();

        $this->get('/admin/projects')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get('/admin/projects/'.$project->id.'/edit')->assertForbidden();
    }

    public function test_singleton_settings_have_no_create_routes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/profiles/create')->assertNotFound();
        $this->get('/admin/site-settings/create')->assertNotFound();
    }
}
