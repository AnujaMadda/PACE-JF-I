<?php

namespace App\Filament\Admin\Resources\Activities\Schemas;

use App\Domain\Audit\Models\Activity;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ActivityInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Event'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('created_at')->label(__('When'))->dateTime('d M Y, H:i:s'),
                        TextEntry::make('causer.name')->label(__('By'))->placeholder(__('System')),
                        TextEntry::make('onBehalfOf.name')->label(__('On behalf of'))->placeholder('—'),
                        TextEntry::make('log_name')->label(__('Area'))->badge(),
                        TextEntry::make('event')->label(__('Event'))->placeholder('—'),
                        TextEntry::make('description')->label(__('Description')),
                        TextEntry::make('subject_type')->label(__('Record'))
                            ->formatStateUsing(fn (?string $state, Activity $record) => $state ? class_basename($state).' #'.$record->subject_id : '—'),
                        TextEntry::make('entity.code')->label(__('Entity'))->placeholder(__('Group')),
                        TextEntry::make('ip_address')->label(__('IP address'))->placeholder('—'),
                        TextEntry::make('user_agent')->label(__('Browser'))->placeholder('—')->columnSpan(2),
                        TextEntry::make('request_id')->label(__('Request id'))->placeholder('—'),
                    ]),
                Section::make(__('Changes'))
                    ->schema([
                        ViewEntry::make('attribute_changes')
                            ->hiddenLabel()
                            ->view('filament.audit.changes'),
                    ]),
            ]);
    }
}
