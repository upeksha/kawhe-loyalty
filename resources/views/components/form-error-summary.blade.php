@props([
    'formId' => null,
    'title' => 'Please fix the highlighted fields before continuing.',
    'maxItems' => 5,
])

@if ($errors->any())
    @php
        $firstErrorField = array_key_first($errors->toArray());
        $errorMessages = array_slice($errors->all(), 0, $maxItems);
        $remainingCount = max(0, count($errors->all()) - count($errorMessages));
    @endphp

    <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4" role="alert" aria-live="assertive">
        <p class="text-sm font-semibold text-red-800">{{ $title }}</p>
        <ul class="mt-2 list-disc pl-5 space-y-1 text-sm text-red-700">
            @foreach ($errorMessages as $message)
                <li>{{ $message }}</li>
            @endforeach
            @if ($remainingCount > 0)
                <li>And {{ $remainingCount }} more issue{{ $remainingCount === 1 ? '' : 's' }}.</li>
            @endif
        </ul>
    </div>

    <script>
        (() => {
            const firstErrorField = @js($firstErrorField);
            const formId = @js($formId);
            if (!firstErrorField) return;

            const scope = formId ? document.getElementById(formId) : document;
            if (!scope) return;

            const escapedId = firstErrorField.replace(/\./g, '_');
            const selectors = [
                `[name="${firstErrorField}"]`,
                `[name="${firstErrorField}[]"]`,
                `#${firstErrorField}`,
                `#${escapedId}`,
            ];

            const findTarget = () => {
                for (const selector of selectors) {
                    const candidates = Array.from(scope.querySelectorAll(selector));
                    const target = candidates.find((el) => el && el.type !== 'hidden' && !el.disabled);
                    if (target) return target;
                }
                return null;
            };

            requestAnimationFrame(() => {
                const target = findTarget();
                if (!target) return;
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                target.focus({ preventScroll: true });
            });
        })();
    </script>
@endif
