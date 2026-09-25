<?php

namespace App\Filament\Admin\Pages;

use App\Domain\Core\Settings\SettingDefinition;
use App\Domain\Core\Settings\Settings;
use App\Domain\Core\Settings\SettingsRegistry;
use App\Domain\Identity\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
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
 * Group-wide system settings, generated from SettingsRegistry.
 * Entity-level overrides get their own page when entity-scoped keys arrive (Phase 2+).
 *
 * @property-read Schema $form
 */
class SystemSettings extends Page
{
    protected static ?string $slug = 'system-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $title = 'System settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return (bool) $user?->isGroupSuperAdmin();
    }

    public function mount(Settings $settings, SettingsRegistry $registry): void
    {
        $values = [];

        foreach ($this->groupDefinitions($registry) as $definition) {
            $values[self::field($definition->key)] = $settings->get($definition->key);
        }

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        $registry = app(SettingsRegistry::class);
        $sections = [];

        foreach ($registry->bySection() as $section => $definitions) {
            $fields = array_map(self::component(...), array_filter($definitions, fn (SettingDefinition $d) => $d->scope === 'group'));

            if ($fields !== []) {
                $sections[] = Section::make(__($section))->columns(2)->schema(array_values($fields));
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
                ->footer([
                    Actions::make([
                        Action::make('save')->label(__('Save settings'))->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(Settings $settings, SettingsRegistry $registry): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();

        /** @var User $user */
        $user = auth()->user();

        foreach ($this->groupDefinitions($registry) as $definition) {
            $settings->set($definition->key, $data[self::field($definition->key)] ?? $definition->default, by: $user);
        }

        Notification::make()->title(__('Settings saved.'))->success()->send();
    }

    /**
     * @return list<SettingDefinition>
     */
    private function groupDefinitions(SettingsRegistry $registry): array
    {
        return array_values(array_filter($registry->all(), fn (SettingDefinition $d) => $d->scope === 'group'));
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
            'list' => TagsInput::make($name),
            default => TextInput::make($name)->required()->rules($definition->rules),
        };

        return $component->label(__($definition->label))->helperText($definition->help ? __($definition->help) : null);
    }
}
