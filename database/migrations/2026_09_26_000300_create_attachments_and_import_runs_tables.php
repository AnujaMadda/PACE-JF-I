<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->restrictOnDelete();
            $table->morphs('attachable');
            $table->string('category', 32)->index();
            $table->string('original_name');
            $table->string('disk', 32);
            // Random name inside the entity's folder; never derived from the upload name.
            $table->string('path')->unique();
            $table->string('extension', 10);
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64)->index();
            $table->string('scan_status', 16)->default('pending');
            $table->timestamp('scanned_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->restrictOnDelete();
            $table->string('type', 64);
            $table->string('status', 16)->default('queued')->index();
            $table->string('original_name');
            $table->string('disk', 32);
            $table->string('path');
            $table->string('error_report_path')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->text('message')->nullable();
            $table->json('options')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['entity_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_runs');
        Schema::dropIfExists('attachments');
    }
};
