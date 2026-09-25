@php($entity = app(\App\Domain\Core\Support\CurrentEntity::class)->get())
@if ($entity)
    <span class="me-3 rounded-full bg-primary-50 px-3 py-1 text-sm font-medium text-primary-700 dark:bg-primary-950 dark:text-primary-300">
        {{ $entity->name }} ({{ $entity->code }})
    </span>
@endif
