<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_follow_ups', static function (Blueprint $table) {
            $table->dateTime('reminder_sent_at')->nullable()->after('completed_at');
            $table->dateTime('overdue_reminder_sent_at')->nullable()->after('reminder_sent_at');
        });

        Schema::table('quotations', static function (Blueprint $table) {
            $table->dateTime('expiry_reminder_sent_at')->nullable()->after('converted_booking_id');
        });

        Schema::table('bookings', static function (Blueprint $table) {
            $table->date('payment_due_date')->nullable()->after('payment_status');
            $table->dateTime('last_payment_reminder_at')->nullable()->after('payment_due_date');
            $table->dateTime('last_travel_reminder_at')->nullable()->after('last_payment_reminder_at');
            $table->dateTime('last_vendor_travel_reminder_at')->nullable()->after('last_travel_reminder_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', static function (Blueprint $table) {
            $table->dropColumn(['payment_due_date', 'last_payment_reminder_at', 'last_travel_reminder_at', 'last_vendor_travel_reminder_at']);
        });

        Schema::table('quotations', static function (Blueprint $table) {
            $table->dropColumn('expiry_reminder_sent_at');
        });

        Schema::table('lead_follow_ups', static function (Blueprint $table) {
            $table->dropColumn(['reminder_sent_at', 'overdue_reminder_sent_at']);
        });
    }
};
