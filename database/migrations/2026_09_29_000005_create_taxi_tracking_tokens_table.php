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
        // Phase 12A.9: revocable customer tracking tokens. Only the
        // SHA-256 hash is stored — the raw token is shown once at
        // generation and never recoverable afterwards.
        Schema::create('taxi_tracking_tokens', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('taxi_booking_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->dateTime('last_accessed_at')->nullable();
            $table->string('source', 20)->default('manual');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['taxi_booking_id', 'revoked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taxi_tracking_tokens');
    }
};
