<?php

namespace App\Filament\Admin\Resources\Activities;

use App\Domain\Audit\Models\Activity;
use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Models\User;
use App\Filament\Admin\Resources\Activities\Pages\ListActivities;
use App\Filament\Admin\Resources\Activities\Pages\ViewActivity;
use App\Filament\Admin\Resources\Activities\Schemas\ActivityInfolist;
use App\Filament\Admin\Resources\Activities\Tables\ActivitiesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Read-only audit log for the current entity. Group Super Admins also see
 * group-level events (no entity), such as system setting changes.
 */
class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $slug = 'audit-log';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Audit log';

    protected static ?string $modelLabel = 'audit entry';

    protected static ?string $pluralModelLabel = 'audit log';

    public static function getEloquentQuery(): Builder
    {
        $entityId = app(CurrentEntity::class)->id();

        /** @var User|null $actor */
        $actor = auth()->user();

        return parent::getEloquentQuery()
            ->with('causer')
            ->where(function (Builder $q) use ($entityId, $actor): void {
                $q->where('entity_id', $entityId ?? 0);

                if ($actor?->isGroupSuperAdmin()) {
                    $q->orWhereNull('entity_id');
                }
            });
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return ActivityInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivitiesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
            'view' => ViewActivity::route('/{record}'),
        ];
    }
}
