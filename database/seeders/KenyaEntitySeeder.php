<?php

namespace Database\Seeders;

use App\Domain\Core\Models\Entity;
use App\Domain\Identity\Actions\ProvisionEntityRoles;
use Illuminate\Database\Seeder;

/**
 * The first operating entity and its default roles. Safe to re-run. UAE and
 * Bangladesh are added later from the admin panel, not from code.
 */
class KenyaEntitySeeder extends Seeder
{
    public function run(ProvisionEntityRoles $provision): void
    {
        $kenya = Entity::query()->firstOrCreate(['code' => 'KE'], [
            'name' => 'JF&I Packaging Kenya',
            'country' => 'Kenya',
            'base_currency' => 'KES',
            'timezone' => 'Africa/Nairobi',
            'fy_start_month' => 4,
            'request_prefix' => 'KE',
            'allowed_email_domains' => ['jfi.lk'],
            'is_active' => true,
        ]);

        $provision->handle($kenya);
    }
}
