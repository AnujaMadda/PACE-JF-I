@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-surface">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('pace.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-slate-900 antialiased">
<div class="flex min-h-full">
    <aside class="hidden w-2/5 flex-col justify-between bg-linear-to-br from-brand-100 via-pastel-lavender to-pastel-sky p-12 text-brand-950 lg:flex">
        <x-pace-logo />
        <div>
            <p class="text-3xl font-semibold leading-tight">{{ __('Payment Approval and Control Engine') }}</p>
            <p class="mt-3 text-brand-900/80">{{ __('Capex requests, approvals, purchase orders, invoices and payments in one controlled flow.') }}</p>
        </div>
        <p class="text-sm text-brand-900/60">&copy; {{ date('Y') }} JF&amp;I Packaging</p>
    </aside>

    <main class="flex flex-1 items-center justify-center px-6 py-12">
        <div class="w-full max-w-sm">
            <div class="mb-8 lg:hidden"><x-pace-logo /></div>

            @if (session('status'))
                <div role="status" class="mb-6 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-900">
                    {{ session('status') }}
                </div>
            @endif

            {{ $slot }}
        </div>
    </main>
</div>
</body>
</html>
