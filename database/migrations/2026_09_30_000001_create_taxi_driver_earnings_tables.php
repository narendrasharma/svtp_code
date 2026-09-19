<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Driver earnings & payouts ledger (Phase 12A.10, forward-only).
     *
     * One earning per booking (unique taxi_booking_id) owned by the final
     * driver; explicit payout allocations with a unique earning_id so an
     * earning can never be double-allocated across batches.
     */
    public function up(): void
    {
        Schema::create('taxi_driver_compensation_plans', static function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('vendor_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('currency', 3)->default('INR');
            $table->string('calculation_type', 30)->default('fixed');
            $table->decimal('fixed_amount', 12, 2)->default(0);
            $table->decimal('percentage', 7, 2)->default(0);
            $table->string('percentage_base', 10)->default('total');
            $table->decimal('per_km_amount', 12, 2)->default(0);
            $table->decimal('per_hour_amount', 12, 2)->default(0);
            $table->decimal('minimum_earning', 12, 2)->default(0);
            $table->boolean('allowance_passthrough')->default(false);
            $table->decimal('no_show_amount', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('effective_from')->nullable();
            $table->dateTime('effective_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vendor_profile_id', 'is_active']);
            $table->index(['driver_id', 'is_active']);
            $table->index(['currency', 'is_active']);
        });

        Schema::create('taxi_driver_earnings', static function (Blueprint $table) {
            $table->id();
            $table->string('earning_number', 40)->unique();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('taxi_booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('taxi_assignment_id')->nullable()->constrained('taxi_assignments')->nullOnDelete();
            $table->string('currency', 3)->default('INR');
            $table->string('calculation_type', 30)->default('fixed');
            $table->json('calculation_snapshot')->nullable();
            $table->decimal('gross_earning', 12, 2)->default(0);
            $table->decimal('adjustments_total', 12, 2)->default(0);
            $table->decimal('net_earning', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->dateTime('earned_at');
            $table->dateTime('payable_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Idempotency: exactly one earning per booking; the final
            // driver's row wins, previous assignees get nothing.
            $table->unique('taxi_booking_id');
            $table->index(['driver_id', 'status']);
            $table->index(['vendor_profile_id', 'status']);
            $table->index(['status', 'payable_at']);
            $table->index(['currency', 'status']);
            $table->index('earned_at');
        });

        Schema::create('taxi_driver_earning_adjustments', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('earning_id')->constrained('taxi_driver_earnings')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('kind', 30)->default('misc');
            $table->string('reason', 500);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('earning_id');
        });

        Schema::create('taxi_driver_payouts', static function (Blueprint $table) {
            $table->id();
            $table->string('payout_number', 40)->unique();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('currency', 3)->default('INR');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->string('payment_method', 20)->default('bank_transfer');
            $table->string('payment_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'status']);
            $table->index(['vendor_profile_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['currency', 'status']);
        });

        Schema::create('taxi_driver_payout_items', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_id')->constrained('taxi_driver_payouts')->cascadeOnDelete();
            $table->foreignId('earning_id')->constrained('taxi_driver_earnings')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            // An earning can only ever sit in one batch at a time.
            $table->unique('earning_id');
            $table->index('payout_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxi_driver_payout_items');
        Schema::dropIfExists('taxi_driver_payouts');
        Schema::dropIfExists('taxi_driver_earning_adjustments');
        Schema::dropIfExists('taxi_driver_earnings');
        Schema::dropIfExists('taxi_driver_compensation_plans');
    }
};
