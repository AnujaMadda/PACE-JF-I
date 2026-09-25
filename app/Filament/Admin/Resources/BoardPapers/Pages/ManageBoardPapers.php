<?php

namespace App\Filament\Admin\Resources\BoardPapers\Pages;

use App\Filament\Admin\MasterData\ManageMasterData;
use App\Filament\Admin\Resources\BoardPapers\BoardPaperResource;

class ManageBoardPapers extends ManageMasterData
{
    protected static string $resource = BoardPaperResource::class;
}
