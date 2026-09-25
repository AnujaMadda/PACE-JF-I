<?php

namespace App\Domain\Core\Settings;

use App\Domain\Documents\Support\AttachmentRules;
use InvalidArgumentException;

/**
 * The single list of configurable settings. Every setting read through
 * Settings::get() must be defined here. Later phases add their keys here
 * (SLA defaults, attachment limits, quotation minimum, tolerances, ...).
 */
final class SettingsRegistry
{
    /** @var array<string, SettingDefinition>|null */
    private ?array $definitions = null;

    /**
     * @return array<string, SettingDefinition>
     */
    public function all(): array
    {
        return $this->definitions ??= collect($this->define())
            ->keyBy(fn (SettingDefinition $d) => $d->key)
            ->all();
    }

    public function get(string $key): SettingDefinition
    {
        return $this->all()[$key] ?? throw new InvalidArgumentException("Unknown setting [{$key}].");
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    /**
     * @return array<string, list<SettingDefinition>>
     */
    public function bySection(): array
    {
        $sections = [];

        foreach ($this->all() as $definition) {
            $sections[$definition->section][] = $definition;
        }

        return $sections;
    }

    /**
     * @return list<SettingDefinition>
     */
    private function define(): array
    {
        return [
            // Passwords
            new SettingDefinition('security.password_min_length', 'int', 12, 'group', 'Passwords', 'Minimum password length', rules: ['integer', 'min:12', 'max:128']),
            new SettingDefinition('security.password_require_mixed_case', 'bool', true, 'group', 'Passwords', 'Require upper and lower case letters'),
            new SettingDefinition('security.password_require_numbers', 'bool', true, 'group', 'Passwords', 'Require numbers'),
            new SettingDefinition('security.password_require_symbols', 'bool', true, 'group', 'Passwords', 'Require symbols'),
            new SettingDefinition('security.password_check_breached', 'bool', true, 'group', 'Passwords', 'Reject passwords found in known data breaches', 'Checks the Have I Been Pwned range API. Only the first 5 characters of a SHA-1 hash leave the server.'),
            new SettingDefinition('security.password_history_count', 'int', 5, 'group', 'Passwords', 'Block reuse of the last N passwords', rules: ['integer', 'min:0', 'max:24']),
            new SettingDefinition('security.password_expiry_days', 'int', 0, 'group', 'Passwords', 'Password expiry (days)', '0 turns expiry off.', ['integer', 'min:0', 'max:365']),

            // Sign-in
            new SettingDefinition('security.lockout_max_attempts', 'int', 5, 'group', 'Sign-in', 'Failed attempts before lockout', rules: ['integer', 'min:3', 'max:20']),
            new SettingDefinition('security.lockout_minutes', 'int', 15, 'group', 'Sign-in', 'Lockout duration (minutes)', rules: ['integer', 'min:1', 'max:1440']),
            new SettingDefinition('security.session_idle_minutes', 'int', 30, 'group', 'Sign-in', 'Idle session timeout (minutes)', rules: ['integer', 'min:5', 'max:480']),
            new SettingDefinition('security.activation_link_minutes', 'int', 60, 'group', 'Sign-in', 'Activation link validity (minutes)', rules: ['integer', 'min:15', 'max:10080']),
            // Attachments (Documents module)
            new SettingDefinition('attachments.max_size_mb', 'int', 10, 'group', 'Attachments', 'Maximum file size (MB)', rules: ['integer', 'min:1', 'max:50']),
            new SettingDefinition('attachments.allowed_extensions', 'list', AttachmentRules::EXTENSIONS, 'group', 'Attachments', 'Allowed file types', 'Choose from: '.implode(', ', AttachmentRules::EXTENSIONS).'. Other types are never accepted.', ['array', 'min:1'], ['in:'.implode(',', AttachmentRules::EXTENSIONS)]),

            // Capex (per entity)
            new SettingDefinition('capex.minimum_quotations', 'int', 3, 'entity', 'Capex', 'Minimum quotations per request', 'Capex categories can override this. Fewer quotations need a sole-source justification.', ['integer', 'min:0', 'max:10']),

            new SettingDefinition('auth.self_registration', 'bool', false, 'group', 'Sign-in', 'Allow self-registration from allowed domains', 'New accounts land in "Pending admin approval" and have no access until an admin grants it.'),
        ];
    }
}
