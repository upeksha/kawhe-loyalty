@props([
    'step' => 1,
    'totalSteps' => 5,
    'title' => '',
    'subtitle' => '',
    'backUrl' => null,
])

@php
    $stepLabels = [
        1 => 'Reward setup',
        2 => 'Card design',
        3 => 'Customer form',
        4 => 'Card ready',
        5 => 'Get started',
    ];
    $visibleStepLabels = array_slice($stepLabels, 0, $totalSteps, true);
@endphp

<div class="min-h-[calc(100vh-5rem)] bg-gradient-to-b from-stone-100 to-stone-50/80 -mx-4 sm:-mx-6 -mb-6 px-4 sm:px-6 py-8 sm:py-10">
    <div class="max-w-5xl mx-auto">
        {{-- Step progress bar --}}
        <nav class="mb-6 lg:mb-8" aria-label="Onboarding steps">
            <div class="rounded-2xl px-5 py-5 sm:px-6 sm:py-6 overflow-visible">
                <ol class="flex items-center w-full list-none p-0 m-0 gap-0">
                    @foreach ($visibleStepLabels as $i => $label)
                        @php
                            $isCompleted = $i < $step;
                            $isCurrent = $i === $step;
                        @endphp
                        <li class="flex-shrink-0 flex items-center justify-center opacity-100">
                            <span class="inline-flex w-10 h-10 shrink-0 rounded-full items-center justify-center text-sm font-semibold opacity-100
                                {{ $isCompleted ? 'bg-emerald-600 text-white' : ($isCurrent ? 'bg-brand-600 text-white ring-4 ring-brand-100' : 'bg-stone-200 text-stone-500') }}">
                                @if ($isCompleted)
                                    <svg class="w-5 h-5 opacity-100" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                @else
                                    {{ $i }}
                                @endif
                            </span>
                        </li>
                        @if ($i < $totalSteps)
                            <li class="flex-1 flex items-center min-w-[16px] px-2 sm:px-3 opacity-100" aria-hidden="true">
                                <span class="block w-full h-1 rounded-full opacity-100 {{ $isCompleted ? 'bg-emerald-600' : 'bg-stone-200' }}"></span>
                            </li>
                        @endif
                    @endforeach
                </ol>
                <p class="mt-4 text-center text-sm font-medium text-stone-600">
                    Step {{ $step }} of {{ $totalSteps }} · <span class="text-stone-800">{{ $stepLabels[$step] ?? '' }}</span>
                </p>
            </div>
        </nav>

        {{-- Main content --}}
        <x-ui.section-panel class="overflow-hidden rounded-[28px] border-stone-200/80 p-0 shadow-xl shadow-stone-200/50">
                    <div class="p-6 sm:p-8 lg:p-10">
                        <header class="mb-8">
                            @if($backUrl)
                                <div class="mb-4">
                                    <a href="{{ $backUrl }}" class="inline-flex items-center gap-2 text-sm font-medium text-stone-600 hover:text-stone-900 transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 rounded-lg px-2 py-1 -ml-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                        Back to previous step
                                    </a>
                                </div>
                            @endif
                            <h2 class="text-2xl sm:text-3xl font-bold text-stone-900 tracking-tight">{{ $title }}</h2>
                            @if($subtitle)
                                <p class="mt-3 text-base text-stone-600 leading-relaxed">{{ $subtitle }}</p>
                            @endif
                        </header>

                        <div class="onboarding-step-content">
                            {{ $slot }}
                        </div>
                    </div>

                    @isset($actions)
                        <div class="border-t border-stone-200 bg-stone-50/90 px-6 py-5 sm:px-8 lg:px-10">
                            {{ $actions }}
                        </div>
                    @endisset
                </x-ui.section-panel>
    </div>
</div>
