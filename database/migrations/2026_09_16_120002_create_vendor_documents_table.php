<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_verification_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->string('document_number_masked')->nullable();
            $table->string('original_filename');
            $table->string('storage_path');
            $table->string('mime_type');
            $table->unsignedInteger('file_size');
            $table->string('status')->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_verification_id', 'document_type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_documents');
    }
};
