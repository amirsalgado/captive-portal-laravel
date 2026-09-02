<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_non_admin_users_are_forbidden(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertForbidden();
    }

    public function test_admin_users_can_view_the_dashboard(): void
    {
        $admin = User::factory()->create();
        $adminRole = Role::create(['name' => 'admin']);
        $admin->roles()->attach($adminRole);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
    }
}
