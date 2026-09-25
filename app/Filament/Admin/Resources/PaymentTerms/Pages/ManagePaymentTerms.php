<?php

namespace App\Filament\Admin\Resources\PaymentTerms\Pages;

use App\Filament\Admin\MasterData\ManageMasterData;
use App\Filament\Admin\Resources\PaymentTerms\PaymentTermResource;

class ManagePaymentTerms extends ManageMasterData
{
    protected static string $resource = PaymentTermResource::class;
}
