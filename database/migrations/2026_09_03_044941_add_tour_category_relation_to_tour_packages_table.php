<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tour_packages', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('category')
                ->constrained('tour_categories')
                ->nullOnDelete();
        });

        $categories = DB::table('tour_packages')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category');

        foreach ($categories as $legacyCategory) {
            $slug = Str::slug($legacyCategory);

            if ($slug === '') {
                continue;
            }

            $categoryId = DB::table('tour_categories')->where('slug', $slug)->value('id');

            if ($categoryId === null) {
                $categoryId = DB::table('tour_categories')->insertGetId([
                    'name' => Str::headline(str_replace('_', ' ', $legacyCategory)),
                    'slug' => $slug,
                    'is_active' => true,
                    'sort_order' => 0,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }

            DB::table('tour_packages')
                ->where('category', $legacyCategory)
                ->update(['category_id' => $categoryId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
