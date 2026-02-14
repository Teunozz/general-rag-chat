@props(['label' => null])

<label class="inline-flex items-center cursor-pointer">
    <input type="checkbox" {{ $attributes->merge(['class' => 'sr-only peer']) }}>
    <div class="relative w-9 h-5 bg-gray-200 rounded-full peer dark:bg-gray-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary/20 peer-checked:bg-primary after:content-[''] after:absolute after:top-0.5 after:start-0.5 after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full dark:border-gray-500"></div>
    @if($label)
        <span class="ms-3 text-sm">{{ $label }}</span>
    @endif
</label>
