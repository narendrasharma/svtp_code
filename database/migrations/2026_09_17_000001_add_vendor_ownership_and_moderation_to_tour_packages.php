<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_packages', function (Blueprint $table) {
            $table->foreignId('vendor_profile_id')->nullable()->after('city_id')->constrained('vendor_profiles')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->after('vendor_profile_id')->constrained('users')->nullOnDelete();
            $table->string('moderation_status')->default('approved')->after('is_active');
            $table->timestamp('submitted_at')->nullable()->after('moderation_status');
            $table->foreignId('reviewed_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_note')->nullable()->after('reviewed_at');
            $table->index('vendor_profile_id');
            $table->index('moderation_status');
        });

        // Migrate existing tours: admin-created, approved
        DB::table('tour_packages')->update([
            'moderation_status' => 'approved',
        ]);
    }

    public function down(): void
    {
        Schema::table('tour_packages', function (Blueprint $table) {
            $table->dropForeign(['vendor_profile_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['vendor_profile_id', 'created_by', 'moderation_status', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_note']);
        });
    }
};
