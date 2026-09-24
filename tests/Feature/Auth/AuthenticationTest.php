<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'http://localhost/code');
        URL::forceRootUrl('http://localhost');
    }

    public function test_admin_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('login', absolute: false));

        $response->assertOk();
    }

    public function test_admin_login_alias_can_be_rendered(): void
    {
        $response = $this->get(route('admin.login', absolute: false));

        $response->assertOk();
    }

    public function test_public_login_route_is_not_exposed(): void
    {
        $response = $this->get('/login');

        $response->assertNotFound();
    }

    public function test_authenticated_admin_visiting_admin_root_is_redirected_to_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('login', absolute: false));

        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_admins_can_authenticate_and_are_redirected_to_the_admin_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->post(route('login.store', absolute: false), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_admin_can_access_the_admin_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.dashboard', absolute: false));

        $response->assertOk();
    }

    public function test_guests_cannot_access_the_admin_dashboard(): void
    {
        $response = $this->get(route('admin.dashboard', absolute: false));

        $response->assertRedirect(route('login', absolute: false));
    }

    public function test_non_admin_users_cannot_access_the_admin_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $response->assertForbidden();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store', absolute: false), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_non_admin_users_cannot_authenticate_through_the_admin_login(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->post(route('login.store', absolute: false), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_admins_can_logout(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->post(route('logout', absolute: false));

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_logged_out_admins_cannot_access_the_admin_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('logout', absolute: false));
        $response = $this->get(route('admin.dashboard', absolute: false));

        $this->assertGuest();
        $response->assertRedirect(route('login', absolute: false));
    }
}
