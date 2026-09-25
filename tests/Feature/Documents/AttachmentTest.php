<?php

use App\Domain\Audit\Models\Activity;
use App\Domain\Documents\Actions\StoreAttachment;
use App\Domain\Documents\Contracts\AttachmentScanner;
use App\Domain\Documents\Enums\ScanStatus;
use App\Domain\Documents\Models\Attachment;
use App\Domain\MasterData\Models\BoardPaper;
use App\Filament\Admin\Resources\BoardPapers\Pages\ManageBoardPapers;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('documents');
    $this->kenya = kenya();
    $this->admin = userIn($this->kenya, ['Entity Admin']);
    actingInEntity($this->admin, $this->kenya);
    $this->paper = BoardPaper::query()->create(['code' => 'BP-1', 'name' => 'Line 2', 'paper_date' => '2026-07-01', 'approved_amount' => '1000.00', 'currency_code' => 'KES']);
});

function store(UploadedFile $file): Attachment
{
    return app(StoreAttachment::class)->handle(test()->paper, $file, 'board_paper', test()->admin);
}

it('stores an allowed file privately under a random name with its hash', function () {
    $attachment = store(UploadedFile::fake()->createWithContent('Board Paper July.pdf', pdfBytes()));

    expect($attachment->original_name)->toBe('Board Paper July.pdf')
        ->and($attachment->path)->not->toContain('Board')
        ->and($attachment->path)->toStartWith('entity-'.$this->kenya->id.'/')
        ->and($attachment->mime_type)->toBe('application/pdf')
        ->and($attachment->sha256)->toBe(hash('sha256', pdfBytes()))
        ->and($attachment->scan_status)->toBe(ScanStatus::NotScanned)
        ->and($attachment->entity_id)->toBe($this->kenya->id);

    Storage::disk('documents')->assertExists($attachment->path);
});

it('rejects file types outside the allow-list', function () {
    store(UploadedFile::fake()->createWithContent('tool.exe', 'MZ binary'));
})->throws(ValidationException::class, 'not allowed');

it('rejects a file whose content does not match its extension', function (string $name, string $content) {
    store(UploadedFile::fake()->createWithContent($name, $content));
})->throws(ValidationException::class, 'does not match')->with([
    'executable renamed to pdf' => ['invoice.pdf', "MZ\x90\x00 fake executable"],
    'text renamed to xlsx' => ['sheet.xlsx', 'just some text'],
    'html renamed to png' => ['photo.png', '<html><script>alert(1)</script></html>'],
]);

it('enforces the configured size limit', function () {
    setting('attachments.max_size_mb', 1);

    store(UploadedFile::fake()->createWithContent('big.pdf', pdfBytes().str_repeat(' ', 1024 * 1024 + 10)));
})->throws(ValidationException::class, 'larger than 1 MB');

it('respects a narrowed list of allowed types', function () {
    setting('attachments.allowed_extensions', ['pdf']);

    store(UploadedFile::fake()->image('photo.png'));
})->throws(ValidationException::class, 'not allowed');

it('blocks files the virus scanner flags', function () {
    app()->bind(AttachmentScanner::class, fn () => new class implements AttachmentScanner
    {
        public function scan(string $localPath): ScanStatus
        {
            return ScanStatus::Infected;
        }
    });

    try {
        store(UploadedFile::fake()->createWithContent('paper.pdf', pdfBytes()));
    } catch (ValidationException) {
        expect(Attachment::query()->count())->toBe(0)
            ->and(Activity::query()->where('event', 'upload_blocked')->exists())->toBeTrue();

        return;
    }

    $this->fail('Infected upload was accepted.');
});

it('downloads through the authorised route and audits it', function () {
    $attachment = store(UploadedFile::fake()->createWithContent('paper.pdf', pdfBytes()));

    $response = $this->get(route('attachments.download', $attachment))->assertOk();

    expect($response->headers->get('content-disposition'))->toContain('attachment')->toContain('paper.pdf')
        ->and($response->headers->get('x-content-type-options'))->toBe('nosniff')
        ->and(Activity::query()->where('event', 'attachment_downloaded')->exists())->toBeTrue();
});

it('returns 404 for another entity\'s attachment', function () {
    $attachment = store(UploadedFile::fake()->createWithContent('paper.pdf', pdfBytes()));
    $uae = makeEntity(['code' => 'AE']);
    $uaeAdmin = userIn($uae, ['Entity Admin']);
    actingInEntity($uaeAdmin, $uae);

    $this->get(route('attachments.download', $attachment))->assertNotFound();
});

it('refuses downloads to people who cannot view the record', function () {
    $attachment = store(UploadedFile::fake()->createWithContent('paper.pdf', pdfBytes()));
    actingInEntity(userIn($this->kenya, ['Requester']), $this->kenya);

    $this->get(route('attachments.download', $attachment))->assertForbidden();
});

it('refuses downloads of files not cleared by the scanner', function () {
    $attachment = store(UploadedFile::fake()->createWithContent('paper.pdf', pdfBytes()));
    $attachment->forceFill(['scan_status' => ScanStatus::Pending])->save();

    $this->get(route('attachments.download', $attachment))->assertForbidden();
});

it('attaches the document when a board paper is saved from the admin screen', function () {
    Livewire::test(ManageBoardPapers::class)
        ->callAction('create', [
            'code' => 'BP-2', 'name' => 'Solar roof', 'paper_date' => '2026-08-01', 'currency_code' => 'KES', 'approved_amount' => '2500000.50',
            'document_upload' => UploadedFile::fake()->createWithContent('solar.pdf', pdfBytes()),
        ])
        ->assertHasNoActionErrors();

    $paper = BoardPaper::query()->where('code', 'BP-2')->firstOrFail();
    expect((string) $paper->approved_amount)->toBe('2500000.50')
        ->and($paper->attachments()->count())->toBe(1)
        ->and($paper->attachments()->first()->original_name)->toBe('solar.pdf');
});
