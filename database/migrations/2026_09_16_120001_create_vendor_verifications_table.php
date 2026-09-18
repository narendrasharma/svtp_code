<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('not_started');
            $table->string('country_code')->default('IN');
            $table->string('entity_type')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('vendor_application_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_verifications');
    }
};
