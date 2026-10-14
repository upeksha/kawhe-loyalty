<x-merchant-design-layout title="Share your loyalty card" :subtitle="$program->name.' · '.$store->name">
    <div class="grid items-start gap-8 lg:grid-cols-2">
        <x-ui.card class="dashboard-panel min-w-0 space-y-4 p-6">
            <h2 class="text-xl font-semibold">Join QR</h2>
            <div class="max-w-full overflow-hidden" aria-label="Join QR code">{!! SimpleSoftwareIO\QrCode\Facades\QrCode::size(240)->generate($joinUrl) !!}</div>
            <div class="space-y-2 rounded-lg border border-stone-200 px-4 py-2">
                <label for="join-link" class="block text-sm font-semibold">Join link</label>
                <x-ui.input id="join-link" type="text" :value="$joinUrl" readonly />
            </div>
            <div class="grid max-w-[400px] gap-4">
                <x-ui.button type="button" onclick="copyToClipboard()" class="dashboard-button">Copy join link</x-ui.button>
                <form method="GET" data-disable-submit-loading="false" action="{{ route('merchant.stores.programs.qr.image', [$store, $program]) }}">
                    <x-ui.button type="submit" variant="secondary" class="dashboard-button w-full">Download QR image</x-ui.button>
                </form>
                <x-ui.button type="button" variant="secondary" class="dashboard-button" onclick="downloadProgramPoster('{{ route('merchant.stores.programs.qr.pdf', [$store, $program]) }}', '{{ \Illuminate\Support\Str::slug($store->name . ' ' . $program->name) }}-join-poster.pdf')">Download PDF poster</x-ui.button>
            </div>
        </x-ui.card>
        <x-ui.card class="dashboard-panel min-w-0 space-y-4 p-6">
            <h2 class="text-xl font-semibold">Counter poster preview</h2>
            <p class="break-words text-3xl font-semibold leading-snug text-brand-600 sm:text-4xl">{{ $program->reward_title }}</p>
            <p class="text-stone-600">Collect {{ $program->reward_target }} stamps for {{ $program->reward_title }}.</p>
            <div class="max-w-full overflow-hidden">{!! SimpleSoftwareIO\QrCode\Facades\QrCode::size(220)->generate($joinUrl) !!}</div>
            <p class="break-words text-xl font-semibold text-brand-600">Scan to join {{ $store->name }}</p>
            <p class="text-xs text-stone-600">Join QR · customers create their own card after signup</p>
        </x-ui.card>
    </div>
    <div class="grid max-w-[400px] gap-4">
        <x-ui.button :href="$joinUrl" target="_blank" rel="noopener" variant="secondary" class="dashboard-button">Test Join Page</x-ui.button>
        <x-ui.button href="{{ route('merchant.stores.programs.qr.pdf', [$store, $program, 'preview' => 1]) }}" target="_blank" rel="noopener" variant="secondary" class="dashboard-button">Open Poster Preview</x-ui.button>
        <x-ui.button href="{{ route('merchant.stores.programs.edit', [$store, $program]) }}" variant="ghost" class="dashboard-button">Edit Card</x-ui.button>
        <x-ui.button href="{{ route('merchant.stores.programs.index', $store) }}" variant="ghost" class="dashboard-button">Back to Cards</x-ui.button>
    </div>
    <x-ui.card class="dashboard-panel space-y-3 p-6">
        <h2 class="text-xl font-semibold">Card sharing checklist</h2>
        <p class="text-sm text-stone-600">Check the reward wording, test the join page on your phone, and use the PDF poster for printing.</p>
    </x-ui.card>
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
</x-merchant-design-layout>
