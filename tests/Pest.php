<?php

use App\Domain\Core\Models\Entity;
use App\Domain\Core\Models\Setting;
use App\Domain\Core\Settings\Settings;
use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Actions\ProvisionEntityRoles;
use App\Domain\Identity\Models\User;
use App\Http\Middleware\IdleTimeout;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // The breached-password check calls an external API; keep tests offline.
        setting('security.password_check_breached', false);
    })
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Store a group-level setting directly (no audit entry) and clear the cache.
 */
function setting(string $key, mixed $value): void
{
    Setting::query()->updateOrCreate(['key' => $key, 'entity_id' => null], ['value' => $value]);
    app(Settings::class)->flush();
}

/**
 * An active entity with the default role set.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeEntity(array $attributes = []): Entity
{
    $entity = Entity::factory()->create($attributes);
    app(ProvisionEntityRoles::class)->handle($entity);

    return $entity;
}

function kenya(): Entity
{
    return Entity::query()->where('code', 'KE')->first() ?? tap(Entity::factory()->kenya()->create(), fn (Entity $e) => app(ProvisionEntityRoles::class)->handle($e));
}

/**
 * An active user with access to the entity and the given roles there.
 *
 * @param  list<string>  $roles
 * @param  array<string, mixed>  $attributes
 */
function userIn(Entity $entity, array $roles = [], array $attributes = []): User
{
    $user = User::factory()->inEntity($entity)->create($attributes);

    if ($roles !== []) {
        app(CurrentEntity::class)->run($entity, fn () => $user->syncRoles(
            Role::query()->where('team_id', $entity->getKey())->whereIn('name', $roles)->get()
        ));
    }

    // As loaded from the database, like every real request (strict mode rejects missing attributes).
    return $user->fresh();
}

/**
 * Sign in as the user, working in the entity: session, CurrentEntity and the Filament panel.
 * Use for HTTP tests and for Livewire::test() on Filament pages (which skip route middleware).
 */
function actingInEntity(User $user, Entity $entity): User
{
    $user = $user->fresh();

    test()->actingAs($user)->withSession([
        'entity_id' => $entity->getKey(),
        IdleTimeout::SESSION_KEY => now()->getTimestamp(),
    ]);

    app(CurrentEntity::class)->set($entity);
    session()->put('entity_id', $entity->getKey());
    Filament::setCurrentPanel('admin');
    $user->unsetRelation('roles')->unsetRelation('permissions');

    return $user;
}

/**
 * The single generic sign-in failure message, with default settings.
 */
function genericLoginError(): string
{
    return __('These details don\'t match our records, or the account is not available. After :n failed attempts an account is locked for :m minutes.', ['n' => 5, 'm' => 15]);
}

/**
 * A real .xlsx upload built with PhpSpreadsheet. The first row is the heading row.
 *
 * @param  list<list<mixed>>  $rows
 */
function xlsxUpload(array $rows, string $name = 'import.xlsx'): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1', true);

    $path = tempnam(sys_get_temp_dir(), 'pace').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

/**
 * Rows of the first sheet of a stored workbook (heading row included).
 *
 * @return list<list<mixed>>
 */
function readXlsx(string $absolutePath): array
{
    return IOFactory::load($absolutePath)->getActiveSheet()->toArray();
}

/**
 * Run a callback inside an entity (for creating entity-owned test records).
 *
 * @template T
 *
 * @param  callable(Entity): T  $callback
 * @return T
 */
function inEntity(Entity $entity, callable $callback): mixed
{
    return app(CurrentEntity::class)->run($entity, $callback);
}

/**
 * Minimal valid PDF bytes (enough for content sniffing).
 */
function pdfBytes(): string
{
    return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
}
