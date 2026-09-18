<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer cancellation requests.
     *
     * A separate table (not booking columns) preserves the full request
     * history, supports admin approve/reject, and leaves room for future
     * refund and vendor workflows. Bookings themselves only ever change
     * status through the centralized booking service.
     */
    public function up(): void
    {
        Schema::create('booking_cancellation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 255)->nullable();
            $table->timestamps();
            $table->index(['booking_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_cancellation_requests');
    }
};
