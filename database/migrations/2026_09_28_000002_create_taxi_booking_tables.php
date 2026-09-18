<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxi_bookings', static function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('customer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vendor_profile_id')->nullable()->constrained('vendor_profiles')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();

            $table->string('trip_type', 20)->default('one_way');

            $table->dateTime('pickup_at');
            $table->string('pickup_address', 500);
            $table->decimal('pickup_lat', 10, 7)->nullable();
            $table->decimal('pickup_lng', 10, 7)->nullable();

            $table->string('drop_address', 500);
            $table->decimal('drop_lat', 10, 7)->nullable();
            $table->decimal('drop_lng', 10, 7)->nullable();

            // Airport-transfer context (no flight tracking in 12A.1).
            $table->string('airport_direction', 20)->nullable();
            $table->string('flight_number', 20)->nullable();
            $table->string('airline', 80)->nullable();
            $table->string('terminal', 40)->nullable();

            $table->unsignedSmallInteger('passenger_count')->default(1);
            $table->unsignedSmallInteger('luggage_count')->nullable();

            $table->foreignId('vehicle_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('assigned_driver_id')->nullable()->constrained('drivers')->nullOnDelete();

            $table->string('customer_name', 150);
            $table->string('customer_phone', 30);
            $table->string('customer_email', 150)->nullable();
            $table->text('special_instructions')->nullable();

            $table->string('source', 20)->default('admin');
            $table->string('status', 30)->default('draft');
            $table->string('payment_status', 20)->default('unpaid');

            $table->string('currency', 3)->default('INR');
            $table->decimal('base_amount', 12, 2)->default(0);
            $table->decimal('extra_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);

            $table->decimal('quoted_distance_km', 8, 2)->nullable();
            $table->unsignedInteger('quoted_duration_minutes')->nullable();

            $table->date('payment_due_date')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('last_customer_reminder_at')->nullable();
            $table->dateTime('last_vendor_reminder_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vendor_profile_id', 'status']);
            $table->index(['status', 'pickup_at']);
            $table->index(['customer_user_id', 'pickup_at']);
            $table->index(['assigned_driver_id', 'status']);
        });

        Schema::create('taxi_booking_stops', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('taxi_booking_id')->constrained()->cascadeOnDelete();
            $table->string('stop_type', 20)->default('intermediate');
            $table->string('address', 500);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('notes', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['taxi_booking_id', 'sort_order']);
        });

        Schema::create('taxi_booking_status_histories', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('taxi_booking_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index('taxi_booking_id');
        });

        Schema::create('taxi_assignments', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('taxi_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('assigned_at');
            $table->dateTime('unassigned_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['taxi_booking_id', 'assigned_at']);
            $table->index(['driver_id', 'assigned_at']);
        });

        // Taxi money trail (decision B): separate append-only table mirroring
        // the tour BookingPayment pattern. No shared-polymorphic refactor of
        // working tour finance in this phase; unification deferred.
        Schema::create('taxi_payments', static function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('taxi_booking_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('payment_method', 20)->default('cash');
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_reference', 100)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['taxi_booking_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxi_payments');
        Schema::dropIfExists('taxi_assignments');
        Schema::dropIfExists('taxi_booking_status_histories');
        Schema::dropIfExists('taxi_booking_stops');
        Schema::dropIfExists('taxi_bookings');
    }
};
