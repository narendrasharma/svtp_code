<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxi_cancellation_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('vendor_profile_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('trip_type', 30)->nullable();
            $table->string('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->dateTime('effective_from')->nullable();
            $table->dateTime('effective_until')->nullable();
            $table->unsignedInteger('free_cancel_before_minutes')->nullable();
            $table->string('fee_type', 20)->default('fixed');
            $table->decimal('fee_value', 12, 2)->default(0);
            $table->string('no_show_fee_type', 20)->default('fixed');
            $table->decimal('no_show_fee_value', 12, 2)->default(0);
            $table->decimal('minimum_fee', 12, 2)->default(0);
            $table->decimal('maximum_fee', 12, 2)->nullable();
            $table->timestamps();
            $table->index(['vendor_profile_id', 'is_active', 'effective_from'], 'taxi_cancel_policy_scope_idx');
        });
        Schema::create('taxi_booking_cancellations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('taxi_booking_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('vendor_profile_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('policy_id')->nullable()->constrained('taxi_cancellation_policies')->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requested_by_type', 20);
            $table->string('reason_code', 40);
            $table->string('reason_text', 500)->nullable();
            $table->string('status', 20);
            $table->string('currency', 3);
            $table->decimal('cancellation_fee', 12, 2);
            $table->decimal('refundable_amount', 12, 2);
            $table->json('calculation_snapshot');
            $table->timestamp('cancelled_at')->index();
            $table->timestamps();
        });
        Schema::create('taxi_refunds', function (Blueprint $table): void {
            $table->id();
            $table->string('refund_number', 40)->unique();
            $table->foreignId('taxi_booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_profile_id')->nullable()->constrained()->restrictOnDelete();
            $table->uuid('request_key');
            $table->string('currency', 3);
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('pending');
            $table->string('method', 30)->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('reason', 500);
            $table->json('calculation_snapshot');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->unique(['taxi_booking_id', 'request_key']);
            $table->index(['vendor_profile_id', 'status']);
        });
        Schema::create('taxi_refund_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('taxi_refund_id')->constrained()->restrictOnDelete();
            $table->foreignId('taxi_payment_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_reference', 100);
            $table->unique(['taxi_refund_id', 'taxi_payment_id']);
        });
        Schema::create('taxi_booking_reschedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('taxi_booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('old_pickup_at');
            $table->dateTime('old_return_at')->nullable();
            $table->dateTime('new_pickup_at');
            $table->dateTime('new_return_at')->nullable();
            $table->string('reason', 500);
            $table->json('snapshot');
            $table->timestamps();
        });
        foreach (['taxi.cancellations.view', 'taxi.cancellations.manage', 'taxi.refunds.view', 'taxi.refunds.manage', 'taxi.reschedule.manage'] as $name) {
            DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
            $permissionId = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
            foreach (DB::table('roles')->whereIn('name', ['administrator', 'super-admin'])->pluck('id') as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('taxi_refund_items');
        Schema::dropIfExists('taxi_refunds');
        Schema::dropIfExists('taxi_booking_reschedules');
        Schema::dropIfExists('taxi_booking_cancellations');
        Schema::dropIfExists('taxi_cancellation_policies');
    }
};
