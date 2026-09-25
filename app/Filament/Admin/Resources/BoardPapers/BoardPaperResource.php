<?php

namespace App\Filament\Admin\Resources\BoardPapers;

use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Documents\Actions\StoreAttachment;
use App\Domain\Documents\Models\Attachment;
use App\Domain\Documents\Support\AttachmentRules;
use App\Domain\Identity\Models\User;
use App\Domain\MasterData\Models\BoardPaper;
use App\Domain\MasterData\Models\Currency;
use App\Filament\Admin\MasterData\MasterDataResource;
use App\Filament\Admin\Resources\BoardPapers\Pages\ManageBoardPapers;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class BoardPaperResource extends MasterDataResource
{
    protected static ?string $model = BoardPaper::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?int $navigationSort = 19;

    public static function importType(): ?string
    {
        return 'board_papers';
    }

    public static function form(Schema $schema): Schema
    {
        $entity = app(CurrentEntity::class)->require();
        $rules = app(AttachmentRules::class);

        return $schema->columns(2)->components([
            static::codeInput('BP-2026-07')->label(__('Reference number')),
            static::nameInput()->label(__('Title')),
            DatePicker::make('paper_date')->label(__('Date'))->required()->maxDate(now()->addYear()),
            Select::make('currency_code')->label(__('Currency'))->options(fn () => Currency::optionsFor($entity))->default($entity->base_currency)->required(),
            // Not ->numeric(): that turns the value into a float. Money stays a string until DecimalCast.
            TextInput::make('approved_amount')->label(__('Approved amount'))->required()
                ->rule('regex:/^\d{1,16}(\.\d{1,2})?$/')->inputMode('decimal')->placeholder('0.00'),
            FileUpload::make('document_upload')
                ->label(__('Board paper document'))
                ->helperText(__('Allowed: :types, up to :mb MB. A new upload is added alongside earlier versions.', ['types' => implode(', ', $rules->allowedExtensions()), 'mb' => intdiv($rules->maxBytes(), 1048576)]))
                ->storeFiles(false)
                ->maxSize($rules->maxKilobytes())
                ->acceptedFileTypes($rules->acceptedMimeTypes())
                ->dehydrated(),
            static::descriptionInput(),
            static::activeToggle(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $table = static::standardTable($table, [
            TextColumn::make('code')->label(__('Reference'))->searchable()->sortable()->weight('medium'),
            TextColumn::make('name')->label(__('Title'))->searchable()->wrap(),
            TextColumn::make('paper_date')->label(__('Date'))->date()->sortable(),
            TextColumn::make('approved_amount')->label(__('Approved'))
                ->formatStateUsing(fn (BoardPaper $record) => $record->currency_code.' '.number_format((float) (string) $record->approved_amount, 2))
                ->alignEnd(),
            TextColumn::make('attachments_count')->label(__('Documents'))->counts('attachments'),
        ], sort: 'paper_date');

        return $table->pushRecordActions([
            Action::make('download')
                ->label(__('Document'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (BoardPaper $record) => $record->attachments()->exists())
                ->url(fn (BoardPaper $record) => $record->attachments()->first()?->downloadUrl()),
        ]);
    }

    public static function createRecord(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            $upload = $data['document_upload'] ?? null;
            unset($data['document_upload']);

            $paper = parent::createRecord($data);
            static::attach($paper, $upload);

            return $paper;
        });
    }

    public static function updateRecord(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            $upload = $data['document_upload'] ?? null;
            unset($data['document_upload']);

            parent::updateRecord($record, $data);
            static::attach($record, $upload);

            return $record;
        });
    }

    protected static function attach(Model $paper, mixed $upload): ?Attachment
    {
        if (is_array($upload)) {
            $upload = reset($upload) ?: null;
        }

        if (! $upload instanceof TemporaryUploadedFile) {
            return null;
        }

        /** @var User|null $user */
        $user = auth()->user();

        return app(StoreAttachment::class)->handle($paper, $upload, 'board_paper', $user, 'document_upload');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBoardPapers::route('/'),
        ];
    }
}
