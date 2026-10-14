            @if(!empty($formConfig['first_name']['enabled']))
            <div class="customer-field">
                <label for="first_name" class="customer-card-label mb-1.5 block text-sm font-medium">First name{{ !empty($formConfig['first_name']['required']) ? '' : ' (optional)' }}</label>
                <input type="text" id="first_name" name="first_name" maxlength="255" value="{{ old('first_name') }}" class="customer-input" placeholder="First name" autocomplete="given-name" {{ !empty($formConfig['first_name']['required']) ? 'required' : '' }}>
                <x-input-error :messages="$errors->get('first_name')" class="mt-1.5 text-sm" />
            </div>
            @endif

            @if(!empty($formConfig['last_name']['enabled']))
            <div class="customer-field">
                <label for="last_name" class="customer-card-label mb-1.5 block text-sm font-medium">Last name{{ !empty($formConfig['last_name']['required']) ? '' : ' (optional)' }}</label>
                <input type="text" id="last_name" name="last_name" maxlength="255" value="{{ old('last_name') }}" class="customer-input" placeholder="Last name" autocomplete="family-name" {{ !empty($formConfig['last_name']['required']) ? 'required' : '' }}>
                <x-input-error :messages="$errors->get('last_name')" class="mt-1.5 text-sm" />
            </div>
            @endif

            @if(!empty($formConfig['phone']['enabled']))
            <div class="customer-field">
                <label for="phone" class="customer-card-label mb-1.5 block text-sm font-medium">Phone{{ !empty($formConfig['phone']['required']) ? '' : ' (optional)' }}</label>
                <input type="tel" id="phone" name="phone" maxlength="20" value="{{ old('phone') }}" class="customer-input" placeholder="Phone number" autocomplete="tel" inputmode="tel" {{ !empty($formConfig['phone']['required']) ? 'required' : '' }}>
                <x-input-error :messages="$errors->get('phone')" class="mt-1.5 text-sm" />
            </div>
            @endif

            @if(!empty($formConfig['birthday']['enabled']))
            <div class="customer-field">
                <label for="birthday" class="customer-card-label mb-1.5 block text-sm font-medium">Birthday{{ !empty($formConfig['birthday']['required']) ? '' : ' (optional)' }}</label>
                <input type="date" id="birthday" name="birthday" value="{{ old('birthday') }}" class="customer-input" autocomplete="bday" {{ !empty($formConfig['birthday']['required']) ? 'required' : '' }}>
                <x-input-error :messages="$errors->get('birthday')" class="mt-1.5 text-sm" />
            </div>
            @endif

