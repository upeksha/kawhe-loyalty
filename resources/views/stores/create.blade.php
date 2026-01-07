<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Store') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('stores.store') }}" class="max-w-md mx-auto">
                        @csrf

                        <!-- Name -->
                        <div class="mb-5">
                            <label for="name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Store Name</label>
                            <input type="text" id="name" name="name" value="{{ old('name') }}" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" required>
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <!-- Address -->
                        <div class="mb-5">
                            <label for="address" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Address (Optional)</label>
                            <input type="text" id="address" name="address" value="{{ old('address') }}" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                            <x-input-error :messages="$errors->get('address')" class="mt-2" />
                        </div>

                        <!-- Reward Target -->
                        <div class="mb-5">
                            <label for="reward_target" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Stamps needed for reward</label>
                            <input type="number" id="reward_target" name="reward_target" value="{{ old('reward_target', 9) }}" min="1" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" required>
                            <x-input-error :messages="$errors->get('reward_target')" class="mt-2" />
                        </div>

                        <!-- Reward Title -->
                        <div class="mb-5">
                            <label for="reward_title" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Reward Title</label>
                            <input type="text" id="reward_title" name="reward_title" value="{{ old('reward_title', 'Free coffee') }}" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" required>
                            <x-input-error :messages="$errors->get('reward_title')" class="mt-2" />
                        </div>

                        <button type="submit" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm w-full sm:w-auto px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">Create Store</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

