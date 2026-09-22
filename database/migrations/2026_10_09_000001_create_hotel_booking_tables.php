<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_bookings', function (Blueprint $table): void {
            $table->id();
            $table->string('booking_number', 40)->unique();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('property_name_snapshot', 150);
            $table->string('vendor_name_snapshot', 150)->nullable();
            $table->string('status', 30)->default('confirmed');
            $table->string('payment_status', 30)->default('unpaid');
            $table->string('currency', 3);
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedSmallInteger('nights');
            $table->unsignedSmallInteger('rooms_count');
            $table->unsignedSmallInteger('adults');
            $table->unsignedSmallInteger('children')->default(0);
            $table->string('guest_name', 150);
            $table->string('guest_email', 150);
            $table->string('guest_phone', 40);
            $table->text('special_requests')->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('taxes', 12, 2)->default(0);
            $table->decimal('fees', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->json('pricing_snapshot');
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamp('booked_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['vendor_profile_id', 'status']);
            $table->index(['property_id', 'check_in', 'check_out']);
            $table->index(['status', 'payment_status']);
        });

        Schema::create('hotel_booking_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->nullable()->constrained('hotel_room_types')->nullOnDelete();
            $table->foreignId('rate_plan_id')->nullable()->constrained('hotel_rate_plans')->nullOnDelete();
            $table->string('room_type_name_snapshot', 150);
            $table->string('rate_plan_name_snapshot', 150);
            $table->string('meal_plan_snapshot', 40)->nullable();
            $table->string('cancellation_mode_snapshot', 40)->nullable();
            $table->unsignedSmallInteger('quantity');
            $table->unsignedSmallInteger('adults');
            $table->unsignedSmallInteger('children')->default(0);
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedSmallInteger('nights');
            $table->string('currency', 3);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('taxes', 12, 2)->default(0);
            $table->decimal('fees', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->json('pricing_snapshot');
            $table->string('status', 30)->default('confirmed');
            $table->timestamps();

            $table->index(['hotel_booking_id', 'room_type_id']);
            $table->index(['room_type_id', 'check_in', 'check_out']);
        });

        Schema::create('hotel_reservation_nights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_booking_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->nullable()->constrained('hotel_room_types')->nullOnDelete();
            $table->date('stay_date');
            $table->unsignedSmallInteger('quantity');
            $table->string('status', 30)->default('confirmed');
            $table->timestamps();

            $table->unique(['hotel_booking_item_id', 'stay_date']);
            $table->index(['room_type_id', 'stay_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_reservation_nights');
        Schema::dropIfExists('hotel_booking_items');
        Schema::dropIfExists('hotel_bookings');
    }
};
