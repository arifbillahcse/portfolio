<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_available_and_dashboard_requires_authentication(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('Arif Portfolio');
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_public_registration_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
        $this->get('/admin/register')->assertNotFound();
    }

    public function test_regular_users_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_admins_can_access_dashboard_and_log_out(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->post('/admin/logout')->assertRedirect();
        $this->assertGuest();
    }

    public function test_admin_flag_is_not_mass_assignable_and_other_panels_are_denied(): void
    {
        $user = new User(['name' => 'Visitor', 'email' => 'visitor@example.com', 'is_admin' => true]);

        $this->assertFalse($user->is_admin);
        $user->is_admin = true;
        $this->assertFalse($user->canAccessPanel(Panel::make()->id('other')));
    }

    public function test_admin_can_sign_in_with_valid_credentials(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'password' => 'Test-Only-Password-2026!']);
        Filament::setCurrentPanel('admin');

        Livewire::test(Login::class)
            ->fillForm(['email' => $admin->email, 'password' => 'Test-Only-Password-2026!'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($admin);
    }

    public function test_invalid_password_does_not_authenticate(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Filament::setCurrentPanel('admin');

        Livewire::test(Login::class)
            ->fillForm(['email' => $admin->email, 'password' => 'incorrect-password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_regular_user_cannot_sign_in_to_admin_even_with_valid_password(): void
    {
        $user = User::factory()->create(['password' => 'Test-Only-Password-2026!']);
        Filament::setCurrentPanel('admin');

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'Test-Only-Password-2026!'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }
}
