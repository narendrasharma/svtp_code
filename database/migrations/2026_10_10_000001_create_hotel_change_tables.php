<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_booking_cancellations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_booking_id')->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 120)->unique();
            $table->string('reason_code', 40)->default('customer_request');
            $table->text('note')->nullable();
            $table->string('currency', 3);
            $table->decimal('cancellation_fee', 12, 2)->default(0);
            $table->decimal('refundable_amount', 12, 2)->default(0);
            $table->json('policy_snapshot');
            $table->timestamp('cancelled_at');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('hotel_booking_id');
        });

        Schema::create('hotel_booking_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('hotel_booking_cancellation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('refund_number')->unique();
            $table->string('idempotency_key', 120)->unique();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('status', 20)->default('pending');
            $table->string('reason', 120);
            $table->string('payment_reference')->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index(['hotel_booking_id', 'status']);
        });

        Schema::create('hotel_booking_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_booking_id')->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 120)->unique();
            $table->date('old_check_in');
            $table->date('old_check_out');
            $table->date('new_check_in');
            $table->date('new_check_out');
            $table->decimal('old_total', 12, 2);
            $table->decimal('new_total', 12, 2);
            $table->decimal('difference', 12, 2);
            $table->string('currency', 3);
            $table->string('status', 20)->default('completed');
            $table->string('reason', 120)->nullable();
            $table->json('old_snapshot');
            $table->json('new_snapshot');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->index('hotel_booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_booking_changes');
        Schema::dropIfExists('hotel_booking_refunds');
        Schema::dropIfExists('hotel_booking_cancellations');
    }
};
