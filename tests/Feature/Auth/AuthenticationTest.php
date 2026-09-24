<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
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
        $response = $this->get(route('admin.entry', absolute: false));

        $response->assertOk();
    }

    public function test_admin_login_alias_can_be_rendered(): void
    {
        $response = $this->get(route('admin.login', absolute: false));

        $response->assertOk();
    }

    public function test_public_login_page_renders_with_registration_and_password_recovery(): void
    {
        $response = $this->get(route('login', absolute: false));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('adminPortal', false)
            ->where('canRegister', true)
            ->where('canResetPassword', true)
            ->etc());
    }

    public function test_public_login_preserves_hotel_checkout_intended_url(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $checkout = '/hotels/test-property/book?check_in=2026-10-01&check_out=2026-10-02';

        $response = $this->withSession(['url.intended' => 'http://localhost'.$checkout])
            ->post(route('login.public.store', absolute: false), [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('http://localhost'.$checkout);
        $this->get(route('account.dashboard', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.id', $user->id)
                ->etc());
    }

    public function test_public_login_redirects_customer_vendor_and_admin_to_their_portals(): void
    {
        foreach (['customer' => 'account.dashboard', 'vendor' => 'vendor.dashboard', 'admin' => 'admin.dashboard'] as $role => $destination) {
            $user = User::factory()->create(['role' => $role]);

            $response = $this->post(route('login.public.store', absolute: false), [
                'email' => $user->email,
                'password' => 'password',
            ]);

            $this->assertAuthenticatedAs($user);
            $response->assertRedirect(route($destination, absolute: false));
            auth()->logout();
            $this->app['session.store']->flush();
        }
    }

    public function test_admin_dashboard_redirects_guests_to_admin_login(): void
    {
        $this->get(route('admin.dashboard', absolute: false))
            ->assertRedirect(route('admin.login', absolute: false));
    }

    public function test_authenticated_admin_visiting_admin_root_is_redirected_to_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.entry', absolute: false));

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

        $response->assertRedirect(route('admin.login', absolute: false));
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
        $response->assertRedirect(route('admin.login', absolute: false));
    }
}
