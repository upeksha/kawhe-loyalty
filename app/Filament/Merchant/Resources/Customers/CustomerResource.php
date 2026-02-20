<?php

namespace App\Filament\Merchant\Resources\Customers;

use App\Filament\Merchant\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Merchant\Resources\Customers\Pages\EditCustomer;
use App\Filament\Merchant\Resources\Customers\Pages\ListCustomers;
use App\Filament\Merchant\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Merchant\Resources\Customers\Tables\CustomersTable;
use App\Models\LoyaltyAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerResource extends Resource
{
    protected static ?string $model = LoyaltyAccount::class;

    protected static ?string $navigationLabel = 'Customers';

    public static function getEloquentQuery(): Builder
    {
        $storeIds = auth()->user()->stores()->pluck('id');
        return parent::getEloquentQuery()->whereIn('store_id', $storeIds)->with(['customer', 'store']);
    }

    protected static ?string $modelLabel = 'Customer';

    protected static ?string $pluralModelLabel = 'Customers';

    protected static ?string $recordTitleAttribute = 'id';

    public static function getRecordTitle(?\Illuminate\Database\Eloquent\Model $record): \Illuminate\Contracts\Support\Htmlable|string|null
    {
        if (! $record) {
            return null;
        }
        return $record->customer?->name ?: $record->customer?->email ?: 'Customer #'.$record->id;
    }

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
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
