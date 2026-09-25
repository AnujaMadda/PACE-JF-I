<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('designation')->nullable();
            // Free text until Phase 2 introduces the per-entity departments master.
            $table->string('department')->nullable();
            $table->foreignId('line_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('invited')->index();
            $table->boolean('is_group_super_admin')->default(false);
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->unsignedSmallInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->boolean('locked_by_admin')->default(false);
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('invitation_sent_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            // Prepared for Microsoft Entra ID single sign-on.
            $table->string('auth_provider', 32)->default('local');
            $table->string('external_id')->nullable();
            $table->timestamps();

            $table->unique(['auth_provider', 'external_id']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
