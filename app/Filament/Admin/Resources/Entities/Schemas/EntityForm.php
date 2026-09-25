<?php

namespace App\Filament\Admin\Resources\Entities\Schemas;

use DateTimeZone;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EntityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Entity'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label(__('Code'))
                            ->required()
                            ->regex('/^[A-Z]{2,10}$/')
                            ->helperText(__('2–10 capital letters, e.g. KE. Cannot be changed later.'))
                            ->unique(ignoreRecord: true)
                            ->disabledOn('edit'),
                        TextInput::make('name')->label(__('Name'))->required()->maxLength(255),
                        TextInput::make('country')->label(__('Country'))->required()->maxLength(255),
                        TextInput::make('base_currency')
                            ->label(__('Base currency'))
                            ->required()
                            ->regex('/^[A-Z]{3}$/')
                            ->helperText(__('ISO 4217 code, e.g. KES. Cannot be changed once created.'))
                            ->disabledOn('edit'),
                        Select::make('timezone')
                            ->label(__('Timezone'))
                            ->options(fn () => array_combine(DateTimeZone::listIdentifiers(), DateTimeZone::listIdentifiers()))
                            ->searchable()
                            ->required(),
                        Select::make('fy_start_month')
                            ->label(__('Financial year starts in'))
                            ->options(collect(range(1, 12))->mapWithKeys(fn (int $m) => [$m => now()->startOfYear()->month($m)->format('F')])->all())
                            ->required(),
                        TextInput::make('request_prefix')
                            ->label(__('Request number prefix'))
                            ->required()
                            ->regex('/^[A-Z0-9]{1,10}$/')
                            ->helperText(__('Used in request numbers, e.g. KE-CPX-FY27-00001.')),
                        Toggle::make('is_active')->label(__('Active'))->default(true),
                    ]),
                Section::make(__('Sign-in'))
                    ->schema([
                        TagsInput::make('allowed_email_domains')
                            ->label(__('Allowed company email domains'))
                            ->placeholder('jfi.lk')
                            ->required()
                            ->nestedRecursiveRules(['regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i'])
                            ->helperText(__('Only people with an email on these domains can activate an account for this entity.')),
                    ]),
            ]);
    }
}
