<?php

namespace App\Filament\Admin\Resources\Departments\Pages;

use App\Filament\Admin\MasterData\ManageMasterData;
use App\Filament\Admin\Resources\Departments\DepartmentResource;

class ManageDepartments extends ManageMasterData
{
    protected static string $resource = DepartmentResource::class;
}
