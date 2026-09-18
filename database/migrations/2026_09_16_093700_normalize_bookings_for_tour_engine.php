<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normalize bookings into a tour-first engine with extension seams
     * (product_type discriminator, source channel, immutable price
     * snapshot, guest support, status history) without touching Taxi/Hotel.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Extension seam: which travel product this booking belongs to.
            // Only 'tour' exists today; future modules partition on this.
            $table->string('product_type', 20)->default('tour')->after('id');
            $table->string('source', 20)->default('website')->after('product_type');

            // Immutable pricing snapshot (server-computed, never from client).
            $table->string('currency', 3)->default('INR')->after('total_children');
            $table->decimal('base_price', 10, 2)->default(0)->after('currency');
            $table->decimal('subtotal', 10, 2)->default(0)->after('base_price');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('subtotal');
            $table->decimal('tax_amount', 10, 2)->default(0)->after('discount_amount');

            // Guest contact details.
            $table->string('country', 100)->nullable()->after('pickup_address');
            $table->text('special_requests')->nullable()->after('country');

            $table->index(['product_type', 'booking_status']);
            $table->index(['payment_status']);
            $table->index(['travel_date']);
        });

        // Map legacy payment values onto the new set before narrowing it.
        // ('paid' is valid in both sets and stays untouched.)
        DB::table('bookings')->where('payment_status', 'pending')->update(['payment_status' => 'unpaid']);
        DB::table('bookings')->where('payment_status', 'failed')->update(['payment_status' => 'unpaid']);

        // Backfill snapshots for pre-existing rows.
        DB::table('bookings')->update([
            'base_price' => DB::raw('total_amount'),
            'subtotal' => DB::raw('total_amount'),
        ]);

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->enum('booking_status', ['pending', 'confirmed', 'completed', 'cancelled'])->default('pending')->change();
            $table->enum('payment_status', ['unpaid', 'partially_paid', 'paid', 'refunded'])->default('unpaid')->change();
            // Nullable owner enables guest (and future API) bookings.
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('booking_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('payment_from', 20)->nullable();
            $table->string('payment_to', 20)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index('booking_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Best effort: downgrading with new statuses or guest rows present
     * requires resolving those rows first.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_status_histories');

        DB::table('bookings')->where('payment_status', 'unpaid')->update(['payment_status' => 'pending']);

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->enum('booking_status', ['confirmed', 'completed', 'cancelled'])->default('confirmed')->change();
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending')->change();
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->dropIndex(['product_type', 'booking_status']);
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['travel_date']);
            $table->dropColumn([
                'product_type', 'source', 'currency', 'base_price', 'subtotal',
                'discount_amount', 'tax_amount', 'country', 'special_requests',
            ]);
        });
    }
};
