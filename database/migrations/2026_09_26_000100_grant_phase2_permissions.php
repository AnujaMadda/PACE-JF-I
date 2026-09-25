<?php

use App\Domain\Identity\Actions\ProvisionEntityRoles;
use Illuminate\Database\Migrations\Migration;

/**
 * Data migration: give existing entities' default roles the Phase 2
 * permissions (master data, vendor bank details, user import).
 */
return new class extends Migration
{
    public function up(): void
    {
        app(ProvisionEntityRoles::class)->grantNewDefaults([
            'masterdata.view', 'masterdata.manage', 'masterdata.import', 'masterdata.export',
            'vendors.view_bank_details', 'users.import', 'admin.access',
        ]);
    }

    public function down(): void
    {
        // Permissions stay; removing them could undo admin decisions.
    }
};
