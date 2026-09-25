<?php

namespace App\Filament\Admin\Resources\Users\Actions;

use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Actions\ApproveRegistration;
use App\Domain\Identity\Actions\DeactivateUser;
use App\Domain\Identity\Actions\ForceLogout;
use App\Domain\Identity\Actions\IssueActivationLink;
use App\Domain\Identity\Actions\LockUser;
use App\Domain\Identity\Actions\ReactivateUser;
use App\Domain\Identity\Actions\RevokeEntityAccess;
use App\Domain\Identity\Actions\SendPasswordResetLink;
use App\Domain\Identity\Actions\UnlockUser;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Spatie\Permission\Models\Role;

/**
 * Account lifecycle actions for the user table and edit page. Each action
 * re-checks the policy for the record it runs on and delegates the work to
 * an Identity action class, which writes the audit entry.
 */
class UserLifecycleActions
{
    /**
     * @return list<Action>
     */
    public static function all(): array
    {
        return [
            self::approveRegistration(),
            self::resendInvitation(),
            self::sendPasswordReset(),
            self::lock(),
            self::unlock(),
            self::forceLogout(),
            self::deactivate(),
            self::reactivate(),
            self::revokeAccess(),
        ];
    }

    public static function group(): ActionGroup
    {
        return ActionGroup::make(self::all())->label(__('Account'))->icon(Heroicon::OutlinedEllipsisVertical);
    }

    private static function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    private static function done(string $message): void
    {
        Notification::make()->title($message)->success()->send();
    }

    public static function approveRegistration(): Action
    {
        return Action::make('approveRegistration')
            ->label(__('Approve registration'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->visible(fn (User $record) => $record->status === UserStatus::PendingApproval && self::actor()->can('update', $record))
            ->schema(fn () => [
                CheckboxList::make('role_ids')
                    ->label(__('Roles'))
                    ->options(Role::query()->where('team_id', app(CurrentEntity::class)->id())->orderBy('name')->pluck('name', 'id')->all())
                    ->required()
                    ->columns(2),
            ])
            ->action(function (User $record, array $data): void {
                app(ApproveRegistration::class)->handle($record, app(CurrentEntity::class)->require(), array_values($data['role_ids']), self::actor());
                self::done(__('Registration approved and activation link sent.'));
            });
    }

    public static function resendInvitation(): Action
    {
        return Action::make('resendInvitation')
            ->label(__('Send activation link'))
            ->icon(Heroicon::OutlinedEnvelope)
            ->visible(fn (User $record) => $record->status === UserStatus::Invited && self::actor()->can('update', $record))
            ->requiresConfirmation()
            ->action(function (User $record): void {
                app(IssueActivationLink::class)->handle($record, self::actor());
                self::done(__('Activation link sent to :email.', ['email' => $record->email]));
            });
    }

    public static function sendPasswordReset(): Action
    {
        return Action::make('sendPasswordReset')
            ->label(__('Send password reset link'))
            ->icon(Heroicon::OutlinedKey)
            ->visible(fn (User $record) => $record->isActive() && self::actor()->can('update', $record))
            ->requiresConfirmation()
            ->modalDescription(__('The person receives an email to choose a new password. Admins never see or set passwords.'))
            ->action(function (User $record): void {
                app(SendPasswordResetLink::class)->handle($record->email, self::actor());
                self::done(__('Password reset link sent.'));
            });
    }

    public static function lock(): Action
    {
        return Action::make('lock')
            ->label(__('Lock account'))
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('warning')
            ->visible(fn (User $record) => ! $record->isLocked() && self::canRestrict($record))
            ->schema([Textarea::make('reason')->label(__('Reason'))->required()->maxLength(500)])
            ->action(function (User $record, array $data): void {
                app(LockUser::class)->handle($record, self::actor(), $data['reason']);
                self::done(__('Account locked and signed out everywhere.'));
            });
    }

    public static function unlock(): Action
    {
        return Action::make('unlock')
            ->label(__('Unlock account'))
            ->icon(Heroicon::OutlinedLockOpen)
            ->visible(fn (User $record) => $record->isLocked() && self::canRestrict($record))
            ->requiresConfirmation()
            ->action(function (User $record): void {
                app(UnlockUser::class)->handle($record, self::actor());
                self::done(__('Account unlocked.'));
            });
    }

    public static function forceLogout(): Action
    {
        return Action::make('forceLogout')
            ->label(__('Sign out everywhere'))
            ->icon(Heroicon::OutlinedArrowRightStartOnRectangle)
            ->visible(fn (User $record) => $record->isActive() && self::canRestrict($record))
            ->requiresConfirmation()
            ->action(function (User $record): void {
                $ended = app(ForceLogout::class)->handle($record, self::actor());
                self::done(trans_choice(':count session ended.|:count sessions ended.', $ended));
            });
    }

    public static function deactivate(): Action
    {
        return Action::make('deactivate')
            ->label(__('Deactivate'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (User $record) => $record->status !== UserStatus::Deactivated
                && self::actor()->isNot($record)
                && self::actor()->can('changeStatus', $record))
            ->schema([Textarea::make('reason')->label(__('Reason'))->required()->maxLength(500)])
            ->action(function (User $record, array $data): void {
                app(DeactivateUser::class)->handle($record, self::actor(), $data['reason']);
                self::done(__('User deactivated.'));
            });
    }

    public static function reactivate(): Action
    {
        return Action::make('reactivate')
            ->label(__('Reactivate'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (User $record) => $record->status === UserStatus::Deactivated
                && self::actor()->isNot($record)
                && self::actor()->can('changeStatus', $record))
            ->requiresConfirmation()
            ->action(function (User $record): void {
                app(ReactivateUser::class)->handle($record, self::actor());
                self::done(__('User reactivated.'));
            });
    }

    public static function revokeAccess(): Action
    {
        return Action::make('revokeAccess')
            ->label(fn () => __('Remove access to :entity', ['entity' => app(CurrentEntity::class)->get()?->code]))
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('danger')
            ->visible(fn (User $record) => self::canRestrict($record)
                && $record->entities()->whereKey(app(CurrentEntity::class)->id())->exists())
            ->requiresConfirmation()
            ->action(function (User $record): void {
                app(RevokeEntityAccess::class)->handle($record, app(CurrentEntity::class)->require(), self::actor());
                self::done(__('Access removed.'));
            });
    }

    private static function canRestrict(User $record): bool
    {
        return self::actor()->isNot($record) && self::actor()->can('restrict', $record);
    }
}
