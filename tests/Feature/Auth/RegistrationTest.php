<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_register_as_customers_and_reach_their_dashboard(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'phone' => '9876543210',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('account.dashboard', absolute: false));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'role' => 'customer',
            'phone' => '9876543210',
        ]);
    }

    public function test_registration_cannot_self_assign_privileged_roles(): void
    {
        foreach (['admin', 'vendor'] as $role) {
            $email = "sneaky-{$role}@example.com";

            $this->post(route('register.store'), [
                'name' => 'Sneaky User',
                'email' => $email,
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => $role,
                'is_admin' => true,
            ])->assertRedirect(route('account.dashboard', absolute: false));

            $this->assertDatabaseHas('users', ['email' => $email, 'role' => 'customer']);
            auth()->logout();
        }

        $this->assertDatabaseMissing('users', ['role' => 'admin']);
    }
}
