<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 13D: extend the existing homepage configuration row and add
     * explicit manual selections. Legacy section rows remain untouched.
     */
    public function up(): void
    {
        Schema::table('homepage_sections', function (Blueprint $table): void {
            $table->string('section_type', 60)->nullable()->after('section_key');
            $table->string('source_mode', 20)->nullable()->after('sort_order');
            $table->unsignedTinyInteger('item_limit')->nullable()->after('source_mode');
            $table->index(['section_type', 'is_active', 'sort_order']);
        });

        Schema::create('homepage_section_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('homepage_section_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 40);
            $table->unsignedBigInteger('entity_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['homepage_section_id', 'entity_type', 'entity_id'], 'homepage_section_items_unique');
            $table->index(['homepage_section_id', 'sort_order']);
        });

        $now = now();
        $sections = [
            ['hero_search', 'hero_search', null, null, 10],
            ['featured_cities', 'featured_cities', 'automatic', 8, 20],
            ['featured_destinations', 'featured_destinations', 'automatic', 8, 30],
            ['featured_hotels', 'featured_hotels', 'automatic', 8, 40],
            ['top_rated_hotels', 'top_rated_hotels', 'automatic', 8, 50],
            ['featured_tours', 'featured_tours', 'automatic', 8, 60],
            ['featured_places', 'featured_places', 'automatic', 8, 70],
            ['custom_cta', 'custom_cta', null, null, 80],
        ];

        foreach ($sections as [$key, $type, $sourceMode, $itemLimit, $sortOrder]) {
            $existing = DB::table('homepage_sections')->where('section_key', $key)->first();

            if ($existing === null) {
                DB::table('homepage_sections')->insert([
                    'section_key' => $key,
                    'section_type' => $type,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                    'source_mode' => $sourceMode,
                    'item_limit' => $itemLimit,
                    'settings' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                continue;
            }

            DB::table('homepage_sections')->where('id', $existing->id)->update([
                'section_type' => $existing->section_type ?? $type,
                'source_mode' => $existing->source_mode ?? $sourceMode,
                'item_limit' => $existing->item_limit ?? $itemLimit,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_section_items');

        Schema::table('homepage_sections', function (Blueprint $table): void {
            $table->dropIndex(['section_type', 'is_active', 'sort_order']);
            $table->dropColumn(['section_type', 'source_mode', 'item_limit']);
        });
    }
};
