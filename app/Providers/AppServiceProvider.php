<?php

namespace App\Providers;

use App\Domain\Audit\Models\Activity;
use App\Domain\Core\Models\Entity;
use App\Domain\Core\Money\Contracts\ExchangeRateProvider;
use App\Domain\Core\Settings\Settings;
use App\Domain\Core\Settings\SettingsRegistry;
use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Core\Support\LocalTime;
use App\Domain\Documents\Contracts\AttachmentScanner;
use App\Domain\Documents\Models\Attachment;
use App\Domain\Documents\Scanners\NullAttachmentScanner;
use App\Domain\Identity\Models\LoginEvent;
use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Erp\Contracts\ErpConnector;
use App\Domain\Integrations\Erp\NullErpConnector;
use App\Domain\MasterData\Import\ImportRun;
use App\Domain\MasterData\Models as MasterData;
use App\Domain\MasterData\Models\Currency;
use App\Domain\MasterData\Support\TableExchangeRateProvider;
use App\Http\Middleware\EnsureEntitySelected;
use App\Http\Middleware\EnsurePasswordNotExpired;
use App\Http\Middleware\IdleTimeout;
use App\Policies\ActivityPolicy;
use App\Policies\AttachmentPolicy;
use App\Policies\CurrencyPolicy;
use App\Policies\EntityPolicy;
use App\Policies\ImportRunPolicy;
use App\Policies\LoginEventPolicy;
use App\Policies\MasterDataPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use App\Policies\VendorPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Spatie\Activitylog\Facades\Activity as ActivityLog;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /** Per-entity master data, all governed by MasterDataPolicy. */
    private const MASTER_DATA_MODELS = [
        MasterData\Department::class, MasterData\CostCentre::class, MasterData\ProfitCentre::class,
        MasterData\GlAccount::class, MasterData\InternalOrder::class, MasterData\PaymentTerm::class,
        MasterData\BudgetCode::class, MasterData\CapexCategory::class,
        MasterData\BoardPaper::class, MasterData\ExchangeRate::class,
    ];

    /** Abilities a Group Super Admin does not bypass. */
    private const POLICY_ONLY_ABILITIES = [
        'delete', 'deleteAny', 'forceDelete', 'forceDeleteAny', 'restore', 'restoreAny', 'replicate', 'reorder',
    ];

    public function register(): void
    {
        // One CurrentEntity per request / job; never shared across requests (Octane-safe).
        $this->app->scoped(CurrentEntity::class);
        $this->app->scoped(Settings::class);
        $this->app->singleton(SettingsRegistry::class);

        // Extension seams (PLAN.md §3.11): default implementations.
        $this->app->bind(ExchangeRateProvider::class, TableExchangeRateProvider::class);
        $this->app->bind(AttachmentScanner::class, NullAttachmentScanner::class);
        $this->app->bind(ErpConnector::class, NullErpConnector::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // Group Super Admins pass every check except destructive ones, which stay with the policies
        // (users, entities and audit records are never deleted). Queries remain entity-scoped.
        Gate::before(fn (User $user, string $ability) => $user->isGroupSuperAdmin() && ! in_array($ability, self::POLICY_ONLY_ABILITIES, true) ? true : null);

        Gate::policy(Entity::class, EntityPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(LoginEvent::class, LoginEventPolicy::class);
        Gate::policy(Attachment::class, AttachmentPolicy::class);
        Gate::policy(ImportRun::class, ImportRunPolicy::class);
        Gate::policy(Currency::class, CurrencyPolicy::class);
        Gate::policy(MasterData\Vendor::class, VendorPolicy::class);

        foreach (self::MASTER_DATA_MODELS as $model) {
            Gate::policy($model, MasterDataPolicy::class);
        }

        $this->enrichAuditRecords();
        $this->registerRateLimiters();

        // Re-run entity and idle checks on every Livewire update request, not just the first page load.
        Livewire::addPersistentMiddleware([IdleTimeout::class, EnsureEntitySelected::class, EnsurePasswordNotExpired::class]);

        Blade::directive('localtime', fn (string $expression) => '<?php echo e(\\'.LocalTime::class."::format({$expression})); ?>");
    }

    /**
     * Stamp every audit record with entity, IP, user agent and request id.
     */
    private function enrichAuditRecords(): void
    {
        ActivityLog::beforeLogging(function (ActivityContract $activity): void {
            /** @var Activity $activity */
            $entityId = $activity->properties?->get('entity_id');

            if ($entityId === null && $activity->subject instanceof Entity) {
                $entityId = $activity->subject->getKey();
            }

            $activity->entity_id = $entityId ?? app(CurrentEntity::class)->id();

            if (! $this->app->runningInConsole() || $this->app->runningUnitTests()) {
                $request = request();
                $activity->ip_address = $request->ip();
                $activity->user_agent = Str::limit((string) $request->userAgent(), 500, '');
                $requestId = $request->attributes->get('request_id');
                $activity->request_id = is_string($requestId) ? $requestId : null;
            }
        });
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(10)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(30)->by('ip:'.$request->ip()),
        ]);

        RateLimiter::for('auth-email', fn (Request $request) => [
            Limit::perMinute(3)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perHour(20)->by('ip:'.$request->ip()),
        ]);
    }
}
