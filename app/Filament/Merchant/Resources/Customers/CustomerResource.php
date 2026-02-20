<?php

namespace App\Filament\Merchant\Resources\Customers;

use App\Filament\Merchant\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Merchant\Resources\Customers\Pages\EditCustomer;
use App\Filament\Merchant\Resources\Customers\Pages\ListCustomers;
use App\Filament\Merchant\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Merchant\Resources\Customers\Tables\CustomersTable;
use App\Models\Customer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
