<select {{ $attributes->merge(['class' => 'block w-full rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm text-stone-700 shadow-sm shadow-stone-200/30 focus:border-brand-300 focus:outline-none focus:ring-brand-300']) }}>
    {{ $slot }}
</select>
