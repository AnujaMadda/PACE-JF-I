<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-entity master data. Every table carries entity_id with codes unique per
 * entity. Rows are deactivated (is_active), never deleted, so foreign keys
 * RESTRICT deletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->char('code', 3)->primary();
            $table->string('name');
            $table->unsignedTinyInteger('decimals')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('entity_currency', function (Blueprint $table) {
            $table->foreignId('entity_id')->constrained()->restrictOnDelete();
            $table->char('currency_code', 3);
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->timestamps();

            $table->primary(['entity_id', 'currency_code']);
        });

        Schema::create('departments', function (Blueprint $table) {
            $this->masterColumns($table);
            $table->foreignId('head_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('cost_centres', function (Blueprint $table) {
            $this->masterColumns($table, effectiveDates: true);
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('profit_centres', function (Blueprint $table) {
            $this->masterColumns($table, effectiveDates: true);
        });

        Schema::create('gl_accounts', function (Blueprint $table) {
            $this->masterColumns($table);
            $table->string('type', 32)->index();
        });

        Schema::create('internal_orders', function (Blueprint $table) {
            $this->masterColumns($table, effectiveDates: true);
            $table->foreignId('cost_centre_id')->nullable()->constrained()->restrictOnDelete();
        });

        Schema::create('payment_terms', function (Blueprint $table) {
            $this->masterColumns($table);
            $table->unsignedSmallInteger('days')->default(0);
        });

        Schema::create('vendors', function (Blueprint $table) {
            $this->masterColumns($table);
            $table->string('tax_number', 64)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 64)->nullable();
            $table->text('address')->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreignId('payment_term_id')->nullable()->constrained()->restrictOnDelete();
            // Bank details: encrypted at rest (text holds the ciphertext).
            $table->text('bank_name')->nullable();
            $table->text('bank_branch')->nullable();
            $table->text('bank_account_name')->nullable();
            $table->text('bank_account_number')->nullable();
            $table->text('bank_swift_code')->nullable();
            $table->text('bank_iban')->nullable();
        });

        Schema::create('budget_codes', function (Blueprint $table) {
            $this->masterColumns($table);
            $table->foreignId('gl_account_id')->nullable()->constrained()->restrictOnDelete();
        });

        Schema::create('capex_categories', function (Blueprint $table) {
            $this->masterColumns($table);
            // Overrides the entity's capex.minimum_quotations setting when set.
            $table->unsignedTinyInteger('minimum_quotations')->nullable();
        });

        Schema::create('board_papers', function (Blueprint $table) {
            $this->masterColumns($table);
            $table->date('paper_date');
            $table->decimal('approved_amount', 18, 2);
            $table->char('currency_code', 3);
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained()->restrictOnDelete();
            $table->char('from_currency', 3);
            $table->char('to_currency', 3);
            $table->foreign('from_currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('to_currency')->references('code')->on('currencies')->restrictOnDelete();
            // 1 unit of from_currency = rate units of to_currency.
            $table->decimal('rate', 18, 6);
            $table->date('effective_from');
            $table->string('source')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['entity_id', 'from_currency', 'to_currency', 'effective_from'], 'exchange_rates_unique_day');
            $table->index(['entity_id', 'from_currency', 'to_currency', 'effective_from'], 'exchange_rates_lookup');
        });
    }

    private function masterColumns(Blueprint $table, bool $effectiveDates = false): void
    {
        $table->id();
        $table->foreignId('entity_id')->constrained()->restrictOnDelete();
        $table->string('code', 64);
        $table->string('name');
        $table->text('description')->nullable();
        $table->boolean('is_active')->default(true);

        if ($effectiveDates) {
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
        }

        $table->timestamps();

        $table->unique(['entity_id', 'code']);
        $table->index(['entity_id', 'is_active']);
    }

    public function down(): void
    {
        foreach (['exchange_rates', 'board_papers', 'capex_categories', 'budget_codes', 'vendors', 'payment_terms',
            'internal_orders', 'gl_accounts', 'profit_centres', 'cost_centres', 'departments', 'entity_currency', 'currencies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
