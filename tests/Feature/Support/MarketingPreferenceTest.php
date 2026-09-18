<?php

namespace Tests\Feature\Support;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MarketingPreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    public function test_customer_updates_marketing_preferences(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->patch(route('profile.update'), [
            'name' => $customer->name,
            'email' => $customer->email,
            'marketing_email_opt_in' => false,
            'marketing_sms_opt_in' => false,
            'marketing_whatsapp_opt_in' => true,
        ])->assertRedirect();

        $customer->refresh();
        $this->assertFalse((bool) $customer->marketing_email_opt_in);
        $this->assertFalse((bool) $customer->marketing_sms_opt_in);
        $this->assertTrue((bool) $customer->marketing_whatsapp_opt_in);
    }

    public function test_signed_unsubscribe_works_without_login(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'marketing_email_opt_in' => true,
            'marketing_sms_opt_in' => true,
        ]);

        $url = URL::signedRoute('unsubscribe.show', ['user' => $customer->id]);

        $this->get($url)->assertOk();

        $this->post(URL::signedRoute('unsubscribe.store', ['user' => $customer->id]))->assertRedirect();

        $customer->refresh();
        $this->assertFalse((bool) $customer->marketing_email_opt_in);
        $this->assertFalse((bool) $customer->marketing_sms_opt_in);
        $this->assertFalse((bool) $customer->marketing_whatsapp_opt_in);
    }

    public function test_invalid_signature_blocked(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->get("/unsubscribe/{$customer->id}")->assertForbidden();
        $this->post("/unsubscribe/{$customer->id}")->assertForbidden();

        $this->assertTrue((bool) $customer->refresh()->marketing_email_opt_in);
    }

    public function test_only_marketing_preference_updated(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'name' => 'Keep Me',
            'marketing_email_opt_in' => true,
        ]);

        $this->post(URL::signedRoute('unsubscribe.store', ['user' => $customer->id]));

        $customer->refresh();
        $this->assertSame('Keep Me', $customer->name);
        $this->assertFalse((bool) $customer->marketing_email_opt_in);
        // Transactional preference untouched.
        $this->assertNull($customer->notification_preferences);
    }
}
