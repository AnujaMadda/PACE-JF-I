<?php

namespace App\Filament\Admin\Resources\Activities\Tables;

use App\Domain\Audit\Models\Activity;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label(__('When'))->dateTime('d M Y, H:i:s')->sortable(),
                TextColumn::make('causer.name')->label(__('By'))->placeholder(__('System'))->searchable(),
                TextColumn::make('log_name')->label(__('Area'))->badge(),
                TextColumn::make('event')->label(__('Event'))->placeholder('—'),
                TextColumn::make('description')->label(__('Description'))->limit(60)->searchable(),
                TextColumn::make('subject_type')->label(__('Record'))
                    ->formatStateUsing(fn (?string $state, Activity $record) => $state ? class_basename($state).' #'.$record->subject_id : '—'),
                TextColumn::make('ip_address')->label(__('IP'))->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('log_name')->label(__('Area'))
                    ->options(fn () => Activity::query()->distinct()->orderBy('log_name')->pluck('log_name', 'log_name')->filter()->all()),
                SelectFilter::make('event')->label(__('Event'))
                    ->options(fn () => Activity::query()->distinct()->orderBy('event')->pluck('event', 'event')->filter()->all()),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label(__('From')),
                        DatePicker::make('until')->label(__('Until')),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $q, string $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
