<?php

namespace App\Filament\Admin\Pages;

use App\Domain\Core\Settings\SettingDefinition;
use App\Domain\Core\Settings\Settings;
use App\Domain\Core\Settings\SettingsRegistry;
use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Settings that can differ per entity (for example the minimum number of
 * quotations). Saved as overrides for the current entity only.
 *
 * @property-read Schema $form
 */
class EntitySettings extends Page
{
    protected static ?string $slug = 'entity-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return (bool) $user?->can('settings.manage');
    }

    public function getTitle(): string
    {
        return __('Settings for :entity', ['entity' => app(CurrentEntity::class)->get()?->name]);
    }

    public static function getNavigationLabel(): string
    {
        return __('Entity settings');
    }

    public function mount(Settings $settings, SettingsRegistry $registry): void
    {
        $entity = app(CurrentEntity::class)->require();
        $values = [];

        foreach ($this->definitions($registry) as $definition) {
            $values[self::field($definition->key)] = $settings->get($definition->key, $entity);
        }

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        $sections = [];

        foreach (app(SettingsRegistry::class)->bySection() as $section => $definitions) {
            $fields = array_map(self::component(...), array_values(array_filter($definitions, fn (SettingDefinition $d) => $d->scope === 'entity')));

            if ($fields !== []) {
                $sections[] = Section::make(__($section))->columns(2)->schema($fields);
            }
        }

        return $schema->components($sections)->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label(__('Save settings'))->submit('save')])]),
        ]);
    }

    public function save(Settings $settings, SettingsRegistry $registry): void
    {
        abort_unless(static::canAccess(), 403);

        $entity = app(CurrentEntity::class)->require();
        $data = $this->form->getState();

        /** @var User $user */
        $user = auth()->user();

        foreach ($this->definitions($registry) as $definition) {
            $settings->set($definition->key, $data[self::field($definition->key)] ?? $definition->default, $entity, $user);
        }

        Notification::make()->title(__('Settings saved for :entity.', ['entity' => $entity->code]))->success()->send();
    }

    /**
     * @return list<SettingDefinition>
     */
    private function definitions(SettingsRegistry $registry): array
    {
        return array_values(array_filter($registry->all(), fn (SettingDefinition $d) => $d->scope === 'entity'));
    }

    private static function field(string $key): string
    {
        return Str::replace('.', '__', $key);
    }

    private static function component(SettingDefinition $definition): Component
    {
        $name = self::field($definition->key);

        $component = match ($definition->type) {
            'bool' => Toggle::make($name),
            'int' => TextInput::make($name)->integer()->required()->rules($definition->rules),
            default => TextInput::make($name)->required()->rules($definition->rules),
        };

        return $component->label(__($definition->label))->helperText($definition->help ? __($definition->help) : null);
    }
}
