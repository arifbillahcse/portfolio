<?php

namespace Tests\Feature;

use App\Filament\Pages\AccountSettings;
use App\Models\SiteSetting;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['is_admin' => true, 'password' => 'Current-Password-2026!']);
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        return $admin;
    }

    public function test_account_settings_are_available_only_to_signed_in_admins(): void
    {
        $this->get('/admin/profile')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get('/admin/profile')->assertForbidden();
        $this->admin();
        $this->get('/admin/profile')->assertOk()->assertSeeText('Account settings')->assertSeeText('Sign-in email')->assertSeeText('New password');
        SiteSetting::factory()->create();
        $this->get('/admin/site-settings')->assertOk()->assertSeeText('Account & password');
    }

    public function test_admin_can_update_their_name_without_changing_password_or_other_accounts(): void
    {
        $admin = $this->admin();
        $originalHash = $admin->password;
        $other = User::factory()->create(['name' => 'Another user']);
        Livewire::test(AccountSettings::class)->fillForm(['name' => 'Arif Billah', 'password' => ''])
            ->set('data.id', $other->id)->set('data.is_admin', false)->set('data.email_verified_at', '2030-01-01')
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('Arif Billah', $admin->fresh()->name);
        $this->assertSame($originalHash, $admin->fresh()->password);
        $this->assertTrue($admin->fresh()->is_admin);
        $this->assertSame('Another user', $other->fresh()->name);
        $this->assertSame($admin->email_verified_at->toDateTimeString(), $admin->fresh()->email_verified_at->toDateTimeString());
    }

    public function test_sign_in_email_can_change_with_current_password_without_changing_public_contact_email(): void
    {
        $admin = $this->admin();
        $settings = SiteSetting::factory()->create(['contact_email' => 'public@example.com']);
        Livewire::test(AccountSettings::class)->fillForm(['email' => 'new-login@example.com', 'currentPassword' => 'Current-Password-2026!'])
            ->call('save')->assertHasNoFormErrors()->assertSet('data.currentPassword', null);
        $this->assertSame('new-login@example.com', $admin->fresh()->email);
        $this->assertSame('public@example.com', $settings->fresh()->contact_email);
        Filament::auth()->logout();
        Livewire::test(Login::class)->fillForm(['email' => 'new-login@example.com', 'password' => 'Current-Password-2026!'])
            ->call('authenticate')->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_can_change_password_and_sign_in_only_with_the_new_password(): void
    {
        $admin = $this->admin();
        Livewire::test(AccountSettings::class)->fillForm([
            'password' => 'New-Password-2026!', 'passwordConfirmation' => 'New-Password-2026!', 'currentPassword' => 'Current-Password-2026!',
        ])->call('save')->assertHasNoFormErrors()->assertSet('data.password', null)->assertSet('data.currentPassword', null);
        $this->assertTrue(Hash::check('New-Password-2026!', $admin->fresh()->password));
        $this->assertFalse(Hash::check('Current-Password-2026!', $admin->fresh()->password));
        $this->get('/admin')->assertOk();
        Filament::auth()->logout();
        Livewire::test(Login::class)->fillForm(['email' => $admin->email, 'password' => 'Current-Password-2026!'])
            ->call('authenticate')->assertHasFormErrors(['email']);
        $this->assertGuest();
        Livewire::test(Login::class)->fillForm(['email' => $admin->email, 'password' => 'New-Password-2026!'])
            ->call('authenticate')->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($admin);
    }

    #[DataProvider('invalidPasswordChanges')]
    public function test_invalid_password_changes_are_rejected(string $password, ?string $current, string $confirmation, string $field): void
    {
        $admin = $this->admin();
        $hash = $admin->password;
        Livewire::test(AccountSettings::class)->fillForm(['password' => $password, 'passwordConfirmation' => $confirmation, 'currentPassword' => $current])
            ->call('save')->assertHasFormErrors([$field]);
        $this->assertSame($hash, $admin->fresh()->password);
    }

    public static function invalidPasswordChanges(): array
    {
        $new = 'New-Password-2026!';
        $current = 'Current-Password-2026!';

        return [
            'current missing' => [$new, null, $new, 'currentPassword'],
            'current incorrect' => [$new, 'Incorrect-Password!', $new, 'currentPassword'],
            'confirmation differs' => [$new, $current, 'Another-Password-2026!', 'password'],
            'short' => ['Short-1!', $current, 'Short-1!', 'password'],
            'no uppercase' => ['lowercase-password123!', $current, 'lowercase-password123!', 'password'],
            'no number' => ['Long-Strong-Password!', $current, 'Long-Strong-Password!', 'password'],
            'no symbol' => ['LongStrongPassword123', $current, 'LongStrongPassword123', 'password'],
            'multibyte limit' => [str_repeat('অ', 23).'Aa1!', $current, str_repeat('অ', 23).'Aa1!', 'password'],
        ];
    }

    public function test_invalid_duplicate_or_unverified_email_changes_are_rejected(): void
    {
        $admin = $this->admin();
        $originalEmail = $admin->email;
        User::factory()->create(['email' => 'taken@example.com']);
        foreach ([['bad-email', 'Current-Password-2026!', 'email'], ['taken@example.com', 'Current-Password-2026!', 'email'], ['changed@example.com', null, 'currentPassword']] as [$email, $password, $error]) {
            Livewire::test(AccountSettings::class)->fillForm(['email' => $email, 'currentPassword' => $password])
                ->call('save')->assertHasFormErrors([$error]);
            $this->assertSame($originalEmail, $admin->fresh()->email);
        }
    }

    public function test_revoked_admin_access_cannot_be_used_to_save_account_changes(): void
    {
        $admin = $this->admin();
        $originalName = $admin->name;
        $component = Livewire::test(AccountSettings::class)->fillForm(['name' => 'Unauthorized change']);
        $admin->forceFill(['is_admin' => false])->save();
        $component->call('save')->assertForbidden();
        $this->assertSame($originalName, $admin->fresh()->name);
    }

    public function test_sessions_with_the_old_password_hash_are_rejected_after_a_password_change(): void
    {
        $admin = $this->admin();
        $oldHash = $admin->password;
        Livewire::test(AccountSettings::class)->fillForm([
            'password' => 'New-Password-2026!', 'passwordConfirmation' => 'New-Password-2026!', 'currentPassword' => 'Current-Password-2026!',
        ])->call('save')->assertHasNoFormErrors();
        $this->withSession(['password_hash_web' => $oldHash])->get('/admin')->assertRedirect('/admin/login');
        $this->assertGuest();
    }
}
