<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxi_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('taxi_booking_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('vendor_profile_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('overall_rating');
            $table->unsignedTinyInteger('driver_rating')->nullable();
            $table->unsignedTinyInteger('vehicle_rating')->nullable();
            $table->unsignedTinyInteger('service_rating')->nullable();
            $table->unsignedTinyInteger('punctuality_rating')->nullable();
            $table->unsignedTinyInteger('cleanliness_rating')->nullable();
            $table->string('comment', 1000)->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('submitted_at')->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('moderation_note', 500)->nullable();
            $table->string('vendor_reply', 1000)->nullable();
            $table->timestamp('vendor_replied_at')->nullable();
            $table->timestamp('flagged_at')->nullable();
            $table->foreignId('flagged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('flag_reason', 30)->nullable();
            $table->string('flag_note', 500)->nullable();
            $table->timestamps();

            $table->index(['vendor_profile_id', 'status', 'submitted_at'], 'taxi_reviews_vendor_status_idx');
            $table->index(['driver_id', 'status', 'submitted_at'], 'taxi_reviews_driver_status_idx');
            $table->index(['status', 'overall_rating'], 'taxi_reviews_status_rating_idx');
        });

        foreach (['taxi.reviews.view', 'taxi.reviews.moderate', 'taxi.reviews.reply'] as $name) {
            DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
            $permissionId = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
            foreach (DB::table('roles')->whereIn('name', ['administrator', 'super-admin'])->pluck('id') as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('taxi_reviews');
    }
};
