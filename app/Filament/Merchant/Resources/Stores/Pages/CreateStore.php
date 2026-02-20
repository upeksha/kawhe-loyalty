<?php

namespace App\Filament\Merchant\Resources\Stores\Pages;

use App\Filament\Merchant\Resources\Stores\StoreResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStore extends CreateRecord
{
    protected static string $resource = StoreResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        return $data;
    }
}
