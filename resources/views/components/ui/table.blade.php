@props(['class' => ''])

<div class="overflow-hidden rounded-lg border border-stone-200 {{ $class }}">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-stone-200">
            {{ $slot }}
        </table>
    </div>
</div>
