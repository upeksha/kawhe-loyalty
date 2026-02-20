<?php

namespace App\Filament\Merchant\Resources\Customers\Pages;

use App\Filament\Merchant\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['name'] = $this->record->customer?->name;
        $data['email'] = $this->record->customer?->email;
        $data['phone'] = $this->record->customer?->phone;
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $name = $data['name'] ?? null;
        $email = $data['email'] ?? null;
        $phone = $data['phone'] ?? null;
        unset($data['name'], $data['email'], $data['phone']);
        if ($this->record->customer) {
            $this->record->customer->update(array_filter([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
            ]));
        }
        return $data;
    }
}
