<x-merchant-design-layout title="QR & poster" :subtitle="$program->name.' · '.$store->name">
    <x-slot:actions>
        <x-ui.button href="{{ route('merchant.stores.programs.index', $store) }}" variant="ghost">← Back to cards</x-ui.button>
        <x-ui.button href="{{ route('merchant.stores.programs.edit', [$store, $program]) }}" variant="secondary">Edit loyalty card</x-ui.button>
    </x-slot:actions>

    <div class="grid items-start gap-6 lg:grid-cols-2">
        <x-ui.card class="min-w-0 p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-stone-900">Your customer join QR</h2>
            <p class="mt-1 text-sm leading-relaxed text-stone-500">Customers scan this code to get their own loyalty card.</p>
            <div class="mt-5 rounded-2xl border border-stone-200 bg-stone-50 px-4 py-6 text-center">
                @php($logoUrl = $program->logo_url ?? $program->pass_logo_url ?? $store->logo_url ?? $store->pass_logo_url)
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $store->name }} logo" class="mx-auto mb-3 h-14 max-w-full rounded-lg object-contain" onerror="this.hidden = true">
                @endif
                <p class="break-words text-xl font-semibold text-stone-900">{{ $store->name }}</p>
                <p class="mt-1 break-words text-sm text-stone-600">{{ $program->name }}</p>
                <div class="mx-auto my-5 w-full max-w-[240px] rounded-xl bg-white p-3 [&>svg]:h-auto [&>svg]:w-full" role="img" aria-label="QR code to join {{ $program->name }} at {{ $store->name }}">
                    {!! SimpleSoftwareIO\QrCode\Facades\QrCode::size(240)->margin(2)->generate($joinUrl) !!}
                </div>
                <p class="text-base font-semibold text-brand-700">Scan to get your loyalty card</p>
                <p class="mt-1 text-xs text-stone-500">Collect stamps. Earn rewards.</p>
            </div>
            <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-stone-500">Try the customer experience before sharing.</p>
                <x-ui.button :href="$joinUrl" target="_blank" rel="noopener" variant="secondary">Open join page ↗</x-ui.button>
            </div>
        </x-ui.card>

        <div class="min-w-0 space-y-5">
            <x-ui.card class="p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-stone-900">Print for your counter</h2>
                <p class="mt-2 text-sm leading-relaxed text-stone-500">Download a ready-to-print poster with your loyalty card branding and join QR.</p>
                <div class="mt-5 flex flex-wrap gap-2">
                    <x-ui.button type="button" onclick="downloadProgramPoster('{{ route('merchant.stores.programs.qr.pdf', [$store, $program]) }}', '{{ \Illuminate\Support\Str::slug($store->name . ' ' . $program->name) }}-join-poster.pdf')">Download A4 poster</x-ui.button>
                    <x-ui.button href="{{ route('merchant.stores.programs.qr.pdf', [$store, $program, 'preview' => 1]) }}" target="_blank" rel="noopener" variant="secondary">Preview poster ↗</x-ui.button>
                </div>
                <p class="mt-3 text-xs text-stone-500">PDF · A4 portrait. Check the preview, then print and test a scan.</p>
            </x-ui.card>

            <x-ui.card class="min-w-0 p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-stone-900">Share online</h2>
                <p class="mt-2 text-sm leading-relaxed text-stone-500">Add your join link to your website, social profile or messages.</p>
                <label for="join-link" class="mt-4 block text-sm font-medium text-stone-700">Customer join link</label>
                <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                    <x-ui.input id="join-link" type="text" :value="$joinUrl" readonly class="min-w-0 flex-1" aria-describedby="join-link-status" />
                    <x-ui.button type="button" onclick="copyToClipboard()" variant="secondary" class="shrink-0">Copy link</x-ui.button>
                </div>
                <p id="join-link-status" role="status" aria-live="polite" class="mt-2 min-h-5 text-xs text-stone-600"></p>
                <div class="mt-3 border-t border-stone-200 pt-4">
                    <h3 class="text-sm font-semibold text-stone-900">Making your own signage?</h3>
                    <p class="mt-1 text-sm text-stone-500">Use the QR image on menus, table signs or other designs.</p>
                    <form class="mt-3" method="GET" data-disable-submit-loading="false" action="{{ route('merchant.stores.programs.qr.image', [$store, $program]) }}">
                        <x-ui.button type="submit" variant="secondary">Download QR image (SVG)</x-ui.button>
                    </form>
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

        async function copyToClipboard() {
            const input = document.getElementById('join-link');
            const status = document.getElementById('join-link-status');
            try {
                await navigator.clipboard.writeText(input.value);
                status.textContent = 'Link copied. Ready to share.';
            } catch (error) {
                input.focus();
                input.select();
                input.setSelectionRange(0, input.value.length);
                status.textContent = 'Select and copy the link to share it.';
            }
        }
    </script>
</x-merchant-design-layout>
