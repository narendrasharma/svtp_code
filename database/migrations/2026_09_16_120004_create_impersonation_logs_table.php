<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impersonation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('impersonated_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_logs');
    }
};
