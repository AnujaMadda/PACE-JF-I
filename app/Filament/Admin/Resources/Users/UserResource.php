<?php

namespace App\Filament\Admin\Resources\Users;

use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Filament\Admin\Resources\Users\RelationManagers\DelegationsRelationManager;
use App\Filament\Admin\Resources\Users\RelationManagers\LoginEventsRelationManager;
use App\Filament\Admin\Resources\Users\Schemas\UserForm;
use App\Filament\Admin\Resources\Users\Tables\UsersTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Access';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Users with access to the current entity, plus self-registrations from its
     * email domains awaiting approval. Group Super Admins see everyone.
     * Records outside this query resolve to 404.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $entity = app(CurrentEntity::class)->get();

        /** @var User|null $actor */
        $actor = auth()->user();

        if ($actor?->isGroupSuperAdmin()) {
            return $query;
        }

        if ($entity === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($entity): void {
            $q->whereHas('entities', fn (Builder $e) => $e->whereKey($entity->getKey()))
                ->orWhere(function (Builder $pending) use ($entity): void {
                    $pending->where('status', UserStatus::PendingApproval)
                        ->whereDoesntHave('entities')
                        ->where(function (Builder $domains) use ($entity): void {
                            foreach ($entity->allowed_email_domains as $domain) {
                                $domains->orWhere('email', 'like', '%@'.str_replace(['%', '_'], ['\%', '\_'], $domain));
                            }
                        });
                });
        });
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DelegationsRelationManager::class,
            LoginEventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
