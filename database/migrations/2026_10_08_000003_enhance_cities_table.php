<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shared geography foundation (12B.4.1): reusable City identity.
     *
     * Additive only — ids, names and slugs are preserved. state_id
     * becomes nullable (countries without state usage, e.g. city-states,
     * must be representable) and every city gains an optional country
     * plus discovery metadata (featured/sort/image/coords/SEO).
     *
     * The state_id nullability change cannot use ->change() (no
     * doctrine/dbal in this product), so it is expressed per driver:
     * MODIFY on MySQL/MariaDB, table rebuild on SQLite (test only).
     */
    public function up(): void
    {
        $this->makeStateNullable();

        Schema::table('cities', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('state_id')->constrained()->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable()->after('slug');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->boolean('is_active')->default(true)->after('is_spiritual_hub');
            $table->boolean('is_featured')->default(false)->after('is_active');
            $table->unsignedInteger('sort_order')->default(0)->after('is_featured');
            $table->string('image')->nullable()->after('sort_order');
            $table->string('meta_title')->nullable()->after('image');
            $table->string('meta_description')->nullable()->after('meta_title');

            $table->index(['country_id', 'is_active']);
            $table->index(['state_id', 'is_active']);
            $table->index(['is_featured', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropIndex(['country_id', 'is_active']);
            $table->dropIndex(['state_id', 'is_active']);
            $table->dropIndex(['is_featured', 'sort_order']);
            $table->dropConstrainedForeignId('country_id');
            $table->dropColumn([
                'latitude', 'longitude', 'is_active', 'is_featured',
                'sort_order', 'image', 'meta_title', 'meta_description',
            ]);
        });

        // state_id nullability is intentionally NOT reverted: rows created
        // while nullable may exist, and re-adding NOT NULL could fail.
    }

    protected function makeStateNullable(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->rebuildCitiesTableForSqlite();

            return;
        }

        DB::statement('ALTER TABLE `cities` MODIFY `state_id` BIGINT UNSIGNED NULL');
    }

    /**
     * SQLite (test databases only) cannot MODIFY a column, so rebuild the
     * table with state_id nullable. Runs before any new columns exist, so
     * the original schema is replicated exactly apart from nullability.
     */
    protected function rebuildCitiesTableForSqlite(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            Schema::create('cities_rebuild', function (Blueprint $table) {
                $table->id();
                $table->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('slug')->unique();
                $table->boolean('is_spiritual_hub')->default(false);
                $table->timestamps();
            });

            DB::statement('INSERT INTO cities_rebuild (id, state_id, name, slug, is_spiritual_hub, created_at, updated_at) SELECT id, state_id, name, slug, is_spiritual_hub, created_at, updated_at FROM cities');
            Schema::drop('cities');
            Schema::rename('cities_rebuild', 'cities');
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};
