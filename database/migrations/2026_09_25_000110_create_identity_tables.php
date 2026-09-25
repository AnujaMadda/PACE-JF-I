<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('password');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });

        // Append-only: the application never updates or deletes rows here.
        Schema::create('login_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->foreignId('entity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 32);
            $table->string('reason', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['entity_id', 'created_at']);
            $table->index(['email', 'created_at']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            // NULL = group default; a value = override for that entity.
            // RESTRICT: MySQL forbids cascading FKs on the base column of a stored generated column.
            $table->foreignId('entity_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('entity_scope')->storedAs('IFNULL(entity_id, 0)');
            $table->string('key', 128);
            $table->json('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['entity_scope', 'key']);
        });

        Schema::create('delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delegate_id')->constrained('users')->cascadeOnDelete();
            // NULL = applies in every entity the delegator has access to.
            $table->foreignId('entity_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delegations');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('login_events');
        Schema::dropIfExists('password_histories');
    }
};
