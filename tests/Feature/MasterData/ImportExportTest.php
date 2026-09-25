<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\MasterData\Import\ExportSheet;
use App\Domain\MasterData\Import\ImportRun;
use App\Domain\MasterData\Import\ImportStatus;
use App\Domain\MasterData\Import\StartImport;
use App\Domain\MasterData\Models\BoardPaper;
use App\Domain\MasterData\Models\CostCentre;
use App\Domain\MasterData\Models\Department;
use App\Domain\MasterData\Models\Vendor;
use App\Filament\Admin\Resources\Departments\Pages\ManageDepartments;
use App\Filament\Admin\Resources\ImportRuns\Pages\ListImportRuns;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('documents');
    $this->kenya = kenya();
    $this->admin = userIn($this->kenya, ['Entity Admin']);
    actingInEntity($this->admin, $this->kenya);
});

function import(string $type, array $rows, ?User $by = null, array $options = []): ImportRun
{
    // The queue runs synchronously in tests, so the run is finished on return.
    return app(StartImport::class)->handle($type, xlsxUpload($rows), $by ?? test()->admin, $options)->refresh();
}

it('creates and updates departments from a workbook', function () {
    Department::query()->create(['code' => 'FIN', 'name' => 'Finance (old name)']);
    $head = userIn($this->kenya, [], ['email' => 'head@jfi.lk']);

    $run = import('departments', [
        ['Code', 'Name', 'Head Email', 'Description', 'Is Active'],
        ['FIN', 'Finance', null, null, 'yes'],
        ['PROD', 'Production', 'head@jfi.lk', 'Factory floor', null],
        [null, null, null, null, null],
        ['QA', 'Quality', null, null, 'no'],
    ]);

    expect($run->message)->toBe('2 created, 1 updated.');
    expect($run->status)->toBe(ImportStatus::Completed)
        ->and($run->total_rows)->toBe(3)
        ->and($run->created_rows)->toBe(2)
        ->and($run->updated_rows)->toBe(1);

    expect(Department::query()->where('code', 'FIN')->value('name'))->toBe('Finance')
        ->and(Department::query()->where('code', 'PROD')->value('head_user_id'))->toBe($head->id)
        ->and(Department::query()->where('code', 'QA')->value('is_active'))->toBeFalse()
        ->and(Department::query()->where('code', 'PROD')->value('entity_id'))->toBe($this->kenya->id);

    expect(Activity::query()->where('event', 'import_completed')->where('entity_id', $this->kenya->id)->exists())->toBeTrue();
});

it('imports nothing when any row is invalid and reports each problem by row', function () {
    $run = import('cost_centres', [
        ['code', 'name', 'department_code', 'owner_email', 'effective_from', 'effective_to'],
        ['KE-CC-1', 'Good row', null, null, '2026-04-01', null],
        ['KE-CC-2', null, 'NOPE', null, null, null],
        ['KE-CC-1', 'Duplicate code', null, 'nobody@jfi.lk', '2026-04-01', '2026-01-01'],
    ]);

    expect($run->status)->toBe(ImportStatus::Failed)
        ->and($run->failed_rows)->toBe(2)
        ->and(CostCentre::query()->count())->toBe(0)
        ->and($run->error_report_path)->not->toBeNull();

    $report = readXlsx(Storage::disk('documents')->path($run->error_report_path));
    expect($report[0][0])->toBe('row')->and($report[0][1])->toBe('errors')
        ->and($report[1][1])->toBeNull()
        ->and($report[2][0])->toBe('3')
        ->and($report[2][1])->toContain('name field is required')->toContain('Department "NOPE" does not exist')
        ->and($report[3][1])->toContain('Duplicate of row 2')->toContain('"nobody@jfi.lk" is not a user')->toContain('effective to');
});

it('does not resolve codes that exist only in another entity', function () {
    $uae = makeEntity(['code' => 'AE']);
    inEntity($uae, fn () => Department::query()->create(['code' => 'UAEONLY', 'name' => 'UAE dept']));

    $run = import('cost_centres', [['code', 'name', 'department_code'], ['KE-CC-9', 'Line 9', 'UAEONLY']]);

    expect($run->status)->toBe(ImportStatus::Failed)->and(CostCentre::query()->count())->toBe(0);
});

it('fails clearly when required columns are missing', function () {
    $run = import('departments', [['Name'], ['Finance']]);

    expect($run->status)->toBe(ImportStatus::Failed)
        ->and($run->message)->toContain('Missing columns: code');
});

it('reads Excel numbers and dates exactly', function () {
    $run = import('board_papers', [
        ['code', 'name', 'paper_date', 'approved_amount', 'currency'],
        ['BP-9', 'Numbers typed in Excel', 46218, 45000000.5, 'kes'],
    ]);

    expect($run->status)->toBe(ImportStatus::Completed);
    $paper = BoardPaper::query()->where('code', 'BP-9')->firstOrFail();
    expect((string) $paper->approved_amount)->toBe('45000000.50')
        ->and($paper->paper_date->toDateString())->toBe('2026-07-15')
        ->and($paper->currency_code)->toBe('KES');
});

