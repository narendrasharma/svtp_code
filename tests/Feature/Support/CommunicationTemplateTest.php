<?php

namespace Tests\Feature\Support;

use App\Models\CommunicationTemplate;
use App\Models\Setting;
use App\Models\User;
use App\Services\Comms\TemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CommunicationTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_whitelist_placeholders_render(): void
    {
        $service = app(TemplateService::class);

        $out = $service->renderText('Hello {{customer_name}}, booking {{booking_reference}} totals {{amount}}. Due {{amount_due}}.', [
            'customer_name' => 'Asha',
            'booking_reference' => 'BK-2026-000001',
            'amount' => '5000',
            'amount_due' => '2000',
        ]);

        $this->assertSame('Hello Asha, booking BK-2026-000001 totals 5000. Due 2000.', $out);
    }

    public function test_unknown_placeholder_left_intact_at_render(): void
    {
        $service = app(TemplateService::class);

        $out = $service->renderText('Hello {{customer_name}}, your {{ssn}} please.', ['customer_name' => 'Asha']);

        $this->assertSame('Hello Asha, your {{ssn}} please.', $out);
    }

    public function test_unknown_placeholder_rejected_at_save(): void
    {
        $this->actingAs($this->admin())->post(route('admin.templates.store'), [
            'key' => 'evil_template',
            'name' => 'Evil',
            'email_body' => 'Hello {{customer_name}}, {{drop_table}}',
        ])->assertInvalid('template');

        $this->assertDatabaseMissing('communication_templates', ['key' => 'evil_template']);
    }

    public function test_no_executable_template_injection(): void
    {
        $service = app(TemplateService::class);

        // Blade directives, PHP tags and object traversal stay literal.
        $out = $service->renderText('@foreach($x as $y) {{ $user->password }} {!! $html !!} <?php echo 1; ?>', [
            'customer_name' => 'Asha',
        ]);

        $this->assertStringContainsString('@foreach($x as $y)', $out);
        $this->assertStringContainsString('{{ $user->password }}', $out);
        $this->assertStringContainsString('{!! $html !!}', $out);
        $this->assertStringContainsString('<?php echo 1; ?>', $out);
    }

    public function test_branded_site_values_work(): void
    {
        Setting::setValue('site_name', 'Braj Yatra Test');

        $service = app(TemplateService::class);
        $template = CommunicationTemplate::where('key', 'quotation_sent')->firstOrFail();

        $subject = $service->renderText($template->email_subject, [
            'quotation_reference' => 'QT-2026-000001',
            'site_name' => Setting::getValue('site_name'),
        ]);

        $this->assertSame('Your quotation QT-2026-000001 from Braj Yatra Test', $subject);
    }

    public function test_channel_specific_bodies(): void
    {
        $template = CommunicationTemplate::where('key', 'payment_received')->firstOrFail();

        $this->assertNotEmpty($template->email_body);
        $this->assertNotEmpty($template->sms_body);
        $this->assertNotEmpty($template->whatsapp_body);
        $this->assertNotEmpty($template->in_app_body);
        $this->assertLessThanOrEqual(500, mb_strlen($template->sms_body));
    }

    public function test_template_management_requires_permission(): void
    {
        $ops = User::factory()->create(['role' => 'admin']);
        $ops->assignRole('operations-manager');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // operations-manager lacks communications.manage_templates.
        $this->actingAs($ops->fresh())->get(route('admin.templates.index'))->assertForbidden();
        $this->actingAs($ops->fresh())->post(route('admin.templates.store'), [
            'key' => 'nope',
            'name' => 'Nope',
        ])->assertForbidden();
    }
}
