@props(['title' => null])
@php
    /** @var \App\Domain\Identity\Models\User $user */
    $user = auth()->user();
    $entity = app(\App\Domain\Core\Support\CurrentEntity::class)->get();
    $switchable = $user->accessibleEntities();
    $isAdmin = $user->can('admin.access');

@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-surface">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('pace.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full font-sans text-slate-900 antialiased">
<div class="flex min-h-full">
    {{-- Sidebar --}}
    <aside class="hidden w-64 shrink-0 flex-col border-r border-slate-200 bg-sidebar text-slate-600 md:flex" aria-label="{{ __('Main navigation') }}">
        <div class="px-5 py-6 text-slate-900"><x-pace-logo /></div>

        <nav class="flex-1 space-y-6 overflow-y-auto px-3 pb-6 text-sm">
            <div>
                <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">{{ __('Dashboard') }}</x-nav-link>
            </div>

            <div>
                <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Request Management') }}</p>
                @foreach (config('pace.processes') as $key => $process)
                    @if ($process['enabled'])
                        <x-nav-link :href="route($key.'.index')" :active="request()->routeIs($key.'.*')">{{ __($process['label']) }}</x-nav-link>
                    @else
                        <span class="flex items-center justify-between gap-2 rounded-md px-3 py-2 text-slate-400" aria-disabled="true">
                            <span class="truncate">{{ __($process['label']) }}</span>
                            <span class="shrink-0 whitespace-nowrap rounded bg-pastel-lavender px-1.5 py-0.5 text-[10px] text-pastel-lavender-ink uppercase tracking-wide">{{ __('Soon') }}</span>
                        </span>
                    @endif
                @endforeach
            </div>

            <div>
                <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Payment Management') }}</p>
                <x-nav-link :href="route('payments.index')" :active="request()->routeIs('payments.*')">{{ __('Payments Tracker') }}</x-nav-link>
            </div>

            <div>
                @if ($isAdmin && $user->can('masterdata.view'))
                    <x-nav-link href="{{ url('/admin/departments') }}">{{ __('Master Data') }}</x-nav-link>
                @endif
                <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">{{ __('Reports & Analytics') }}</x-nav-link>
            </div>

            @if ($isAdmin)
                <div>
                    <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Admin') }}</p>
                    <x-nav-link href="{{ url('/admin') }}">{{ __('Admin Panel') }}</x-nav-link>
                    @if ($user->isGroupSuperAdmin())
                        <x-nav-link href="{{ url('/admin/system-settings') }}">{{ __('System Settings') }}</x-nav-link>
                    @endif
                    @can('audit.view')
                        <x-nav-link href="{{ url('/admin/audit-log') }}">{{ __('Audit Logs') }}</x-nav-link>
                    @endcan
                </div>
            @endif
        </nav>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        {{-- Header --}}
        <header class="flex items-center justify-between gap-4 border-b border-slate-200 bg-white px-6 py-3">
            <div class="flex items-center gap-3">
                <span class="md:hidden"><x-pace-logo /></span>
                @if ($switchable->count() > 1)
                    <form method="POST" action="{{ route('entity.switch') }}" class="flex items-center gap-2">
                        @csrf
                        <label for="entity-switch" class="text-sm text-slate-500">{{ __('Entity') }}</label>
                        <select id="entity-switch" name="entity_id" class="pace-input w-auto py-1.5">
                            @foreach ($switchable as $option)
                                <option value="{{ $option->id }}" @selected($option->id === $entity?->id)>{{ $option->name }} ({{ $option->code }})</option>
                            @endforeach
                        </select>
                        <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium hover:bg-slate-50">{{ __('Switch') }}</button>
                    </form>
                @else
                    <span class="rounded-full bg-brand-50 px-3 py-1 text-sm font-medium text-brand-800">{{ $entity?->name }} ({{ $entity?->code }})</span>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <span class="relative text-slate-400" title="{{ __('Notifications arrive in Phase 6') }}">
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/></svg>
                    <span class="sr-only">{{ __('Notifications') }}</span>
                </span>

                <details class="relative">
                    <summary class="flex cursor-pointer list-none items-center gap-2 rounded-lg px-2 py-1 hover:bg-slate-100">
                        <span class="flex size-8 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-800">{{ \Illuminate\Support\Str::of($user->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}</span>
                        <span class="hidden text-sm font-medium sm:block">{{ $user->name }}</span>
                    </summary>
                    <div class="absolute right-0 z-10 mt-2 w-56 rounded-lg border border-slate-200 bg-white p-1 text-sm shadow-lg">
                        <p class="truncate px-3 py-2 text-slate-500">{{ $user->email }}</p>
                        <a href="{{ route('password.change') }}" class="block rounded-md px-3 py-2 hover:bg-slate-100">{{ __('Change password') }}</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full rounded-md px-3 py-2 text-left hover:bg-slate-100">{{ __('Sign out') }}</button>
                        </form>
                    </div>
                </details>
            </div>
        </header>

        <main class="flex-1 px-6 py-8">
            @if (session('status'))
                <div role="status" class="mb-6 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-900">{{ session('status') }}</div>
            @endif

            {{ $slot }}
        </main>
    </div>
</div>
@livewireScripts
</body>
</html>
