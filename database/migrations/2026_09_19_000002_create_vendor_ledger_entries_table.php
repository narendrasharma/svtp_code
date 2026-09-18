<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 7: immutable vendor financial ledger.
     *
     * Append-only rows; balances are always derived, never stored. The
     * unique reference column is the idempotency key for earning, reversal,
     * hold, release and settlement entries. Booking linkage is optional
     * context so future Taxi/Hotel earnings credit the same vendor ledger.
     * No backfill here — eligible historical earnings are credited by the
     * idempotent vendor-ledger:backfill command.
     */
    public function up(): void
    {
        Schema::create('vendor_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_profile_id')->constrained('vendor_profiles')->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('withdrawal_request_id')->nullable()->constrained('vendor_withdrawal_requests')->nullOnDelete();
            $table->string('type', 32);
            $table->string('direction', 16);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('reference')->unique();
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vendor_profile_id', 'type']);
            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_ledger_entries');
    }
};
