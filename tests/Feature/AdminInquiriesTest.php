<?php

namespace Tests\Feature;

use App\Filament\Resources\Inquiries\Pages\EditInquiry;
use App\Filament\Resources\Inquiries\Pages\ListInquiries;
use App\Jobs\SendInquiryNotification;
use App\Models\Inquiry;
use App\Models\SiteSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class AdminInquiriesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function signInAsAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        return $admin;
    }

    public function test_only_admins_can_read_and_update_client_inquiries(): void
    {
        $inquiry = Inquiry::factory()->create();
        $this->get('/admin/inquiries')->assertRedirect('/admin/login');
        $this->get('/admin/inquiries/'.$inquiry->id.'/edit')->assertRedirect('/admin/login');
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get('/admin/inquiries')->assertForbidden();
        $this->get('/admin/inquiries/'.$inquiry->id.'/edit')->assertForbidden();
        $this->assertFalse(Gate::forUser($user)->allows('update', $inquiry));
        $admin = $this->signInAsAdmin();
        $this->get('/admin/inquiries')->assertOk();
        $this->get('/admin/inquiries/'.$inquiry->id.'/edit')->assertOk();
        $this->assertFalse(Gate::forUser($admin)->allows('create', Inquiry::class));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $inquiry));
        $this->get('/admin/inquiries/create')->assertNotFound();
    }

    public function test_admin_can_add_notes_and_status_without_rewriting_the_client_message(): void
    {
        $this->signInAsAdmin();
        $inquiry = Inquiry::factory()->create(['message' => 'Original client request.']);
        Livewire::test(EditInquiry::class, ['record' => $inquiry->id])->fillForm([
            'status' => 'replied', 'admin_notes' => 'Discussed the scope.', 'message' => 'Tampered message', 'notification_status' => 'sent',
        ])->call('save')->assertHasNoFormErrors();
        $this->assertSame('replied', $inquiry->fresh()->status);
        $this->assertSame('Discussed the scope.', $inquiry->fresh()->admin_notes);
        $this->assertSame('Original client request.', $inquiry->fresh()->message);
        $this->assertSame('pending', $inquiry->fresh()->notification_status);
    }

    public function test_invalid_admin_status_is_rejected(): void
    {
        $this->signInAsAdmin();
        $inquiry = Inquiry::factory()->create();
        Livewire::test(EditInquiry::class, ['record' => $inquiry->id])->fillForm(['status' => 'unknown'])->call('save')->assertHasFormErrors(['status']);
        $this->assertSame('new', $inquiry->fresh()->status);
    }

    public function test_admin_can_retry_a_failed_notification_after_mail_is_configured(): void
    {
        config(['mail.default' => 'smtp']);
        $this->signInAsAdmin();
        SiteSetting::factory()->create();
        $inquiry = Inquiry::factory()->create(['notification_status' => 'failed']);
        Queue::fake([SendInquiryNotification::class]);
        Livewire::test(EditInquiry::class, ['record' => $inquiry->id])->callAction('retryNotification');
        $this->assertSame('pending', $inquiry->fresh()->notification_status);
        Queue::assertPushed(SendInquiryNotification::class);
    }

    public function test_admin_can_filter_the_inbox_and_client_text_is_escaped(): void
    {
        $this->signInAsAdmin();
        $new = Inquiry::factory()->create(['name' => '<img src=x onerror=alert(1)>', 'status' => 'new']);
        $archived = Inquiry::factory()->create(['status' => 'archived']);
        Livewire::test(ListInquiries::class)->filterTable('status', 'new')->assertCanSeeTableRecords([$new])->assertCanNotSeeTableRecords([$archived])
            ->assertSeeHtml('&lt;img src=x onerror=alert(1)&gt;')->assertDontSeeHtml('<img src=x onerror=alert(1)>');
    }
}
