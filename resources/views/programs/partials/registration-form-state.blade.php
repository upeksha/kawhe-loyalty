@once
    <script>
        function registrationFormConfigState(initial) {
            const base = {
                email: { enabled: true, required: true },
                first_name: { enabled: false, required: false },
                last_name: { enabled: false, required: false },
                phone: { enabled: false, required: false },
                birthday: { enabled: false, required: false },
            };

            const config = { ...base, ...initial };
            config.email = { enabled: true, required: true };

            const presets = {
                fastest: {
                    first_name: { enabled: false, required: false },
                    last_name: { enabled: false, required: false },
                    phone: { enabled: false, required: false },
                    birthday: { enabled: false, required: false },
                },
                balanced: {
                    first_name: { enabled: true, required: false },
                    last_name: { enabled: false, required: false },
                    phone: { enabled: false, required: false },
                    birthday: { enabled: true, required: false },
                },
                marketing: {
                    first_name: { enabled: true, required: true },
                    last_name: { enabled: false, required: false },
                    phone: { enabled: true, required: false },
                    birthday: { enabled: true, required: false },
                },
            };

            return {
                config,
                applyPreset(preset) {
                    Object.entries(presets[preset] || {}).forEach(([key, value]) => {
                        this.config[key] = { ...value };
                    });
                },
                get activePreset() {
                    return Object.keys(presets).find(preset =>
                        Object.entries(presets[preset]).every(([field, { enabled, required }]) => {
                            const current = this.config[field];
                            return current.enabled === enabled && (!enabled || current.required === required);
                        })
                    ) ?? null;
                },
                get optionalFieldCount() {
                    return ['first_name', 'last_name', 'phone', 'birthday'].filter((key) => this.config[key].enabled).length;
                },
                get requiredFieldCount() {
                    return ['email', 'first_name', 'last_name', 'phone', 'birthday'].filter((key) => this.config[key].required).length;
                },
                get signupFrictionLabel() {
                    if (this.requiredFieldCount <= 1 && this.optionalFieldCount <= 1) return 'Fastest';
                    if (this.requiredFieldCount <= 2 && this.optionalFieldCount <= 2) return 'Balanced';
                    return 'Heavier';
                },
            };
        }
    </script>
@endonce
