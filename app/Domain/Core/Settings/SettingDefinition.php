<?php

namespace App\Domain\Core\Settings;

/**
 * Describes one configurable setting: its type, default, scope and validation.
 */
final readonly class SettingDefinition
{
    /**
     * @param  'int'|'bool'|'string'|'list'  $type
     * @param  'group'|'entity'  $scope  group = one value for all entities; entity = group default with per-entity override
     * @param  list<string>  $rules  Laravel validation rules for the value
     */
    public function __construct(
        public string $key,
        public string $type,
        public mixed $default,
        public string $scope,
        public string $section,
        public string $label,
        public ?string $help = null,
        public array $rules = [],
    ) {}

    public function cast(mixed $value): mixed
    {
        return match ($this->type) {
            'int' => (int) $value,
            'bool' => (bool) $value,
            'string' => (string) $value,
            'list' => array_values(array_filter(array_map('strval', (array) $value), fn (string $v) => $v !== '')),
        };
    }
}
