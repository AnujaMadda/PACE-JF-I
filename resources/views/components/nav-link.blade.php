@props(['active' => false])
<a {{ $attributes->merge(['class' => 'flex items-center rounded-md px-3 py-2 '.($active ? 'bg-slate-800 text-white font-medium' : 'hover:bg-slate-800 hover:text-white')]) }} @if ($active) aria-current="page" @endif>
    {{ $slot }}
</a>
