<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_invitations', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->string('email');
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'used_at']);
        });

        Schema::table('users', static function (Blueprint $table) {
            // Marketing consent is independent from transactional
            // preferences (notification_preferences). Campaigns read
            // these; booking/security mail never does.
            $table->boolean('marketing_email_opt_in')->default(true)->after('notification_preferences');
            $table->boolean('marketing_sms_opt_in')->default(true)->after('marketing_email_opt_in');
            $table->boolean('marketing_whatsapp_opt_in')->default(true)->after('marketing_sms_opt_in');
        });
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->dropColumn(['marketing_email_opt_in', 'marketing_sms_opt_in', 'marketing_whatsapp_opt_in']);
        });

        Schema::dropIfExists('account_invitations');
    }
};
