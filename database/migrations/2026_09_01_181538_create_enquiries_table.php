<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('enquiry_type');
            $table->string('status')->default('new');
            $table->string('full_name');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('pickup_drop')->nullable();
            $table->string('hotel_category')->nullable();
            $table->unsignedSmallInteger('adults')->nullable();
            $table->unsignedSmallInteger('children')->nullable();
            $table->date('arrival_date')->nullable();
            $table->date('departure_date')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['enquiry_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};
