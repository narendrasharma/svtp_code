<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Date-change workflow. The booking row is only rewritten after
        // the new date passes every availability rule; the old date and
        // both totals stay here forever. change_type is module-neutral
        // (tour date today; taxi pickup / hotel stay dates later).
        Schema::create('booking_reschedules', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('change_type', 30)->default('date_change');
            $table->date('old_travel_date');
            $table->date('new_travel_date');
            $table->decimal('old_total', 12, 2);
            $table->decimal('new_total', 12, 2);
            $table->decimal('price_difference', 12, 2)->default(0);
            $table->text('reason');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('approved');
            $table->timestamps();

            $table->index('booking_id');
        });

        // Operational notes. is_internal=true rows stay inside the admin
        // area; customer/vendor shapes must filter them out.
        Schema::create('booking_notes', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(true);
            $table->timestamps();

            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_notes');
        Schema::dropIfExists('booking_reschedules');
    }
};
