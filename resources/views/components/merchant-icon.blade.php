@props(['name'])
@php
$paths = [
 'overview'=>'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
 'cards'=>'M3 5h18v14H3z M3 9h18 M7 14h4',
 'customers'=>'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M16 3a4 4 0 0 1 0 8 M22 21v-2a4 4 0 0 0-3-3.87 M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
 'activity'=>'M3 12h4l3-8 4 16 3-8h4',
 'stores'=>'M3 10h18l-2-7H5z M5 10v11h14V10 M9 21v-7h6v7',
 'team'=>'M12 3a4 4 0 1 1 0 8 4 4 0 0 1 0-8 M4 21v-2a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v2',
 'billing'=>'M3 5h18v14H3z M3 9h18 M7 15h3',
 'settings'=>'M12 8a4 4 0 1 1 0 8 4 4 0 0 1 0-8 M12 2v3 M12 19v3 M2 12h3 M19 12h3 M5 5l2 2 M17 17l2 2 M5 19l2-2 M17 7l2-2',
 'support'=>'M4 14v-3a8 8 0 0 1 16 0v3 M4 11H2v7h4v-7z M20 11h2v7h-4v-7z M20 18a4 4 0 0 1-4 4h-4',
 'scan'=>'M3 8V3h5 M16 3h5v5 M21 16v5h-5 M8 21H3v-5 M3 12h18',
 'logout'=>'M9 3H3v18h6 M9 12h12 M17 8l4 4-4 4',
];
@endphp
<svg {{ $attributes->merge(['class' => 'merchant-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['overview'] }}" /></svg>
