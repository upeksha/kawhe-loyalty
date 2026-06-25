<x-merchant-layout>
    <x-slot name="header">
        {{ __('Card QR Code') }} - {{ $program->name }}
    </x-slot>

    <div class="mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <x-ui.card class="p-8 flex flex-col items-center justify-center space-y-6">
                <div class="p-4 bg-white rounded-lg shadow-sm border border-stone-200">
                    {!! SimpleSoftwareIO\QrCode\Facades\QrCode::size(256)->generate($joinUrl) !!}
                </div>

                <div class="text-center">
                    <p class="text-sm text-stone-600">Scan to join {{ $program->name }}</p>
                    <p class="mt-1 text-xs text-stone-500">{{ $program->reward_target }} stamps for {{ $program->reward_title }}</p>
                </div>

                <div class="flex flex-col items-center gap-2 w-full">
                    <div class="flex flex-wrap items-center justify-center gap-2">
                        <form method="GET" action="{{ route('merchant.stores.programs.qr.image', [$store, $program]) }}">
                            <button type="submit" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-stone-100 text-stone-800 hover:bg-stone-200 border border-stone-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-stone-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2m4 0h-2m-4 4h6m-6-2h2m2 0h2m-4-4h2m2 0h2" />
                                </svg>
                                Download QR
                            </button>
                        </form>
                        <button type="button" onclick="downloadProgramPoster('{{ route('merchant.stores.programs.qr.pdf', [$store, $program]) }}', '{{ \Illuminate\Support\Str::slug($store->name . ' ' . $program->name) }}-join-poster.pdf')" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-brand-600 text-white hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Download Poster
                        </button>
                        <a href="{{ $joinUrl }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-stone-100 text-stone-800 hover:bg-stone-200 border border-stone-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-stone-500">
                            Test Join Page
                        </a>
                    </div>
                    <p class="text-xs text-stone-500">Use these assets to test or share this specific loyalty card.</p>
                </div>

                <div class="w-full max-w-md">
                    <label for="join-link" class="mb-2 text-sm font-medium text-stone-700 sr-only">Join Link</label>
                    <div class="flex gap-2">
                        <x-ui.input type="text" id="join-link" value="{{ $joinUrl }}" readonly class="flex-1" />
                        <x-ui.button onclick="copyToClipboard()" variant="primary" size="md" type="button">
                            Copy
                        </x-ui.button>
                    </div>
                </div>

                <x-ui.button href="{{ route('merchant.stores.programs.index', $store) }}" variant="ghost" size="sm">
                    ← Back to Cards
                </x-ui.button>
            </x-ui.card>
        </div>

        <div class="lg:col-span-1">
            <x-ui.card class="p-5">
                <h3 class="text-base font-bold text-stone-900">Card sharing checklist</h3>
                <p class="mt-1 text-sm text-stone-600">Before you print or share this QR, quickly check the card setup.</p>

                <ul class="mt-4 space-y-3 text-sm">
                    <li class="rounded-xl border border-stone-200 bg-stone-50/80 px-4 py-3">
                        <span class="font-medium text-stone-800">Reward copy</span>
                        <p class="mt-1 text-xs leading-relaxed text-stone-500">Make sure “{{ $program->reward_target }} stamps for {{ $program->reward_title }}” is the exact offer you want customers to see.</p>
                    </li>
                    <li class="rounded-xl border border-stone-200 bg-stone-50/80 px-4 py-3">
                        <span class="font-medium text-stone-800">Join page test</span>
                        <p class="mt-1 text-xs leading-relaxed text-stone-500">Open the join page once on a phone and confirm the branding, reward wording, and signup fields are clear.</p>
                    </li>
                    <li class="rounded-xl border border-stone-200 bg-stone-50/80 px-4 py-3">
                        <span class="font-medium text-stone-800">Print asset</span>
                        <p class="mt-1 text-xs leading-relaxed text-stone-500">Use the poster if staff need something ready to print near the counter.</p>
                    </li>
                </ul>

                <div class="mt-4 flex flex-wrap gap-2">
                    <x-ui.button href="{{ route('merchant.stores.programs.edit', [$store, $program]) }}" variant="secondary" size="sm">
                        Edit Card
                    </x-ui.button>
                    <x-ui.button href="{{ route('merchant.stores.programs.qr.pdf', [$store, $program, 'preview' => 1]) }}" variant="ghost" size="sm" target="_blank">
                        Open Poster Preview
                    </x-ui.button>
                </div>
            </x-ui.card>
        </div>
    </div>

    <script>
        async function downloadProgramPoster(url, filename) {
            try {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error('Download failed');
                }

                const contentType = response.headers.get('content-type') || '';
                if (!contentType.includes('application/pdf')) {
                    window.location.href = url;
                    return;
                }

                const blob = await response.blob();
                const blobUrl = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = blobUrl;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.URL.revokeObjectURL(blobUrl);
            } catch (error) {
                window.location.href = url;
            }
        }

        function copyToClipboard() {
            var copyText = document.getElementById("join-link");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(copyText.value).then(function() {
                alert("Copied the link: " + copyText.value);
            }, function(err) {
                console.error('Async: Could not copy text: ', err);
            });
        }
    </script>
</x-merchant-layout>
