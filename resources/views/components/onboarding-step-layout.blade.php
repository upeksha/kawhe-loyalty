@props([
    'step' => 1,
    'totalSteps' => 6,
    'store' => null,
    'title' => '',
    'subtitle' => '',
    'backUrl' => null,
    'stickyPreview' => false,
    'compactProgress' => false,
])

@php
    $stepLabels = ['Email', 'Café details', 'Create card', 'Join poster', 'Merchant app', 'Test stamp'];
    $launch = app(\App\Support\MerchantLaunchChecklist::class)->forUser(auth()->user(), $store);
@endphp

<div {{ $attributes->class('kawhe-setup-grid') }}>
    <section class="kawhe-setup-panel">
        <nav @class(['mb-5' => !$compactProgress, 'mb-3.5' => $compactProgress]) aria-label="Café launch steps">
            <ol class="grid grid-cols-3 gap-1.5 min-[480px]:grid-cols-6">
                @foreach(array_slice($stepLabels, 0, $totalSteps) as $index => $label)
                    @php
                        $isCompleted = $launch['steps'][$index]['done'] ?? false;
                        $isCurrent = !$launch['ready'] && $index + 1 === $step;
                    @endphp
                    <li aria-label="{{ $launch['steps'][$index]['label'] ?? $label }}{{ $isCompleted ? ', complete' : '' }}"
                        @if($isCurrent) aria-current="step" @endif
                        @if($isCompleted) data-complete @endif
                        class="flex min-h-10 flex-col items-center gap-1 rounded-md px-1 py-1.5 text-center text-[10px] font-semibold leading-tight {{ $isCurrent ? 'bg-brand-50 text-brand-900' : 'bg-stone-50 text-stone-600' }}">
                        <span aria-hidden="true" class="h-1 w-3 shrink-0 rounded-full {{ $isCurrent ? 'bg-brand-700' : ($isCompleted ? 'bg-brand-500' : 'bg-stone-300') }}"></span>
                        {{ $label }}
                    </li>
                @endforeach
            </ol>
            @unless($launch['ready'] || $compactProgress)
                <p class="mt-2 text-xs font-medium text-stone-600">Step {{ $step }} of {{ $totalSteps }}</p>
            @endunless
        </nav>

        <header @class(['mb-5' => !$compactProgress, 'mb-3.5' => $compactProgress])>
            <h1 class="kawhe-setup-title text-2xl font-bold tracking-tight sm:text-3xl">{{ $title }}</h1>
            @if($subtitle)
                <p class="mt-2 text-sm leading-relaxed text-stone-600">{{ $subtitle }}</p>
            @endif
        </header>

        <div class="onboarding-step-content">{{ $slot }}</div>

        @if(isset($actions) || $backUrl)
            <div class="mt-auto pt-6">
                <div class="border-t border-stone-200 pt-4">
                    @isset($actions)
                        {{ $actions }}
                    @else
                        <a href="{{ $backUrl }}" class="text-sm font-semibold text-brand-800 underline underline-offset-4">Back</a>
                    @endisset
                </div>
            </div>
        @endif
    </section>

    @isset($preview)
        <aside @class(['kawhe-setup-preview', 'kawhe-setup-preview--sticky' => $stickyPreview]) aria-label="{{ $previewLabel ?? 'Setup preview' }}">
            <div class="w-full min-w-0">{{ $preview }}</div>
        </aside>
    @endisset
</div>
