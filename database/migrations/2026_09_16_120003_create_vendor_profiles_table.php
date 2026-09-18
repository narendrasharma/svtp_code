<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('business_name');
            $table->string('entity_type')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country_code')->default('IN');
            $table->string('postcode')->nullable();
            $table->string('website')->nullable();
            $table->text('business_description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('vendor_verification_id')->nullable()->constrained()->nullOnDelete();
            $table->string('verification_status')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_profiles');
    }
};
