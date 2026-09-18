<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 8: vendor payout destinations.
     *
     * One active row per vendor (unique vendor_profile_id); replacements
     * reset verification. Sensitive identifiers live in encrypted-cast
     * columns with separately persisted masked display values — masked
     * values are what every UI and withdrawal snapshot uses.
     */
    public function up(): void
    {
        Schema::create('vendor_payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_profile_id')->unique()->constrained('vendor_profiles')->restrictOnDelete();
            $table->string('method', 16);
            $table->string('account_holder_name');
            $table->string('bank_name')->nullable();
            $table->text('account_number')->nullable();
            $table->string('account_number_last4', 4)->nullable();
            $table->string('ifsc', 11)->nullable();
            $table->text('upi_id')->nullable();
            $table->string('upi_id_masked')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payout_accounts');
    }
};
