<?php

namespace Tests\Feature;

use App\Filament\Resources\Profiles\Pages\EditProfile;
use App\Filament\Resources\ResumeEntries\Pages\CreateResumeEntry;
use App\Filament\Resources\SiteSettings\Pages\EditSiteSetting;
use App\Models\Profile;
use App\Models\ResumeEntry;
use App\Models\SiteSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageProfileSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function signInAsAdmin(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel('admin');
    }

    public function test_admin_can_edit_profile_and_structured_skills(): void
    {
        $this->signInAsAdmin();
        $profile = Profile::factory()->create();

        Livewire::test(EditProfile::class, ['record' => $profile->id])->fillForm([
            'headline' => 'Applications and infrastructure.',
            'skill_groups' => [['title' => 'Development', 'skills' => ['PHP', 'Laravel']]],
            'stats' => [['value' => '200+', 'label' => 'Websites built']],
        ])->call('save')->assertHasNoFormErrors();

        $this->assertSame('Applications and infrastructure.', $profile->fresh()->headline);
        $this->assertSame(['PHP', 'Laravel'], $profile->fresh()->skill_groups[0]['skills']);
    }

    public function test_admin_can_update_real_contact_settings(): void
    {
        $this->signInAsAdmin();
        $settings = SiteSetting::factory()->create();

        Livewire::test(EditSiteSetting::class, ['record' => $settings->id])->fillForm([
            'contact_email' => 'contact@example.com', 'whatsapp_number' => '+8801779440297',
            'facebook_url' => 'https://facebook.com/arifbillahcse',
            'brand_name' => 'Arif Billah', 'tagline' => 'Development and cloud services.', 'footer_text' => 'Arif Billah. All rights reserved.',
            'linkedin_url' => 'https://linkedin.com/in/arifbillahcse',
        ])->call('save')->assertHasNoFormErrors();

        $this->assertSame('contact@example.com', $settings->fresh()->contact_email);
        $this->assertSame('https://facebook.com/arifbillahcse', $settings->fresh()->facebook_url);
        $this->get('/contact')->assertOk()->assertSeeText('contact@example.com')->assertSeeText('Development and cloud services.')
            ->assertSee('https://linkedin.com/in/arifbillahcse', false)->assertSeeText('Arif Billah. All rights reserved.');
    }

    public function test_invalid_contact_details_are_rejected(): void
    {
        $this->signInAsAdmin();
        $settings = SiteSetting::factory()->create();

        Livewire::test(EditSiteSetting::class, ['record' => $settings->id])->fillForm([
            'contact_email' => 'not-an-email', 'whatsapp_number' => 'phone me',
            'facebook_url' => 'javascript:alert(1)',
        ])->call('save')->assertHasFormErrors(['contact_email', 'whatsapp_number', 'facebook_url']);

        $this->assertSame('owner@example.com', $settings->fresh()->contact_email);
    }

    public function test_formatted_whatsapp_numbers_are_saved_in_link_ready_format(): void
    {
        $this->signInAsAdmin();
        $settings = SiteSetting::factory()->create();
        Livewire::test(EditSiteSetting::class, ['record' => $settings->id])->fillForm(['whatsapp_number' => '+880 (1779) 440-297'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('+8801779440297', $settings->fresh()->whatsapp_number);
        $this->get('/contact')->assertSee('https://wa.me/8801779440297', false);
    }

    public function test_admin_can_create_resume_entries_without_invented_dates(): void
    {
        $this->signInAsAdmin();

        Livewire::test(CreateResumeEntry::class)->fillForm([
            'type' => 'education', 'title' => 'BSc in Computer Science',
            'description' => 'Computer Science studies.', 'sort_order' => 1, 'is_published' => true,
        ])->call('create')->assertHasNoFormErrors();

        $entry = ResumeEntry::query()->sole();
        $this->assertNull($entry->start_label);
        $this->assertNull($entry->end_label);
        $this->assertTrue($entry->is_published);
    }
}
