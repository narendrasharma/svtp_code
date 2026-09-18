<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Manual/partial payment collections. Append-only money trail:
        // rows are never edited or deleted (corrections go through
        // refunds or a later reversal mechanism). Restrict booking
        // deletion while payments exist, mirroring booking_refunds.
        Schema::create('booking_payments', static function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('payment_method', 20)->default('cash');
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_reference')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['booking_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_payments');
    }
};
