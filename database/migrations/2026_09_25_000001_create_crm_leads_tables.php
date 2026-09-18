<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_sources', static function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        foreach ([
            ['Website', 'website', 10],
            ['Phone', 'phone', 20],
            ['Walk-in', 'walk-in', 30],
            ['WhatsApp', 'whatsapp', 40],
            ['Google Ads', 'google-ads', 50],
            ['Facebook / Instagram', 'facebook-instagram', 60],
            ['Referral', 'referral', 70],
            ['Third-party portal', 'third-party-portal', 80],
            ['Vendor', 'vendor', 90],
            ['Other', 'other', 100],
        ] as [$name, $slug, $order]) {
            DB::table('lead_sources')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'is_active' => true, 'sort_order' => $order, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        Schema::create('leads', static function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('enquiry_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('lead_sources')->nullOnDelete();
            $table->string('name');
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->string('service_type', 20)->default('tour');
            $table->string('product_title')->nullable();
            $table->string('destination')->nullable();
            $table->date('travel_start_date')->nullable();
            $table->date('travel_end_date')->nullable();
            $table->unsignedSmallInteger('adults')->nullable();
            $table->unsignedSmallInteger('children')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->string('priority', 10)->default('normal');
            $table->string('status', 20)->default('new');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->dateTime('next_follow_up_at')->nullable();
            $table->dateTime('last_contacted_at')->nullable();
            $table->string('lost_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('assigned_to');
            $table->index('next_follow_up_at');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
        Schema::dropIfExists('lead_sources');
    }
};
