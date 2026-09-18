<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', static function (Blueprint $table) {
            $table->id();
            // Revisions of one commercial offer share a reference; the
            // (reference, revision_number) pair is the unique identity.
            $table->string('reference');
            $table->unsignedSmallInteger('revision_number')->default(1);
            $table->foreignId('root_quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('service_type', 20)->default('tour');
            $table->string('status', 20)->default('draft');
            $table->string('currency', 3)->default('INR');
            $table->date('valid_until')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->text('terms')->nullable();
            $table->text('internal_note')->nullable();
            $table->text('customer_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('public_token', 64)->unique();
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->dateTime('converted_at')->nullable();
            $table->foreignId('converted_booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->timestamps();

            $table->unique(['reference', 'revision_number']);
            $table->index(['status', 'created_at']);
            $table->index('lead_id');
            $table->index('customer_user_id');
        });

        Schema::create('quotation_items', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            // Generic lines: tour | transfer | guide | meal | stay |
            // activity | manual ... product_id links the source row when
            // there is one; description always snapshots display text.
            $table->string('item_type', 20)->default('manual');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('quotation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
