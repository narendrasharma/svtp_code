<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_follow_ups', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('due_at');
            $table->string('type', 20)->default('call');
            $table->string('status', 20)->default('pending');
            $table->text('note')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lead_id', 'status']);
            $table->index(['status', 'due_at']);
            $table->index('assigned_to');
        });

        Schema::create('lead_timeline_entries', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('event', 60);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('lead_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_timeline_entries');
        Schema::dropIfExists('lead_follow_ups');
    }
};
