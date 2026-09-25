<?php

namespace App\Filament\Admin\Resources\LoginEvents\Pages;

use App\Filament\Admin\Resources\LoginEvents\LoginEventResource;
use Filament\Resources\Pages\ManageRecords;

class ManageLoginEvents extends ManageRecords
{
    protected static string $resource = LoginEventResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
