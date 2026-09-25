<x-layouts::guest :title="__('Sign in')">
    <h1 class="text-2xl font-semibold">{{ __('Sign in') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('Use your company email and choose the entity you are working in.') }}</p>

    <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5" novalidate>
        @csrf

        <div>
            <label for="email" class="pace-label">{{ __('Company email') }}</label>
            <input id="email" name="email" type="email" autocomplete="username" required autofocus
                   value="{{ old('email') }}" class="pace-input" aria-describedby="email-error">
            <x-field-error name="email" />
        </div>

        <div>
            <label for="password" class="pace-label">{{ __('Password') }}</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required class="pace-input">
            <x-field-error name="password" />
        </div>

        <div>
            <label for="entity_id" class="pace-label">{{ __('Entity') }}</label>
            <select id="entity_id" name="entity_id" required class="pace-input">
                @if ($entities->count() !== 1)
                    <option value="">{{ __('Select an entity') }}</option>
                @endif
                @foreach ($entities as $entity)
                    <option value="{{ $entity->id }}" @selected((string) old('entity_id') === (string) $entity->id)>{{ $entity->name }} ({{ $entity->code }})</option>
                @endforeach
            </select>
            <x-field-error name="entity_id" />
        </div>

        <button type="submit" class="pace-button">{{ __('Sign in') }}</button>
    </form>

    <div class="mt-6 flex justify-between text-sm">
        <a href="{{ route('password.request') }}" class="pace-link">{{ __('Forgot password?') }}</a>
        <a href="{{ route('sign-up') }}" class="pace-link">{{ __('First time? Sign up') }}</a>
    </div>
</x-layouts::guest>
