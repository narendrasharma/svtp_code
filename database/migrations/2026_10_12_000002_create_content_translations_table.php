<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 13A: generic polymorphic content translations.
     *
     * One table serves Destination / Page today and Property / Tour /
     * Place / CMS content tomorrow without schema changes. Only
     * explicitly whitelisted fields per model may be stored (enforced
     * in App\Support\HasTranslations + controllers, not by DB).
     */
    public function up(): void
    {
        Schema::create('content_translations', function (Blueprint $table): void {
            $table->id();
            $table->string('translatable_type', 191);
            $table->unsignedBigInteger('translatable_id');
            $table->string('locale', 12);
            $table->string('field', 100);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->index(['translatable_type', 'translatable_id']);
            $table->index(['locale']);
            $table->unique(
                ['translatable_type', 'translatable_id', 'locale', 'field'],
                'content_translations_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_translations');
    }
};
