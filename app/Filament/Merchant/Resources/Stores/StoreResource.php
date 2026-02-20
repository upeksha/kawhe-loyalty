<?php

namespace App\Filament\Merchant\Resources\Stores;

use App\Filament\Merchant\Resources\Stores\Pages\CreateStore;
use App\Filament\Merchant\Resources\Stores\Pages\EditStore;
use App\Filament\Merchant\Resources\Stores\Pages\ListStores;
use App\Filament\Merchant\Resources\Stores\Schemas\StoreForm;
use App\Filament\Merchant\Resources\Stores\Tables\StoresTable;
use App\Models\Store;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        if ($user->isSuperAdmin()) {
            return parent::getEloquentQuery();
        }
        return parent::getEloquentQuery()->whereIn('id', $user->stores()->pluck('id'));
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return StoreForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StoresTable::configure($table);
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
            'index' => ListStores::route('/'),
            'create' => CreateStore::route('/create'),
            'edit' => EditStore::route('/{record}/edit'),
        ];
    }
}
