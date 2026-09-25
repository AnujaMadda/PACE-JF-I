<?php

use App\Domain\Core\Money\CurrencyList;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reference data: the ISO 4217 currencies PACE starts with. Group Super
 * Admins can add more in the admin panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (CurrencyList::iso() as $code => [$name, $decimals]) {
            DB::table('currencies')->insertOrIgnore([
                'code' => $code, 'name' => $name, 'decimals' => $decimals, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Reference data stays.
    }
};
