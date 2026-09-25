<x-layouts::guest :title="__('Forgot password')">
    <h1 class="text-2xl font-semibold">{{ __('Forgot your password?') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('Enter your company email and we will send you a reset link.') }}</p>

    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5" novalidate>
        @csrf
        <div>
            <label for="email" class="pace-label">{{ __('Company email') }}</label>
            <input id="email" name="email" type="email" autocomplete="email" required autofocus value="{{ old('email') }}" class="pace-input">
            <x-field-error name="email" />
        </div>
        <button type="submit" class="pace-button">{{ __('Send reset link') }}</button>
    </form>

    <p class="mt-6 text-sm"><a href="{{ route('login') }}" class="pace-link">{{ __('Back to sign in') }}</a></p>
</x-layouts::guest>
