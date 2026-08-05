@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand :name="null" {{ $attributes }}>
        <x-slot name="logo" class="flex h-20 items-center justify-center overflow-hidden rounded-md bg-white dark:bg-zinc-950">
            <img src="{{ asset('logo.png') }}" alt="{{ config('app.name', 'Laravel') }}" class="h-full rounded-md object-contain" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="null" {{ $attributes }}>
        <x-slot name="logo" class="flex h-14 items-center justify-center overflow-hidden rounded-md bg-white dark:bg-zinc-950">
            <img src="{{ asset('logo.png') }}" alt="{{ config('app.name', 'Laravel') }}" class="h-full rounded-md object-contain" />
        </x-slot>
    </flux:brand>
@endif