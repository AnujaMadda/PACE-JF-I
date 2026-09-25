<?php

namespace App\Filament\Admin\Resources\InternalOrders\Pages;

use App\Filament\Admin\MasterData\ManageMasterData;
use App\Filament\Admin\Resources\InternalOrders\InternalOrderResource;

class ManageInternalOrders extends ManageMasterData
{
    protected static string $resource = InternalOrderResource::class;
}
