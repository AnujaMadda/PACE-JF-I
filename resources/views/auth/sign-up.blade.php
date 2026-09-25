<x-layouts::guest :title="__('Sign up')">
    <h1 class="text-2xl font-semibold">{{ __('Activate your account') }}</h1>
    <p class="mt-1 text-sm text-slate-600">
        {{ __('Your administrator creates your PACE account. Enter your company email and we will send you a link to set your password.') }}
    </p>

    <form method="POST" action="{{ route('sign-up.store') }}" class="mt-8 space-y-5" novalidate>
        @csrf

        @if ($selfRegistration)
            <div>
                <label for="name" class="pace-label">{{ __('Full name') }}</label>
                <input id="name" name="name" type="text" autocomplete="name" value="{{ old('name') }}" class="pace-input">
                <p class="mt-1 text-xs text-slate-500">{{ __('Only needed if your administrator has not created your account yet.') }}</p>
                <x-field-error name="name" />
            </div>
        @endif

        <div>
            <label for="email" class="pace-label">{{ __('Company email') }}</label>
            <input id="email" name="email" type="email" autocomplete="email" required autofocus value="{{ old('email') }}" class="pace-input">
            <x-field-error name="email" />
        </div>

        <button type="submit" class="pace-button">{{ __('Send activation link') }}</button>
    </form>

    <p class="mt-6 text-sm"><a href="{{ route('login') }}" class="pace-link">{{ __('Back to sign in') }}</a></p>
</x-layouts::guest>
