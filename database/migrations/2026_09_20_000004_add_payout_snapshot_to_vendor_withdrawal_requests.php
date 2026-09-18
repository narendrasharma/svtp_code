<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 8: historical payout destination snapshot on withdrawals.
     *
     * Only masked values are stored — never decrypted secrets — so later
     * payout-account changes never rewrite a request's history. Pre-Phase-8
     * rows keep nulls and render as unspecified.
     */
    public function up(): void
    {
        Schema::table('vendor_withdrawal_requests', function (Blueprint $table) {
            $table->foreignId('payout_account_id')->nullable()->after('vendor_profile_id')->constrained('vendor_payout_accounts')->nullOnDelete();
            $table->string('payout_method', 16)->nullable()->after('payout_account_id');
            $table->string('payout_destination_masked')->nullable()->after('payout_method');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_withdrawal_requests', function (Blueprint $table) {
            $table->dropForeign(['payout_account_id']);
            $table->dropColumn(['payout_account_id', 'payout_method', 'payout_destination_masked']);
        });
    }
};
