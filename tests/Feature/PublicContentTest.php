<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Project;
use App\Models\ResumeEntry;
use App\Models\Service;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicContentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function contentRoutes(): array
    {
        return [['projects', Project::class], ['blog', Post::class], ['services', Service::class]];
    }

    #[DataProvider('contentRoutes')]
    public function test_only_published_content_is_visible_in_lists_and_detail_pages(string $path, string $model): void
    {
        $this->freezeTime();
        $published = $model::factory()->published()->create(['title' => 'Public entry', 'slug' => 'public-entry', 'body' => "## The approach\n\nA **useful** result."]);
        $draft = $model::factory()->create(['title' => 'Secret draft']);
        $future = $model::factory()->published()->create(['title' => 'Future release', 'published_at' => now()->addDay()]);

        $this->get('/'.$path)->assertOk()->assertSeeText('Public entry')->assertDontSeeText('Secret draft')->assertDontSeeText('Future release');
        $this->get('/'.$path.'/'.$published->slug)->assertOk()->assertSee('<strong>useful</strong>', false)->assertSee('aria-current="page"', false);
        $this->get('/'.$path.'/'.$draft->slug)->assertNotFound();
        $this->get('/'.$path.'/'.$future->slug)->assertNotFound();
        $this->get('/'.$path.'/does-not-exist')->assertNotFound();

        $published->update(['is_published' => false]);
        $this->get('/'.$path.'/'.$published->slug)->assertNotFound();
        $this->get('/'.$path)->assertDontSeeText('Public entry');
    }

    public function test_a_hundred_projects_are_paginated_and_filtered_by_database_category(): void
    {
        $category = Category::factory()->create(['type' => 'project', 'slug' => 'applications', 'name' => 'Applications']);
        $otherCategory = Category::factory()->create(['type' => 'project']);
        foreach (range(1, 100) as $number) {
            Project::factory()->published()->create(['category_id' => $category->id, 'title' => 'Work '.str_pad($number, 3, '0', STR_PAD_LEFT), 'sort_order' => $number]);
        }
        Project::factory()->published()->create(['category_id' => $otherCategory->id, 'title' => 'Different category']);

        $first = $this->get('/projects?category=applications');
        $first->assertOk()->assertSeeText('Work 001')->assertDontSeeText('Work 010')->assertDontSeeText('Different category')
            ->assertSee('category=applications&amp;page=2', false)->assertViewHas('projects', fn ($projects) => $projects->total() === 100 && $projects->count() === 9);
        $this->get('/projects?category=applications&page=2')->assertOk()->assertSeeText('Work 010')->assertDontSeeText('Work 001');
        $this->get('/projects?category=applications&page=12')->assertOk()->assertSeeText('Work 100')->assertViewHas('projects', fn ($projects) => $projects->count() === 1);
        $this->get('/projects?category=unknown')->assertOk()->assertSeeText('No matching projects');
        $this->get('/projects?category=applications&page=999')->assertOk()
            ->assertSeeText('No projects on this page')->assertSeeText('Return to the first page')
            ->assertSee('category=applications&amp;page=1', false)
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_blog_search_and_category_are_preserved_when_paginating(): void
    {
        $category = Category::factory()->create(['type' => 'post', 'slug' => 'linux']);
        Post::factory()->published()->count(11)->create(['category_id' => $category->id, 'title' => 'Linux deployment guide']);
        Post::factory()->published()->create(['title' => 'Unrelated article', 'excerpt' => 'A different subject']);
        Post::factory()->create(['category_id' => $category->id, 'title' => 'Linux secret draft']);

        $this->get('/blog?category=linux&q=deployment')->assertOk()->assertDontSeeText('Unrelated article')->assertDontSeeText('Linux secret draft')
            ->assertSee('category=linux&amp;q=deployment&amp;page=2', false)
            ->assertViewHas('posts', fn ($posts) => $posts->total() === 11 && $posts->count() === 9);
        $this->get('/blog?category=linux&q=deployment&page=2')->assertOk()->assertViewHas('posts', fn ($posts) => $posts->count() === 2);
        $this->get('/blog?q=nonexistent')->assertOk()->assertSeeText('No matching articles');
        $this->get('/blog?q='.urlencode("' OR 1=1 --"))->assertOk()->assertSeeText('No matching articles');
    }

    public function test_invalid_collection_query_parameters_are_rejected(): void
    {
        $this->getJson('/blog?q[]=x&category[]=x&page=-1')->assertUnprocessable()->assertJsonValidationErrors(['q', 'category', 'page']);
        $this->getJson('/projects?category[]=x&page=bad')->assertUnprocessable()->assertJsonValidationErrors(['category', 'page']);
    }

    public function test_category_filters_accept_the_slug_length_allowed_by_the_admin(): void
    {
        $slug = str_repeat('a', 100);
        $category = Category::factory()->create(['type' => 'project', 'slug' => $slug]);
        Project::factory()->published()->create(['category_id' => $category->id, 'title' => 'Long category project']);

        $this->get('/projects?category='.$slug)->assertOk()->assertSeeText('Long category project');
    }

    public function test_home_uses_six_selected_published_projects_and_reflects_updates(): void
    {
        $profile = Profile::factory()->create(['home_heading' => 'Applications and cloud, together.', 'home_intro' => 'An updated introduction.']);
        Project::factory()->published()->count(7)->create(['is_featured' => false]);
        $featured = Project::factory()->published()->create(['title' => 'Featured platform', 'is_featured' => true, 'cover_image' => 'projects/cover.webp']);
        Project::factory()->create(['title' => 'Hidden project']);

        $this->get('/')->assertOk()->assertSeeText('Applications and cloud, together.')->assertSeeText('An updated introduction.')
            ->assertSeeText('Featured platform')->assertDontSeeText('Hidden project')->assertSee('/storage/projects/cover.webp', false)
            ->assertViewHas('projects', fn ($projects) => $projects->count() === 6 && $projects->first()->id === $featured->id);
        $profile->update(['home_intro' => 'Saved later in the admin.']);
        $this->get('/')->assertSeeText('Saved later in the admin.')->assertDontSeeText('An updated introduction.');
    }

    public function test_about_renders_profile_structures_and_only_published_resume_entries(): void
    {
        Profile::factory()->create([
            'headline' => 'My connected career',
            'biography' => '**Building** across the stack.',
            'portrait_path' => 'profile/portrait.png',
            'journey' => [['label' => 'First', 'title' => 'Learning the web', 'description' => 'A practical beginning.']],
            'skill_groups' => [['title' => 'Infrastructure', 'skills' => ['Bash scripting']]],
            'stats' => [['value' => '200+', 'label' => 'Websites built']],
            'businesses' => [['name' => 'My hosting company', 'focus' => 'Hosting', 'description' => 'Practical server care.']],
            'support_story' => 'Helping customers solve problems.',
            'vision_quote' => 'Build useful things.',
        ]);
        ResumeEntry::factory()->create(['type' => 'education', 'title' => 'Computer Science', 'is_published' => true]);
        ResumeEntry::factory()->create(['type' => 'extracurricular', 'title' => 'Community workshop', 'is_published' => true]);
        ResumeEntry::factory()->create(['title' => 'Private resume draft', 'is_published' => false]);

        $this->get('/about')->assertOk()->assertSeeText('My connected career')->assertSee('<strong>Building</strong>', false)
            ->assertSeeText('Learning the web')->assertSeeText('Bash scripting')->assertSeeText('200+')->assertSeeText('My hosting company')
            ->assertSeeText('Helping customers solve problems.')->assertSeeText('Computer Science')->assertSeeText('Community workshop')
            ->assertDontSeeText('Private resume draft')->assertSee('/storage/profile/portrait.png', false);
    }

    public function test_services_render_in_process_groups_with_deliverables_and_details(): void
    {
        Service::factory()->published()->create(['title' => 'Custom platform', 'slug' => 'custom-platform', 'group' => 'development', 'sort_order' => 1, 'offerings' => ['Workflow planning'], 'body' => 'Built around your business.']);
        Service::factory()->published()->create(['title' => 'Server deployment', 'group' => 'infrastructure', 'sort_order' => 2]);
        $this->get('/services')->assertOk()->assertSeeInOrder(['Custom platform', 'Server deployment'])->assertSeeText('Workflow planning')->assertSee('/services/custom-platform', false);
        $this->get('/services/custom-platform')->assertOk()->assertSeeText('Built around your business.')->assertSeeText('Workflow planning');
    }

    public function test_site_settings_supply_links_branding_contact_details_and_whatsapp_links(): void
    {
        $settings = SiteSetting::factory()->create([
            'brand_name' => 'Arif Studio', 'contact_email' => 'contact@example.com', 'whatsapp_number' => '+441234567890',
            'facebook_url' => 'https://facebook.com/updated-profile', 'linkedin_url' => 'https://linkedin.com/in/updated-profile',
            'github_url' => 'https://github.com/updated-profile', 'footer_text' => 'Updated footer.',
            'seo_title' => 'Custom home title', 'seo_description' => 'Custom home description.',
        ]);
        $this->get('/contact')->assertOk()->assertSeeText('Arif Studio')->assertSeeText('contact@example.com')
            ->assertSee('href="https://wa.me/441234567890"', false)->assertSee('data-whatsapp="441234567890"', false)
            ->assertSee('https://facebook.com/updated-profile', false)->assertSee('https://linkedin.com/in/updated-profile', false)
            ->assertSee('https://github.com/updated-profile', false)->assertSeeText('Updated footer.');
        $this->get('/')->assertSee('<title>Custom home title</title>', false)->assertSee('content="Custom home description."', false);
        $settings->update(['contact_email' => 'new@example.com', 'facebook_url' => null]);
        $this->get('/contact')->assertSee('mailto:new@example.com', false)->assertDontSee('https://facebook.com/updated-profile', false);
    }

    public function test_details_render_media_links_seo_and_keep_demo_content_labelled(): void
    {
        $project = Project::factory()->published()->create([
            'slug' => 'a-platform', 'seo_title' => 'Platform case study', 'seo_description' => 'A custom case study.',
            'gallery' => ['projects/screenshot.png'], 'tools' => ['Laravel'], 'external_url' => 'https://example.com/platform',
            'repository_url' => 'https://github.com/example/platform', 'is_demo' => true,
        ]);
        $this->get('/projects/a-platform')->assertOk()->assertSeeText('Platform case study')->assertSee('content="A custom case study."', false)
            ->assertSeeText('illustrative project concept')->assertSee('content="noindex, follow"', false)
            ->assertSee('/storage/projects/screenshot.png', false)->assertSee('href="https://example.com/platform"', false)
            ->assertSee('href="https://github.com/example/platform"', false)->assertSeeText('Laravel');
        $project->category()->dissociate();
        $project->save();
        $this->get('/projects/a-platform')->assertOk();
    }

    public function test_original_articles_are_indexable_while_sample_articles_remain_noindex(): void
    {
        Post::factory()->published()->create(['slug' => 'sample', 'is_demo' => true]);
        $this->get('/blog')->assertSee('content="noindex, follow"', false);
        $this->get('/blog/sample')->assertSee('content="noindex, follow"', false)->assertSeeText('sample article');
        Post::factory()->published()->create(['slug' => 'original', 'is_demo' => false]);
        $this->get('/blog')->assertDontSee('content="noindex, follow"', false);
        $this->get('/blog/original')->assertDontSee('content="noindex, follow"', false);
    }

    public function test_featured_blog_article_uses_database_content_and_links_to_the_article(): void
    {
        Post::factory()->published()->create(['title' => 'Featured guide', 'slug' => 'featured-guide', 'is_featured' => true]);
        $this->get('/blog')->assertOk()->assertViewHas('featured', fn ($post) => $post->slug === 'featured-guide')->assertSee('/blog/featured-guide', false);
        $this->get('/blog?q=missing')->assertViewHas('featured', null);
    }

    public function test_public_content_escapes_text_and_removes_unsafe_markdown_and_urls(): void
    {
        Project::factory()->published()->create([
            'slug' => 'safe-rendering', 'title' => '<img src=x onerror=alert(1)>',
            'body' => "<script>alert('attack')</script>\n\n[safe label](javascript:alert(1))\n\n**Visible content**",
            'external_url' => 'javascript:alert(1)', 'cover_image' => '../private.png',
            'gallery' => ['https://evil.example/track.svg', 'projects/../secret.png'],
        ]);
        SiteSetting::factory()->create(['facebook_url' => 'javascript:alert(1)']);
        $this->get('/projects/safe-rendering')->assertOk()->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false)->assertDontSee("<script>alert('attack')</script>", false)
            ->assertDontSee('href="javascript:', false)->assertDontSee('src="../', false)->assertDontSee('evil.example', false)
            ->assertSee('<strong>Visible content</strong>', false);
    }

    public function test_empty_database_has_valid_empty_pages_and_portrait_fallback(): void
    {
        $this->get('/')->assertOk()->assertSee('/assets/images/arif-formal.png', false);
        $this->get('/about')->assertOk();
        $this->get('/services')->assertOk();
        $this->get('/projects')->assertOk()->assertSeeText('Projects are on the way.');
        $this->get('/blog')->assertOk()->assertSeeText('New articles are on the way.');
        $this->get('/contact')->assertOk();
    }
}
