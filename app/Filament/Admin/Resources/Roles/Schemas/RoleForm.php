<?php

namespace App\Filament\Admin\Resources\Roles\Schemas;

use App\Domain\Core\Support\CurrentEntity;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Spatie\Permission\Models\Permission;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        $entityId = app(CurrentEntity::class)->id();

        /** @var array<string, string> $catalogue */
        $catalogue = config('pace.permissions', []);

        return $schema
            ->components([
                Section::make(__('Role'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('team_id', $entityId)->where('guard_name', 'web'))
                            ->helperText(__('Workflow steps are assigned to roles, so rename with care once workflows exist.')),
                    ]),
                Section::make(__('Permissions'))
                    ->description(__('What people with this role can open and do on screens. Approval steps are configured in the workflow designer.'))
                    ->schema([
                        CheckboxList::make('permissions')
                            ->label('')
                            ->relationship('permissions', 'name')
                            ->getOptionLabelFromRecordUsing(fn (Permission $record) => $catalogue[$record->name] ?? $record->name)
                            ->descriptions(fn () => Permission::query()->pluck('name', 'id')->map(fn (string $n) => Str::before($n, '.'))->all())
                            ->columns(2)
                            ->bulkToggleable(),
                    ]),
            ]);
    }
}
