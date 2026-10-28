<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreatePortfolioAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_admin_with_a_hashed_password(): void
    {
        $this->artisan('portfolio:admin', ['--name' => 'Portfolio Owner', '--email' => 'owner@example.com'])
            ->expectsQuestion('Password', 'Test-Only-Password-2026!')
            ->expectsQuestion('Confirm password', 'Test-Only-Password-2026!')
            ->assertSuccessful();

        $user = User::where('email', 'owner@example.com')->sole();
        $this->assertTrue($user->is_admin);
        $this->assertTrue(Hash::check('Test-Only-Password-2026!', $user->password));
        $this->assertNotSame('Test-Only-Password-2026!', $user->password);
    }

    public function test_weak_passwords_do_not_create_accounts(): void
    {
        $this->artisan('portfolio:admin', ['--name' => 'Owner', '--email' => 'owner@example.com'])
            ->expectsQuestion('Password', 'weak')
            ->expectsQuestion('Confirm password', 'weak')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_password_confirmation_must_match(): void
    {
        $this->artisan('portfolio:admin', ['--name' => 'Owner', '--email' => 'owner@example.com'])
            ->expectsQuestion('Password', 'Test-Only-Password-2026!')
            ->expectsQuestion('Confirm password', 'Different-Password-2026!')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_users_are_not_silently_promoted(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('portfolio:admin', ['--name' => 'Owner', '--email' => $user->email])
            ->assertFailed();

        $this->assertFalse($user->fresh()->is_admin);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_seed_does_not_create_default_accounts(): void
    {
        $this->seed();

        $this->assertDatabaseCount('users', 0);
    }
}
