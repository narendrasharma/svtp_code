<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Phase 11: public vendor storefront presentation fields.
     *
     * Split: legal/verification data stays in existing columns; these new
     * columns are presentation-only and safe to expose publicly. Private
     * phone/email/address/postcode are NEVER used for the storefront —
     * vendors opt in via public_phone/public_email instead.
     */
    public function up(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            $table->string('slug', 255)->nullable()->unique()->after('business_name');
            $table->string('logo_path', 500)->nullable()->after('business_description');
            $table->string('cover_path', 500)->nullable()->after('logo_path');
            $table->text('public_description')->nullable()->after('cover_path');
            $table->string('public_phone', 30)->nullable()->after('public_description');
            $table->string('public_email', 255)->nullable()->after('public_phone');
            $table->json('social_links')->nullable()->after('public_email');
            $table->boolean('storefront_enabled')->default(true)->after('social_links');
        });

        // Backfill stable unique slugs: slug(business_name)-{id}.
        DB::table('vendor_profiles')->orderBy('id')->chunkById(200, function ($profiles): void {
            foreach ($profiles as $profile) {
                $base = Str::slug((string) ($profile->business_name ?: 'vendor'));
                $base = $base !== '' ? $base : 'vendor';
                DB::table('vendor_profiles')->where('id', $profile->id)->update([
                    'slug' => $base.'-'.$profile->id,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn([
                'slug',
                'logo_path',
                'cover_path',
                'public_description',
                'public_phone',
                'public_email',
                'social_links',
                'storefront_enabled',
            ]);
        });
    }
};
