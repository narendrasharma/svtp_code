<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', static function (Blueprint $table) {
            $table->foreignId('quotation_id')->nullable()->after('coupon_discount_value')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('quotation_revision_number')->nullable()->after('quotation_id');
            $table->decimal('quoted_total_amount', 12, 2)->nullable()->after('quotation_revision_number');
        });

        Schema::table('booking_status_histories', static function (Blueprint $table) {
            $table->boolean('is_internal')->default(false)->after('note');
        });

        Schema::table('users', static function (Blueprint $table) {
            $table->string('source', 20)->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('booking_status_histories', static function (Blueprint $table) {
            $table->dropColumn('is_internal');
        });

        Schema::table('bookings', static function (Blueprint $table) {
            $table->dropConstrainedForeignId('quotation_id');
            $table->dropColumn(['quotation_revision_number', 'quoted_total_amount']);
        });
    }
};
