<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 7: vendor withdrawal requests (manual settlement, no gateway).
     *
     * Funds are held at request time via a ledger hold entry; rejected or
     * cancelled requests release the hold, paid requests convert the hold
     * into a settlement. Status transitions are guarded by WithdrawalStatus.
     */
    public function up(): void
    {
        Schema::create('vendor_withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_profile_id')->constrained('vendor_profiles')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('status', 32)->default('pending');
            $table->timestamp('requested_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('admin_note')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payout_reference')->nullable();
            $table->string('vendor_note')->nullable();
            $table->timestamps();

            $table->index(['vendor_profile_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_withdrawal_requests');
    }
};
