<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->string('provider', 40)->nullable();
            $table->string('model', 120)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('ai_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('role', 12);
            $table->text('content');
            $table->timestamps();
            $table->index(['ai_conversation_id', 'id']);
        });

        Schema::create('ai_tool_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('tool_name', 80);
            $table->boolean('succeeded');
            $table->unsignedInteger('duration_ms');
            $table->unsignedInteger('result_count')->nullable();
            $table->timestamp('created_at');
            $table->index(['ai_conversation_id', 'created_at']);
        });

        Schema::create('ai_knowledge_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 200);
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->longText('content');
            $table->string('status', 20)->default('inactive');
            $table->string('visibility', 20)->default('internal');
            $table->string('locale', 12)->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->timestamp('last_indexed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['source_type', 'source_id']);
            $table->index(['status', 'visibility', 'locale']);
        });

        Schema::create('ai_knowledge_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_knowledge_document_id')->constrained('ai_knowledge_documents')->cascadeOnDelete();
            $table->unsignedSmallInteger('chunk_index');
            $table->text('content');
            $table->longText('embedding');
            $table->string('embedding_provider', 40);
            $table->string('embedding_model', 120);
            $table->char('content_hash', 64);
            $table->timestamps();
            $table->unique(['ai_knowledge_document_id', 'chunk_index']);
            $table->index(['embedding_provider', 'embedding_model']);
        });

        if (Schema::hasTable('permissions')) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $permission = Permission::firstOrCreate(['name' => 'ai.knowledge.manage', 'guard_name' => 'web']);
            Role::whereIn('name', ['super-admin', 'administrator'])->where('guard_name', 'web')
                ->get()->each(fn (Role $role) => $role->givePermissionTo($permission));
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_knowledge_chunks');
        Schema::dropIfExists('ai_knowledge_documents');
        Schema::dropIfExists('ai_tool_events');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
