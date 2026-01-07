<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $account->store->name }} - My Card</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased bg-gray-100 dark:bg-gray-900">
        <div class="min-h-screen flex flex-col items-center pt-6 sm:pt-0">
            <div class="w-full max-w-md p-6">
                <!-- Store Branding -->
                <div class="text-center mb-8">
                    <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">{{ $account->store->name }}</h1>
                    <p class="text-gray-600 dark:text-gray-400">{{ $account->store->reward_title }}</p>
                </div>

                <!-- Digital Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden mb-8">
                    <div class="p-6 bg-gradient-to-r from-blue-600 to-blue-800 text-white text-center">
                        <div class="flex justify-center mb-4 p-4 bg-white rounded-lg inline-block mx-auto">
                            {!! SimpleSoftwareIO\QrCode\Facades\QrCode::size(200)->generate('LA:' . $account->public_token) !!}
                        </div>
                        <p class="text-sm opacity-90 font-mono">{{ $account->customer->name ?? 'Valued Customer' }}</p>
                        <p class="text-xs opacity-75 mt-1">Show this QR code to get stamped</p>
                    </div>

                    <!-- Progress -->
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Progress</span>
                            <span class="text-sm font-bold text-blue-600 dark:text-blue-400">{{ $account->stamp_count }} / {{ $account->store->reward_target }}</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-4 dark:bg-gray-700 mb-6">
                            @php
                                $percentage = min(100, ($account->stamp_count / $account->store->reward_target) * 100);
                            @endphp
                            <div class="bg-blue-600 h-4 rounded-full transition-all duration-500" style="width: {{ $percentage }}%"></div>
                        </div>

                        <div class="grid grid-cols-5 gap-2">
                            @for ($i = 1; $i <= $account->store->reward_target; $i++)
                                <div class="aspect-square rounded-full flex items-center justify-center text-sm font-bold
                                    {{ $i <= $account->stamp_count
                                        ? 'bg-blue-100 text-blue-800 border-2 border-blue-500'
                                        : 'bg-gray-100 text-gray-400 border-2 border-gray-200 dark:bg-gray-700 dark:border-gray-600' }}">
                                    {{ $i }}
                                </div>
                            @endfor
                        </div>
                    </div>
                </div>

                <!-- Add to Home Screen Hint -->
                <div class="bg-blue-50 dark:bg-blue-900/30 rounded-lg p-4 text-center">
                    <p class="text-sm text-blue-800 dark:text-blue-200">
                        <svg class="w-5 h-5 inline-block mr-1 -mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        <strong>Tip:</strong> Add this page to your Home Screen to access your card easily!
                    </p>
                </div>
            </div>
        </div>
    </body>
</html>

