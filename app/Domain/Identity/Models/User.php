<?php

namespace App\Domain\Identity\Models;

use App\Domain\Core\Models\Entity;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * A person who can sign in to PACE. The login is global; access and roles
 * are granted per entity (Spatie team = entity).
 *
 * Security-sensitive columns (status, lockout, super-admin flag, password)
 * are deliberately not fillable and are only changed by Identity actions.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $designation
 * @property string|null $department
 * @property int|null $line_manager_id
 * @property UserStatus $status
 * @property bool $is_group_super_admin
 * @property string|null $password
 * @property int $failed_login_attempts
 * @property Carbon|null $locked_until
 * @property bool $locked_by_admin
 * @property Carbon|null $password_changed_at
 * @property Carbon|null $invitation_sent_at
 * @property Carbon|null $activated_at
 * @property Carbon|null $deactivated_at
 * @property Carbon|null $last_login_at
 * @property string $auth_provider
 */
#[Fillable(['name', 'email', 'designation', 'department', 'line_manager_id'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable;

    /**
     * Mirrors the column defaults so new instances behave like stored ones.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'invited',
        'is_group_super_admin' => false,
        'failed_login_attempts' => 0,
        'locked_by_admin' => false,
        'auth_provider' => 'local',
    ];

    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'is_group_super_admin' => 'boolean',
            'password' => 'hashed',
            'failed_login_attempts' => 'integer',
            'locked_until' => 'datetime',
            'locked_by_admin' => 'boolean',
            'password_changed_at' => 'datetime',
            'invitation_sent_at' => 'datetime',
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Entity, $this>
     */
    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class)
            ->withPivot(['is_home', 'granted_by'])
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function lineManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'line_manager_id');
    }

    /**
     * @return HasMany<LoginEvent, $this>
     */
    public function loginEvents(): HasMany
    {
        return $this->hasMany(LoginEvent::class);
    }

    /**
     * @return HasMany<Delegation, $this>
     */
    public function delegations(): HasMany
    {
        return $this->hasMany(Delegation::class);
    }

    /**
     * @return HasMany<PasswordHistory, $this>
     */
    public function passwordHistories(): HasMany
    {
        return $this->hasMany(PasswordHistory::class);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isLocked(): bool
    {
        return $this->locked_by_admin || ($this->locked_until !== null && $this->locked_until->isFuture());
    }

    public function isGroupSuperAdmin(): bool
    {
        return $this->is_group_super_admin;
    }

    /**
     * Whether the user may work in the given entity. Group Super Admins may work in any active entity.
     */
    public function canAccessEntity(Entity|int $entity): bool
    {
        $entity = $entity instanceof Entity ? $entity : Entity::query()->find($entity);

        if ($entity === null || ! $entity->is_active) {
            return false;
        }

        if ($this->isGroupSuperAdmin()) {
            return true;
        }

        return $this->entities()->whereKey($entity->getKey())->exists();
    }

    /**
     * Active entities this user can switch to.
     *
     * @return Collection<int, Entity>
     */
    public function accessibleEntities(): Collection
    {
        if ($this->isGroupSuperAdmin()) {
            return Entity::query()->active()->orderBy('name')->get();
        }

        return $this->entities()->where('entities.is_active', true)->orderBy('name')->get();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isActive() && ($this->isGroupSuperAdmin() || $this->can('admin.access'));
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('users')
            ->logOnly([
                'name', 'email', 'designation', 'department', 'line_manager_id', 'status',
                'is_group_super_admin', 'locked_until', 'locked_by_admin', 'deactivated_at',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
