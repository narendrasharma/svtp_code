<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('tour_packages')->cascadeOnDelete();
            $table->string('booking_reference_id')->unique();
            $table->date('travel_date');
            $table->unsignedTinyInteger('total_adults')->default(1);
            $table->unsignedTinyInteger('total_children')->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->string('payment_gateway')->nullable();
            $table->string('payment_reference')->nullable();
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');
            $table->enum('booking_status', ['confirmed', 'completed', 'cancelled'])->default('confirmed');
            $table->string('qr_code_string')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
