<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * `php artisan migrate --seed` — base configuration everywhere; demo data locally only.
     */
    public function run(): void
    {
        $this->call(KenyaEntitySeeder::class);

        if (! app()->isProduction()) {
            $this->call(DemoUsersSeeder::class);
            $this->call(KenyaMasterDataSeeder::class);
        }
    }
}
