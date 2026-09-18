<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_categories', static function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        foreach ([
            ['Booking', 'booking', 10],
            ['Payment', 'payment', 20],
            ['Cancellation', 'cancellation', 30],
            ['Refund', 'refund', 40],
            ['Vendor KYC', 'vendor-kyc', 50],
            ['Vendor Payout', 'vendor-payout', 60],
            ['Technical', 'technical', 70],
            ['General', 'general', 80],
        ] as [$name, $slug, $order]) {
            DB::table('support_categories')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'is_active' => true, 'sort_order' => $order, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        Schema::create('support_tickets', static function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vendor_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('support_categories')->nullOnDelete();
            $table->string('subject');
            $table->string('priority', 10)->default('normal');
            $table->string('status', 20)->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
            // Polymorphic overflow for refund / withdrawal / future
            // taxi-ride / hotel-reservation links. Core links stay real
            // foreign keys above so they remain queryable + constrained.
            $table->nullableMorphs('related');
            $table->dateTime('last_reply_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['requester_user_id', 'status']);
            $table->index('assigned_to');
        });

        Schema::create('support_ticket_messages', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal_note')->default(false);
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('support_ticket_attachments', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('support_ticket_messages')->cascadeOnDelete();
            $table->string('disk', 30)->default('support');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->boolean('is_internal')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_attachments');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('support_categories');
    }
};
