<x-layouts::guest :title="__('Reset password')">
    <h1 class="text-2xl font-semibold">{{ __('Choose a new password') }}</h1>

    <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-5" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label for="email" class="pace-label">{{ __('Company email') }}</label>
            <input id="email" name="email" type="email" autocomplete="username" required value="{{ old('email', $email) }}" class="pace-input">
            <x-field-error name="email" />
        </div>
        @include('auth.partials.password-fields')
        <button type="submit" class="pace-button">{{ __('Reset password') }}</button>
    </form>
</x-layouts::guest>
