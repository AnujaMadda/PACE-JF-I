<?php

namespace App\Domain\Core\Models;

use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\EntityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An operating company (Kenya, UAE, Bangladesh, ...). Every master-data and
 * transactional record belongs to exactly one entity.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $country
 * @property string $base_currency
 * @property string $timezone
 * @property int $fy_start_month
 * @property string $request_prefix
 * @property list<string> $allowed_email_domains
 * @property bool $is_active
 */
#[Fillable([
    'code', 'name', 'country', 'base_currency', 'timezone', 'fy_start_month',
    'request_prefix', 'allowed_email_domains', 'is_active',
])]
#[UseFactory(EntityFactory::class)]
class Entity extends Model
{
    /** @use HasFactory<EntityFactory> */
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'allowed_email_domains' => 'array',
            'fy_start_month' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['is_home', 'granted_by'])
            ->withTimestamps();
    }

    /**
     * @param  Builder<Entity>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function allowsEmail(string $email): bool
    {
        $domain = Str::lower(Str::after($email, '@'));

        return in_array($domain, array_map(Str::lower(...), $this->allowed_email_domains ?? []), true);
    }

    /**
     * The fiscal year label number: the calendar year in which the fiscal year ends.
     * With an April start, 2026-09-25 falls in FY27.
     */
    public function fiscalYearFor(CarbonInterface $date): int
    {
        $local = CarbonImmutable::instance($date)->setTimezone($this->timezone);

        if ($this->fy_start_month === 1) {
            return $local->year;
        }

        return $local->month >= $this->fy_start_month ? $local->year + 1 : $local->year;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('entities')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
