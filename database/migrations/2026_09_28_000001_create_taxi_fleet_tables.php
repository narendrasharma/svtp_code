<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_types', static function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
            $table->string('description', 500)->nullable();
            $table->string('icon', 60)->nullable();
            $table->string('image_path', 255)->nullable();
            $table->unsignedSmallInteger('passenger_capacity')->default(4);
            $table->unsignedSmallInteger('luggage_capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('vehicles', static function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('vendor_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('registration_number', 30);
            $table->string('make', 60)->nullable();
            $table->string('model', 60)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('color', 40)->nullable();
            $table->unsignedSmallInteger('passenger_capacity')->default(4);
            $table->unsignedSmallInteger('luggage_capacity')->nullable();
            $table->string('fuel_type', 20)->nullable();
            $table->string('transmission', 20)->nullable();
            $table->boolean('is_air_conditioned')->default(true);
            $table->string('status', 20)->default('available');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['vendor_profile_id', 'registration_number']);
            $table->index(['vendor_profile_id', 'status']);
            $table->index(['vehicle_type_id', 'is_active']);
        });

        Schema::create('vehicle_documents', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->string('document_number_masked', 60)->nullable();
            $table->string('original_filename', 255)->nullable();
            $table->string('storage_path', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'document_type']);
            $table->index(['status', 'expiry_date']);
        });

        Schema::create('vehicle_unavailable_periods', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->dateTime('from_at');
            $table->dateTime('to_at');
            $table->string('type', 20)->default('blocked');
            $table->string('reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vehicle_id', 'from_at', 'to_at']);
        });

        Schema::create('drivers', static function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('vendor_profile_id')->constrained()->cascadeOnDelete();
            // Nullable by design: vendors create drivers without login;
            // an invite flow links a User account later.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name', 80);
            $table->string('last_name', 80)->nullable();
            $table->string('phone', 30);
            $table->string('email', 150)->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact_name', 120)->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->date('joining_date')->nullable();
            $table->string('driver_type', 30)->nullable();
            $table->string('availability_status', 20)->default('offline');
            $table->string('employment_status', 20)->default('active');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vendor_profile_id', 'is_active']);
            $table->index(['vendor_profile_id', 'availability_status']);
            $table->index('phone');
        });

        Schema::create('driver_documents', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->string('document_number_masked', 60)->nullable();
            $table->string('original_filename', 255)->nullable();
            $table->string('storage_path', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'document_type']);
            $table->index(['status', 'expiry_date']);
        });

        Schema::create('driver_availabilities', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->date('date')->nullable();
            $table->dateTime('from_at')->nullable();
            $table->dateTime('to_at')->nullable();
            $table->string('status', 20)->default('unavailable');
            $table->string('reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['driver_id', 'date']);
            $table->index(['driver_id', 'from_at', 'to_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_availabilities');
        Schema::dropIfExists('driver_documents');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('vehicle_unavailable_periods');
        Schema::dropIfExists('vehicle_documents');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('vehicle_types');
    }
};
