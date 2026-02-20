<?php

namespace App\Filament\Merchant\Resources\Customers\Pages;

use App\Filament\Merchant\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;
}
