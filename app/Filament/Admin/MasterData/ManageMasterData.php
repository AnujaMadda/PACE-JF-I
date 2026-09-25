<?php

namespace App\Filament\Admin\MasterData;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

/**
 * List page for master data: create in a modal, plus Excel import, export
 * and template buttons.
 */
abstract class ManageMasterData extends ManageRecords
{
    protected function getHeaderActions(): array
    {
        /** @var class-string<MasterDataResource> $resource */
        $resource = static::getResource();
        $type = $resource::importType();

        return [
            CreateAction::make()->using(fn (array $data) => $resource::createRecord($data)),
            ...($type !== null ? ExcelActions::for($type) : []),
        ];
    }
}
