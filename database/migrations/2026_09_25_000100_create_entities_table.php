<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('country');
            $table->char('base_currency', 3);
            $table->string('timezone', 64);
            $table->unsignedTinyInteger('fy_start_month');
            $table->string('request_prefix', 10);
            $table->json('allowed_email_domains');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('entity_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The operating entity a requester belongs to. Group users have none.
            $table->boolean('is_home')->default(false);
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['entity_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_user');
        Schema::dropIfExists('entities');
    }
};
