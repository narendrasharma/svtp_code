<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', static function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('name');
            $table->string('subject');
            $table->text('content');
            $table->string('channel', 20)->default('email');
            $table->string('status', 20)->default('draft');
            $table->string('audience_type', 30)->default('selected');
            $table->json('audience_filter')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('campaign_deliveries', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('status', 20)->default('pending');
            $table->dateTime('sent_at')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();

            // One delivery row per recipient+channel: re-running a send
            // can never duplicate.
            $table->unique(['campaign_id', 'user_id', 'channel']);
            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_deliveries');
        Schema::dropIfExists('campaigns');
    }
};
