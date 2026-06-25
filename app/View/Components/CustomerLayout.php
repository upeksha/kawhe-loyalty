<?php

namespace App\View\Components;

use App\Models\LoyaltyAccount;
use App\Models\LoyaltyProgram;
use App\Models\Store;
use App\Support\ProgramBranding;
use App\Support\ProgramTheme;
use Illuminate\View\Component;
use Illuminate\View\View;

class CustomerLayout extends Component
{
    public ProgramTheme $theme;

    public string $displayTitle;

    public function __construct(
        public ?LoyaltyProgram $program = null,
        public ?Store $store = null,
        public ?LoyaltyAccount $account = null,
        public string $title = '',
        public bool $centered = false,
        public string $shell = 'default',
        public ?string $documentTitle = null,
        public ?string $manifestHref = null,
    ) {
        if ($account) {
            $this->theme = ProgramBranding::resolveFromAccount($account);
            $this->displayTitle = $account->store?->name ?? ProgramBranding::cardTitle($account->loyaltyProgram, $account->store);
        } else {
            $this->theme = ProgramBranding::resolve($program, $store);
            $this->displayTitle = ProgramBranding::cardTitle($program, $store);
        }
    }

    public function render(): View
    {
        return view('components.customer-layout');
    }
}
