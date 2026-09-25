<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">{{ __('Dashboard') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $entity->name }} · {{ __('Financial year') }} FY{{ substr((string) $entity->fiscalYearFor(now()), -2) }} · @localtime(now(), 'd M Y, H:i') ({{ $entity->timezone }})</p>
        </div>
    </div>

    <section class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-5" aria-label="{{ __('Key figures') }}">
        @foreach ($kpis as $kpi)
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <p class="text-sm text-slate-500">{{ $kpi }}</p>
                <p class="mt-2 text-2xl font-semibold text-slate-300">—</p>
            </div>
        @endforeach
    </section>

    <section class="mt-8 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center">
        <h2 class="font-semibold">{{ __('Welcome to PACE') }}</h2>
        <p class="mt-2 text-sm text-slate-600">{{ __('Capex requests, approvals and dashboards will appear here as they are released.') }}</p>
    </section>
</div>
