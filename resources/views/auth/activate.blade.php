<x-layouts::guest :title="__('Set your password')">
    <h1 class="text-2xl font-semibold">{{ __('Set your password') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('Welcome, :name. Choose a password for :email.', ['name' => $user->name, 'email' => $user->email]) }}</p>

    <form method="POST" action="{{ $action }}" class="mt-8 space-y-5" novalidate>
        @csrf
        @include('auth.partials.password-fields')
        <button type="submit" class="pace-button">{{ __('Activate account') }}</button>
    </form>
</x-layouts::guest>
