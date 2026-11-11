@php
    $launch = app(\App\Support\MerchantLaunchChecklist::class)->forUser(auth()->user(), request()->route('store'));
@endphp

@if($launch && ! $launch['ready'])
    <section class="rounded-2xl border border-stone-200 bg-white px-4 py-4 sm:px-5" aria-labelledby="launch-checklist-title">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <h2 id="launch-checklist-title" class="text-sm font-semibold text-stone-900">Finish setting up {{ $launch['store']?->name ?? 'your café' }}</h2>
                <p class="mt-1 text-sm text-stone-600">Next: {{ $launch['next']['label'] }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-sm font-medium text-stone-600">{{ $launch['doneCount'] }} of 6 complete</span>
                <a href="{{ $launch['nextUrl'] }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-brand-700 px-4 text-sm font-semibold text-white hover:bg-brand-800">Continue setup</a>
            </div>
        </div>

        <progress value="{{ $launch['doneCount'] }}" max="6" class="mt-3 h-1 w-full overflow-hidden rounded-full [&::-webkit-progress-bar]:bg-stone-100 [&::-webkit-progress-value]:bg-brand-600 [&::-moz-progress-bar]:bg-brand-600" aria-label="Café launch progress">{{ $launch['doneCount'] }} of 6</progress>

    </section>
@endif
