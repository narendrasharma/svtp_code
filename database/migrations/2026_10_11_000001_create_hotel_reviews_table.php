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
        Schema::create('hotel_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_booking_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('overall_rating');
            $table->unsignedTinyInteger('cleanliness_rating');
            $table->unsignedTinyInteger('location_rating');
            $table->unsignedTinyInteger('service_rating');
            $table->unsignedTinyInteger('comfort_rating');
            $table->unsignedTinyInteger('value_rating');
            $table->string('title', 120)->nullable();
            $table->text('comment');
            $table->string('status', 20)->default('pending');
            $table->boolean('verified_stay')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->text('vendor_reply')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'status', 'published_at']);
            $table->index(['property_id', 'status', 'overall_rating']);
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('properties', function (Blueprint $table): void {
            $table->unsignedInteger('reviews_count')->default(0);
            $table->decimal('rating_average', 3, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotel_reviews');
        Schema::table('properties', function (Blueprint $table): void {
            $table->dropColumn(['reviews_count', 'rating_average']);
        });
    }
};