it('ignores bank columns from people without the payment permission', function () {
    $run = import('vendors', [
        ['code', 'name', 'bank_account_number'],
        ['V-9', 'Imported vendor', '5555666677778888'],
    ]);

    expect($run->status)->toBe(ImportStatus::Completed)
        ->and(Vendor::query()->where('code', 'V-9')->first()->bank_account_number)->toBeNull();

    $payments = userIn($this->kenya, ['Payment Team']);
    $payments->givePermissionTo('masterdata.import');
    actingInEntity($payments, $this->kenya);

    import('vendors', [['code', 'name', 'bank_account_number'], ['V-9', 'Imported vendor', '5555666677778888']], $payments->fresh());

    expect(Vendor::query()->where('code', 'V-9')->first()->bank_account_number)->toBe('5555666677778888');
});

it('creates invited users and updates existing ones from a workbook', function () {
    $existing = userIn($this->kenya, ['Requester'], ['email' => 'existing@jfi.lk', 'name' => 'Existing Person']);

    $run = import('users', [
        ['name', 'email', 'designation', 'department', 'roles', 'home_entity'],
        ['Grace Wanjiru', 'grace@jfi.lk', 'Engineer', 'Engineering', 'Requester, Validator', 'yes'],
        ['Existing Person', 'existing@jfi.lk', 'Plant Manager', null, 'Approver', null],
    ]);

    expect($run->status)->toBe(ImportStatus::Completed)
        ->and($run->created_rows)->toBe(1)
        ->and($run->updated_rows)->toBe(1);

    $grace = User::query()->where('email', 'grace@jfi.lk')->firstOrFail();
    expect($grace->status)->toBe(UserStatus::Invited)
        ->and(inEntity($this->kenya, fn () => $grace->roles()->pluck('name')->sort()->values()->all()))->toBe(['Requester', 'Validator'])
        ->and(inEntity($this->kenya, fn () => $existing->fresh()->roles()->pluck('name')->all()))->toBe(['Approver'])
        ->and($existing->fresh()->designation)->toBe('Plant Manager');
});

it('rejects unknown roles and emails outside the allowed domains in user imports', function () {
    $run = import('users', [
        ['name', 'email', 'roles'],
        ['Outsider', 'someone@gmail.com', 'Requester'],
        ['Typo', 'typo@jfi.lk', 'Aprover'],
    ]);

    $report = readXlsx(Storage::disk('documents')->path($run->error_report_path));
    expect($run->status)->toBe(ImportStatus::Failed)
        ->and(User::query()->whereIn('email', ['someone@gmail.com', 'typo@jfi.lk'])->exists())->toBeFalse()
        ->and($report[1][1])->toContain('email domain is not allowed')
        ->and($report[2][1])->toContain('Unknown role(s)');
});

it('refuses imports from people without the import permission', function () {
    $auditor = userIn($this->kenya, ['Viewer / Auditor']);
    actingInEntity($auditor, $this->kenya);

    import('departments', [['code', 'name'], ['X', 'Y']], $auditor);
})->throws(AuthorizationException::class);

it('accepts only real xlsx workbooks', function () {
    $fake = UploadedFile::fake()->createWithContent('data.xlsx', 'code,name');

    app(StartImport::class)->handle('departments', $fake, $this->admin);
})->throws(ValidationException::class);

it('exports records in the import layout so they can be edited and re-imported', function () {
    Department::query()->create(['code' => 'FIN', 'name' => 'Finance']);
    Department::query()->create(['code' => 'ENG', 'name' => 'Engineering', 'is_active' => false]);

    $response = app(ExportSheet::class)->handle('departments', $this->admin);
    $rows = readXlsx($response->getFile()->getPathname());

    expect($rows[0])->toBe(['code', 'name', 'head_email', 'description', 'is_active'])
        ->and($rows[1])->toBe(['ENG', 'Engineering', null, null, 'no'])
        ->and(Activity::query()->where('event', 'master_data_exported')->exists())->toBeTrue();

    $run = app(StartImport::class)->handle('departments', new UploadedFile($response->getFile()->getPathname(), 'export.xlsx', null, null, true), $this->admin)->refresh();
    expect($run->status)->toBe(ImportStatus::Completed)->and($run->updated_rows)->toBe(2)->and($run->created_rows)->toBe(0);
});

it('offers export and template downloads on the screen', function () {
    Livewire::test(ManageDepartments::class)->callAction('export')->assertFileDownloaded();
    Livewire::test(ManageDepartments::class)->callAction('template')->assertFileDownloaded();
});

it('keeps import history and error reports within the entity', function () {
    $run = import('departments', [['code', 'name'], ['X', null]]);
    $uae = makeEntity(['code' => 'AE']);
    $uaeAdmin = userIn($uae, ['Entity Admin']);

    $this->get(route('imports.errors', $run))->assertOk();

    actingInEntity($uaeAdmin, $uae);
    $this->get(route('imports.errors', $run))->assertNotFound();
    Livewire::test(ListImportRuns::class)->assertCanNotSeeTableRecords([$run]);
});
