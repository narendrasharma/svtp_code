<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 8: link reversal ledger entries to their refund record so each
     * processed refund maps to exactly one reversal (idempotent retries
     * resolve through the refund id).
     */
    public function up(): void
    {
        Schema::table('vendor_ledger_entries', function (Blueprint $table) {
            $table->foreignId('booking_refund_id')->nullable()->after('withdrawal_request_id')->constrained('booking_refunds')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_ledger_entries', function (Blueprint $table) {
            $table->dropForeign(['booking_refund_id']);
            $table->dropColumn('booking_refund_id');
        });
    }
};
