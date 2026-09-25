<x-layouts::app :title="__('Change password')">
    <div class="max-w-md">
        <h1 class="text-2xl font-semibold">{{ __('Change password') }}</h1>

        <form method="POST" action="{{ route('password.change.update') }}" class="mt-6 space-y-5 rounded-xl border border-slate-200 bg-white p-6" novalidate>
            @csrf
            @method('PUT')
            <div>
                <label for="current_password" class="pace-label">{{ __('Current password') }}</label>
                <input id="current_password" name="current_password" type="password" autocomplete="current-password" required class="pace-input">
                <x-field-error name="current_password" />
            </div>
            @include('auth.partials.password-fields')
            <button type="submit" class="pace-button">{{ __('Change password') }}</button>
        </form>
    </div>
</x-layouts::app>
