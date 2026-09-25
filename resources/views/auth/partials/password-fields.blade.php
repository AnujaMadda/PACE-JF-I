<div>
    <label for="password" class="pace-label">{{ __('New password') }}</label>
    <input id="password" name="password" type="password" autocomplete="new-password" required class="pace-input" aria-describedby="password-help">
    <p id="password-help" class="mt-1 text-xs text-slate-500">{{ $passwordHelp }}</p>
    <x-field-error name="password" />
</div>

<div>
    <label for="password_confirmation" class="pace-label">{{ __('Confirm new password') }}</label>
    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="pace-input">
</div>
