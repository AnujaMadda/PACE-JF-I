@php
    /** @var \App\Domain\Audit\Models\Activity $record */
    $record = $getRecord();
    $changes = $record->attribute_changes?->toArray() ?? [];
    $new = $changes['attributes'] ?? [];
    $old = $changes['old'] ?? [];
    $fields = array_unique(array_merge(array_keys($old), array_keys($new)));
    $properties = $record->properties?->toArray() ?? [];
    $show = fn ($v) => is_scalar($v) || $v === null ? (is_bool($v) ? ($v ? 'true' : 'false') : (string) ($v ?? '—')) : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp

<div class="space-y-6 text-sm">
    @if ($fields !== [])
        <table class="w-full table-auto border-collapse text-start">
            <thead>
                <tr class="border-b border-gray-200 dark:border-white/10">
                    <th class="py-2 pe-4 text-start font-medium">{{ __('Field') }}</th>
                    <th class="py-2 pe-4 text-start font-medium">{{ __('Before') }}</th>
                    <th class="py-2 text-start font-medium">{{ __('After') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($fields as $field)
                    <tr class="border-b border-gray-100 align-top dark:border-white/5">
                        <td class="py-2 pe-4 font-mono">{{ $field }}</td>
                        <td class="py-2 pe-4 break-all text-danger-700 dark:text-danger-400">{{ array_key_exists($field, $old) ? $show($old[$field]) : '—' }}</td>
                        <td class="py-2 break-all text-success-700 dark:text-success-400">{{ array_key_exists($field, $new) ? $show($new[$field]) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($properties !== [])
        <dl class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-[max-content_1fr]">
            @foreach ($properties as $key => $value)
                <dt class="font-mono text-gray-500">{{ $key }}</dt>
                <dd class="break-all">{{ $show($value) }}</dd>
            @endforeach
        </dl>
    @endif

    @if ($fields === [] && $properties === [])
        <p class="text-gray-500">{{ __('No field changes recorded for this event.') }}</p>
    @endif
</div>
