<?php

namespace App\Filament\Admin\Resources\ExchangeRates\Pages;

use App\Filament\Admin\MasterData\ManageMasterData;
use App\Filament\Admin\Resources\ExchangeRates\ExchangeRateResource;

class ManageExchangeRates extends ManageMasterData
{
    protected static string $resource = ExchangeRateResource::class;
}
