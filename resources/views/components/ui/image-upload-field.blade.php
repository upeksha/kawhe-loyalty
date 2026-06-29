@props([
    'id',
    'name' => null,
    'label',
    'previewUrl' => null,
    'imgClass' => 'h-20 w-20 object-contain',
    'existingLabel' => 'Current',
    'helper' => null,
    'accept' => 'image/png,image/jpeg,image/jpg,image/webp',
])

@php
    $inputName = $name ?? $id;
@endphp

<div {{ $attributes->merge(['class' => 'flex h-full min-w-0 flex-col']) }}>
    <span class="mb-2 block min-h-[2.5rem] text-sm font-medium leading-snug text-stone-700">{{ $label }}</span>
    <label
        for="{{ $id }}"
        data-image-upload
        data-existing-label="{{ $existingLabel }}"
        data-has-preview="{{ $previewUrl ? 'true' : 'false' }}"
        class="group flex h-44 shrink-0 cursor-pointer flex-col rounded-2xl border-2 border-dashed border-stone-300 bg-stone-50/60 p-4 transition hover:border-stone-400 hover:bg-stone-50 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/20"
    >
        <div class="flex h-full flex-col items-center text-center">
            <div data-existing @class(['flex min-h-0 w-full flex-1 flex-col items-center justify-center', 'hidden' => ! $previewUrl])>
                <p class="mb-1 text-xs font-medium text-stone-500">{{ $existingLabel }}</p>
                <div class="flex h-20 w-full items-center justify-center">
                    <img src="{{ $previewUrl }}" alt="{{ $label }} preview" class="{{ $imgClass }} max-h-20 max-w-full rounded-lg border border-stone-300 bg-white shadow-sm">
                </div>
            </div>

            <div data-thumbnail class="hidden min-h-0 w-full flex-1 flex-col items-center justify-center">
                <p class="mb-1 text-xs font-medium text-stone-500">New selection</p>
                <div class="flex h-20 w-full items-center justify-center">
                    <img src="" alt="{{ $label }} preview" class="{{ $imgClass }} max-h-20 max-w-full rounded-lg border border-stone-300 bg-white shadow-sm">
                </div>
            </div>

            <div data-empty @class(['flex min-h-0 w-full flex-1 flex-col items-center justify-center gap-1', 'hidden' => (bool) $previewUrl])>
                <svg class="h-8 w-8 text-stone-400 transition group-hover:text-stone-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span class="text-sm font-medium text-stone-600">Click to upload</span>
            </div>

            <p data-hint @class(['mt-1 shrink-0 text-xs leading-snug text-stone-500', 'hidden' => ! $previewUrl])>
                {{ $existingLabel }} · Click to replace
            </p>
        </div>

        <input
            type="file"
            id="{{ $id }}"
            name="{{ $inputName }}"
            accept="{{ $accept }}"
            class="sr-only"
        >
    </label>

    @if($helper)
        <p class="mt-2 min-h-[2.5rem] text-xs leading-snug text-stone-500">{{ $helper }}</p>
    @endif

    {{ $slot }}
</div>

@once
    @push('scripts')
        <script>
            document.querySelectorAll('[data-image-upload]').forEach(function(root) {
                if (root.dataset.bound === 'true') {
                    return;
                }
                root.dataset.bound = 'true';

                var input = root.querySelector('input[type="file"]');
                var existing = root.querySelector('[data-existing]');
                var thumbnail = root.querySelector('[data-thumbnail]');
                var thumbImg = thumbnail?.querySelector('img');
                var empty = root.querySelector('[data-empty]');
                var hint = root.querySelector('[data-hint]');
                var existingLabel = root.dataset.existingLabel || 'Current';
                var hasPreview = root.dataset.hasPreview === 'true';

                input?.addEventListener('change', function(e) {
                    if (e.target.files?.[0]) {
                        if (thumbImg) {
                            thumbImg.src = URL.createObjectURL(e.target.files[0]);
                        }
                        thumbnail?.classList.remove('hidden');
                        thumbnail?.classList.add('flex');
                        existing?.classList.add('hidden');
                        empty?.classList.add('hidden');
                        if (hint) {
                            hint.textContent = 'New selection · Click to replace';
                            hint.classList.remove('hidden');
                        }
                    } else {
                        thumbnail?.classList.add('hidden');
                        thumbnail?.classList.remove('flex');
                        if (thumbImg) {
                            thumbImg.src = '';
                        }
                        if (existing && hasPreview) {
                            existing.classList.remove('hidden');
                            if (hint) {
                                hint.textContent = existingLabel + ' · Click to replace';
                                hint.classList.remove('hidden');
                            }
                        } else {
                            existing?.classList.add('hidden');
                            empty?.classList.remove('hidden');
                            hint?.classList.add('hidden');
                        }
                    }
                });
            });
        </script>
    @endpush
@endonce
