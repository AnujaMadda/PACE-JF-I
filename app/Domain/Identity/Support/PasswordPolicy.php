<?php

namespace App\Domain\Identity\Support;

use App\Domain\Core\Settings\Settings;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Rules\NotRecentlyUsed;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;

/**
 * Builds password validation rules from the configurable settings.
 */
class PasswordPolicy
{
    public function __construct(private readonly Settings $settings) {}

    public function rule(): Password
    {
        $rule = Password::min(max(12, $this->settings->int('security.password_min_length')))->max(128);

        if ($this->settings->bool('security.password_require_mixed_case')) {
            $rule->mixedCase();
        }

        if ($this->settings->bool('security.password_require_numbers')) {
            $rule->numbers();
        }

        if ($this->settings->bool('security.password_require_symbols')) {
            $rule->symbols();
        }

        if ($this->settings->bool('security.password_check_breached')) {
            $rule->uncompromised();
        }

        return $rule;
    }

    /**
     * Full rule set for a new password, including confirmation and history.
     *
     * @return list<string|ValidationRule|Password>
     */
    public function rules(?User $user = null): array
    {
        $rules = ['required', 'string', 'confirmed', $this->rule()];

        if ($user !== null) {
            $rules[] = new NotRecentlyUsed($user, $this->historyCount());
        }

        return $rules;
    }

    public function historyCount(): int
    {
        return $this->settings->int('security.password_history_count');
    }

    public function isExpired(User $user): bool
    {
        $days = $this->settings->int('security.password_expiry_days');

        if ($days <= 0 || $user->auth_provider !== 'local') {
            return false;
        }

        return $user->password_changed_at === null || $user->password_changed_at->addDays($days)->isPast();
    }

    /**
     * A human-readable summary for forms.
     */
    public function description(): string
    {
        $parts = [__('at least :n characters', ['n' => max(12, $this->settings->int('security.password_min_length'))])];

        if ($this->settings->bool('security.password_require_mixed_case')) {
            $parts[] = __('upper and lower case letters');
        }
        if ($this->settings->bool('security.password_require_numbers')) {
            $parts[] = __('a number');
        }
        if ($this->settings->bool('security.password_require_symbols')) {
            $parts[] = __('a symbol');
        }

        $text = __('Use :rules.', ['rules' => implode(', ', $parts)]);

        if ($this->historyCount() > 0) {
            $text .= ' '.__('You cannot reuse your last :n passwords.', ['n' => $this->historyCount()]);
        }

        return $text;
    }
}
