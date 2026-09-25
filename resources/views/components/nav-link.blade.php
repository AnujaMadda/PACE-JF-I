@props(['active' => false])
<a {{ $attributes->merge(['class' => 'flex items-center rounded-md px-3 py-2 '.($active ? 'bg-brand-50 text-brand-800 font-medium' : 'hover:bg-slate-50 hover:text-slate-900')]) }} @if ($active) aria-current="page" @endif>
    {{ $slot }}
</a>
