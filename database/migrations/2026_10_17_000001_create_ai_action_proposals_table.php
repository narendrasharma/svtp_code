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
        Schema::create('ai_action_proposals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->string('tool_name', 80);
            $table->string('target_type', 40);
            $table->unsignedBigInteger('target_id');
            $table->string('target_label', 200);
            $table->string('field_label', 80);
            $table->string('summary', 500);
            $table->string('risk_level', 20);
            $table->string('status', 20)->default('pending');
            $table->json('validated_arguments');
            $table->json('before_snapshot');
            $table->json('proposed_changes');
            $table->json('result')->nullable();
            $table->string('failure_reason', 40)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'expires_at']);
            $table->index(['target_type', 'target_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_action_proposals');
    }
};
