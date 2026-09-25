<div {{ $attributes->merge(['class' => 'flex items-center gap-3']) }}>
    <span class="flex size-10 items-center justify-center rounded-lg bg-brand-600 text-lg shadow-sm font-bold text-white" aria-hidden="true">P</span>
    <span>
        <span class="block text-xl font-bold tracking-wide">{{ config('pace.name') }}</span>
        <span class="block text-xs uppercase tracking-widest opacity-75">{{ config('pace.tagline') }}</span>
    </span>
</div>
