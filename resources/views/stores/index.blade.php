<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('My Stores') }}
            </h2>
            <a href="{{ route('stores.create') }}" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800">
                Add Store
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if($stores->isEmpty())
                        <p class="text-gray-500 text-center py-4">You haven't created any stores yet.</p>
                    @else
                        <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                            <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th scope="col" class="px-6 py-3">Store Name</th>
                                        <th scope="col" class="px-6 py-3">Address</th>
                                        <th scope="col" class="px-6 py-3">Reward Target</th>
                                        <th scope="col" class="px-6 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($stores as $store)
                                        <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                            <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                                {{ $store->name }}
                                            </th>
                                            <td class="px-6 py-4">
                                                {{ $store->address ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4">
                                                {{ $store->reward_target }} stamps for {{ $store->reward_title }}
                                            </td>
                                            <td class="px-6 py-4 flex space-x-2">
                                                <a href="{{ route('stores.edit', $store) }}" class="font-medium text-blue-600 dark:text-blue-500 hover:underline">Edit</a>
                                                <a href="{{ route('stores.qr', $store) }}" class="font-medium text-green-600 dark:text-green-500 hover:underline">QR Code</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

