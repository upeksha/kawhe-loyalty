<?php

namespace App\Filament\Merchant\Resources\Customers\Pages;

use App\Filament\Merchant\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
