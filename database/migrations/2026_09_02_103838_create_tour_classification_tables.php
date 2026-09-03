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
        Schema::create('destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('destination_tour_package', function (Blueprint $table) {
            $table->foreignId('destination_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tour_package_id')->constrained()->cascadeOnDelete();
            $table->primary(['destination_id', 'tour_package_id']);
        });

        Schema::create('place_tour_package', function (Blueprint $table) {
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tour_package_id')->constrained()->cascadeOnDelete();
            $table->primary(['place_id', 'tour_package_id']);
        });

        Schema::create('tag_tour_package', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tour_package_id')->constrained()->cascadeOnDelete();
            $table->primary(['tag_id', 'tour_package_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tag_tour_package');
        Schema::dropIfExists('place_tour_package');
        Schema::dropIfExists('destination_tour_package');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('places');
        Schema::dropIfExists('destinations');
    }
};
