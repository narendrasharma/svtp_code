<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 8: manual accounting refund records.
     *
     * Each processed refund owns exactly one vendor reversal ledger entry.
     * vendor_reversal_amount snapshots the prorated vendor share so history
     * never needs recomputation. No backfill: legacy Phase 7 full reversals
     * (ledger-only) are supported gracefully by BookingRefundService.
     */
    public function up(): void
    {
        Schema::create('booking_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('status', 32)->default('processed');
            $table->string('reason');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->string('reference')->unique()->nullable();
            $table->string('external_reference')->nullable();
            $table->decimal('vendor_reversal_amount', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_refunds');
    }
};
