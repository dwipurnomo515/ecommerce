<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_are_not_admins(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->isAdmin());
        $this->assertFalse(Gate::forUser($user)->allows('admin'));
    }

    public function test_admins_pass_the_admin_gate(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('admin'));
    }

    public function test_registering_with_an_is_admin_field_does_not_grant_admin(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Penyusup',
            'email' => 'penyusup@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'is_admin' => '1',
        ]);

        $user = User::where('email', 'penyusup@example.com')->firstOrFail();
        $this->assertFalse($user->isAdmin());
    }

    public function test_the_make_admin_command_grants_and_revokes_admin(): void
    {
        $user = User::factory()->create(['email' => 'budi@example.com']);

        $this->artisan('app:make-admin', ['email' => 'budi@example.com'])
            ->expectsOutputToContain('is now an admin')
            ->assertSuccessful();
        $this->assertTrue($user->fresh()?->isAdmin());

        $this->artisan('app:make-admin', ['email' => 'budi@example.com', '--revoke' => true])
            ->assertSuccessful();
        $this->assertFalse($user->fresh()?->isAdmin());
    }

    public function test_the_make_admin_command_fails_for_an_unknown_email(): void
    {
        $this->artisan('app:make-admin', ['email' => 'tidak-ada@example.com'])
            ->expectsOutputToContain('No user found')
            ->assertFailed();
    }
}
