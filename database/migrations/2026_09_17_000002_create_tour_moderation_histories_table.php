<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_moderation_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_package_id')->constrained('tour_packages')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index('tour_package_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_moderation_histories');
    }
};
