<x-merchant-design-layout title="QR & poster" :subtitle="$program->name.' · '.$store->name">
    <x-slot:actions>
        <x-ui.button href="{{ route('merchant.stores.programs.index', $store) }}" variant="ghost">← Back to cards</x-ui.button>
        <x-ui.button href="{{ route('merchant.stores.programs.edit', [$store, $program]) }}" variant="secondary">Edit loyalty card</x-ui.button>
    </x-slot:actions>

    <div class="grid items-start gap-6 lg:grid-cols-2">
        <x-ui.card class="min-w-0 p-5 sm:p-6 lg:self-stretch">
            <h2 class="text-lg font-semibold text-stone-900">Your join poster</h2>
            <p class="mt-1 text-sm leading-relaxed text-stone-500">This is the A4 poster customers scan to join your card.</p>
            <div id="join-poster-preview" class="relative mx-auto mt-5 w-full max-w-[390px] overflow-hidden rounded-lg border border-stone-200 shadow-sm" style="aspect-ratio: 794 / 1123">
                <iframe src="{{ route('merchant.stores.programs.qr.pdf', [$store, $program, 'preview' => 1]) }}" title="A4 join poster preview for {{ $program->name }} at {{ $store->name }}" class="pointer-events-none absolute left-0 top-0 border-0" style="width: 794px; height: 1123px; transform-origin: top left"></iframe>
            </div>
            <p class="mt-4 text-xs text-stone-500">The downloaded PDF uses this same poster design and join QR.</p>
        </x-ui.card>

        <div class="min-w-0 space-y-5">
            <x-ui.card class="p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-stone-900">Print for your counter</h2>
                <p class="mt-2 text-sm leading-relaxed text-stone-500">Download a ready-to-print poster with your loyalty card branding and join QR.</p>
                <div class="mt-5 flex flex-wrap gap-2">
                    <x-ui.button type="button" onclick="downloadProgramPoster('{{ route('merchant.stores.programs.qr.pdf', [$store, $program]) }}', '{{ \Illuminate\Support\Str::slug($store->name . ' ' . $program->name) }}-join-poster.pdf')">Download A4 poster</x-ui.button>
                    <x-ui.button href="{{ route('merchant.stores.programs.qr.pdf', [$store, $program, 'preview' => 1]) }}" target="_blank" rel="noopener" variant="secondary">Open full size ↗</x-ui.button>
                </div>
                <p class="mt-3 text-xs text-stone-500">PDF · A4 portrait. Print and test a scan before displaying it.</p>
            </x-ui.card>

            <x-ui.card class="min-w-0 p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-stone-900">Share online</h2>
                <p class="mt-2 text-sm leading-relaxed text-stone-500">Add your join link to your website, social profile or messages.</p>
                <label for="join-link" class="mt-4 block text-sm font-medium text-stone-700">Customer join link</label>
                <div class="mt-2 space-y-2">
                    <x-ui.input id="join-link" type="text" :value="$joinUrl" readonly class="w-full min-w-0" aria-describedby="join-link-status" />
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button type="button" onclick="copyToClipboard()" variant="secondary">Copy link</x-ui.button>
                        <x-ui.button :href="$joinUrl" target="_blank" rel="noopener" variant="secondary">Open join page ↗</x-ui.button>
                    </div>
                </div>
                <p id="join-link-status" role="status" aria-live="polite" class="mt-2 min-h-5 text-xs text-stone-600"></p>
            </x-ui.card>

            <x-ui.card class="min-w-0 p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-stone-900">Making your own signage?</h2>
                <p class="mt-2 text-sm leading-relaxed text-stone-500">Use the QR image on menus, table signs or other designs.</p>
                <form class="mt-5" method="GET" data-disable-submit-loading="false" action="{{ route('merchant.stores.programs.qr.image', [$store, $program]) }}">
                    <x-ui.button type="submit" variant="secondary">Download QR image (SVG)</x-ui.button>
                </form>
            </x-ui.card>
        </div>
    </div>
    <script>
        const posterPreview = document.getElementById('join-poster-preview');
        if (posterPreview && 'ResizeObserver' in window) {
            const posterFrame = posterPreview.querySelector('iframe');
            const resizePoster = () => {
                posterFrame.style.transform = `scale(${posterPreview.clientWidth / 794})`;
            };
            new ResizeObserver(resizePoster).observe(posterPreview);
            resizePoster();
        }

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
