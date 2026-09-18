<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Default sections in homepage order. Labels, descriptions and setting
     * defaults live in HomepageSectionService; only presentation state is
     * stored here so re-running stays idempotent for existing installs.
     *
     * @return array<int, string>
     */
    public static function defaultKeys(): array
    {
        return [
            'hero',
            'divider',
            'circuits',
            'shloka_ticker',
            'director_message',
            'featured_packages',
            'why_choose_us',
            'services',
            'trust_strip',
            'attractions',
            'best_time',
            'gallery',
            'testimonials',
            'blog',
            'faq',
            'contact',
            'trust_marquee',
        ];
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('homepage_sections', function (Blueprint $table) {
            $table->id();
            $table->string('section_key', 60)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });

        $now = now();
        foreach (self::defaultKeys() as $position => $key) {
            DB::table('homepage_sections')->updateOrInsert(
                ['section_key' => $key],
                ['is_active' => true, 'sort_order' => $position, 'updated_at' => $now, 'created_at' => $now]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('homepage_sections');
    }
};
