<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_templates', static function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('email_subject')->nullable();
            $table->text('email_body')->nullable();
            $table->string('sms_body', 500)->nullable();
            $table->text('whatsapp_body')->nullable();
            $table->string('in_app_title')->nullable();
            $table->text('in_app_body')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        foreach ($this->defaults() as $row) {
            DB::table('communication_templates')->updateOrInsert(
                ['key' => $row['key']],
                $row + ['updated_at' => now(), 'created_at' => now()]
            );
        }

        Schema::create('communication_logs', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_type', 20)->default('customer');
            $table->string('channel', 20);
            $table->string('template_key')->nullable();
            $table->string('event', 60)->nullable();
            // Polymorphic parent: lead / quotation / booking / ticket /
            // campaign / payment... keeps one log instead of five.
            $table->nullableMorphs('related');
            // Masked by writers (j***@example.com, ***1234) — never raw
            // PII beyond what the channel itself needs at send time.
            $table->string('destination_masked')->nullable();
            $table->string('status', 20)->default('sent');
            $table->string('provider', 60)->nullable();
            $table->string('error', 500)->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['recipient_user_id', 'created_at']);
            $table->index(['channel', 'created_at']);
            $table->index(['event', 'created_at']);
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function defaults(): array
    {
        return [
            [
                'key' => 'quotation_sent',
                'name' => 'Quotation sent',
                'email_subject' => 'Your quotation {{quotation_reference}} from {{site_name}}',
                'email_body' => "Hello {{customer_name}},\n\nYour quotation {{quotation_reference}} for {{amount}} is ready and valid until {{valid_until}}.\n\nView it here: {{secure_url}}\n\n{{site_name}}",
                'sms_body' => 'Your quotation {{quotation_reference}} ({{amount}}) is ready: {{secure_url}}',
                'whatsapp_body' => "Hello {{customer_name}},\nyour quotation {{quotation_reference}} is ready.\nView: {{secure_url}}",
                'in_app_title' => 'Quotation {{quotation_reference}} ready',
                'in_app_body' => 'Your quotation totaling {{amount}} is ready to view.',
                'is_active' => true,
            ],
            [
                'key' => 'quotation_follow_up',
                'name' => 'Quotation follow-up',
                'email_subject' => 'Still interested? Quotation {{quotation_reference}}',
                'email_body' => "Hello {{customer_name}},\n\nJust checking in on quotation {{quotation_reference}} ({{amount}}), valid until {{valid_until}}.\n\nView it here: {{secure_url}}\n\n{{site_name}}",
                'sms_body' => 'Reminder: quotation {{quotation_reference}} ({{amount}}) expires {{valid_until}}: {{secure_url}}',
                'whatsapp_body' => "Hello {{customer_name}},\na quick reminder about quotation {{quotation_reference}}.\nView: {{secure_url}}",
                'in_app_title' => 'Quotation {{quotation_reference}} awaiting you',
                'in_app_body' => 'Your quotation totaling {{amount}} is still open.',
                'is_active' => true,
            ],
            [
                'key' => 'booking_confirmation',
                'name' => 'Booking confirmation',
                'email_subject' => 'Booking confirmed: {{booking_reference}}',
                'email_body' => "Hello {{customer_name}},\n\nYour booking {{booking_reference}} for {{travel_date}} is confirmed. Total {{amount}}, balance due {{amount_due}}.\n\n{{site_name}}",
                'sms_body' => 'Booking {{booking_reference}} confirmed for {{travel_date}}. Due {{amount_due}}. {{site_name}}',
                'whatsapp_body' => "Hello {{customer_name}},\nbooking {{booking_reference}} is confirmed for {{travel_date}}.\nBalance due: {{amount_due}}",
                'in_app_title' => 'Booking {{booking_reference}} confirmed',
                'in_app_body' => 'Your booking for {{travel_date}} is confirmed. Balance due {{amount_due}}.',
                'is_active' => true,
            ],
            [
                'key' => 'payment_received',
                'name' => 'Payment received',
                'email_subject' => 'Payment received: {{payment_reference}}',
                'email_body' => "Hello {{customer_name}},\n\nWe received {{amount}} against booking {{booking_reference}} ({{payment_reference}}). Balance due {{amount_due}}.\n\nView receipt: {{secure_url}}\n\n{{site_name}}",
                'sms_body' => 'Received {{amount}} for {{booking_reference}}. Due {{amount_due}}. Receipt: {{secure_url}}',
                'whatsapp_body' => "Hello {{customer_name}},\nreceived {{amount}} for booking {{booking_reference}}.\nBalance due: {{amount_due}}\nReceipt: {{secure_url}}",
                'in_app_title' => 'Payment {{payment_reference}} received',
                'in_app_body' => '{{amount}} received. Balance due {{amount_due}}.',
                'is_active' => true,
            ],
            [
                'key' => 'payment_due',
                'name' => 'Payment due reminder',
                'email_subject' => 'Balance due on booking {{booking_reference}}',
                'email_body' => "Hello {{customer_name}},\n\nA balance of {{amount_due}} is due on booking {{booking_reference}} (travel {{travel_date}}).\n\n{{site_name}}",
                'sms_body' => 'Reminder: {{amount_due}} due on booking {{booking_reference}}. {{site_name}}',
                'whatsapp_body' => "Hello {{customer_name}},\na balance of {{amount_due}} is due on booking {{booking_reference}}.",
                'in_app_title' => 'Balance due: {{booking_reference}}',
                'in_app_body' => 'A balance of {{amount_due}} is due on your booking.',
                'is_active' => true,
            ],
            [
                'key' => 'invoice_ready',
                'name' => 'Invoice ready',
                'email_subject' => 'Invoice for booking {{booking_reference}}',
                'email_body' => "Hello {{customer_name}},\n\nYour invoice for booking {{booking_reference}} is ready: {{secure_url}}\n\n{{site_name}}",
                'sms_body' => 'Invoice for {{booking_reference}}: {{secure_url}}',
                'whatsapp_body' => "Hello {{customer_name}},\nyour invoice for booking {{booking_reference}} is ready: {{secure_url}}",
                'in_app_title' => 'Invoice ready',
                'in_app_body' => 'Your invoice for booking {{booking_reference}} is ready.',
                'is_active' => true,
            ],
            [
                'key' => 'refund_processed',
                'name' => 'Refund processed',
                'email_subject' => 'Refund processed for booking {{booking_reference}}',
                'email_body' => "Hello {{customer_name}},\n\nA refund of {{amount}} was processed for booking {{booking_reference}}.\n\n{{site_name}}",
                'sms_body' => 'Refund of {{amount}} processed for {{booking_reference}}. {{site_name}}',
                'whatsapp_body' => "Hello {{customer_name}},\na refund of {{amount}} was processed for booking {{booking_reference}}.",
                'in_app_title' => 'Refund processed',
                'in_app_body' => 'A refund of {{amount}} was processed.',
                'is_active' => true,
            ],
            [
                'key' => 'support_reply',
                'name' => 'Support reply',
                'email_subject' => 'Update on support ticket {{ticket_reference}}',
                'email_body' => "Hello {{customer_name}},\n\nOur team replied to your ticket {{ticket_reference}} ({{ticket_subject}}).\n\n{{site_name}}",
                'sms_body' => 'Update on ticket {{ticket_reference}}: please check your account. {{site_name}}',
                'whatsapp_body' => "Hello {{customer_name}},\nwe replied to your support ticket {{ticket_reference}}.",
                'in_app_title' => 'Support ticket updated',
                'in_app_body' => 'Our team replied to ticket {{ticket_reference}}.',
                'is_active' => true,
            ],
            [
                'key' => 'account_invitation',
                'name' => 'Account invitation',
                'email_subject' => 'Claim your {{site_name}} account',
                'email_body' => "Hello {{customer_name}},\n\nAn account was created for you. Set your password here (link expires in 72 hours): {{secure_url}}\n\nIf you did not expect this, ignore this email.\n\n{{site_name}}",
                'sms_body' => 'Claim your {{site_name}} account: {{secure_url}}',
                'whatsapp_body' => "Hello {{customer_name}},\nclaim your account here: {{secure_url}}",
                'in_app_title' => 'Account invitation',
                'in_app_body' => 'Claim your account using the emailed link.',
                'is_active' => true,
            ],
        ];
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_logs');
        Schema::dropIfExists('communication_templates');
    }
};
