<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="p-6">
            <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Use the scanner below to stamp or redeem loyalty cards. Same backend as before &mdash; stamp and redeem work as usual.</p>
            <iframe
                src="{{ url('/merchant/legacy/scanner-ui?embed=1') }}"
                title="Scanner"
                class="w-full rounded-lg border border-gray-200 dark:border-gray-700"
                style="min-height: 700px;"
            ></iframe>
        </div>
    </div>
</x-filament-panels::page>
