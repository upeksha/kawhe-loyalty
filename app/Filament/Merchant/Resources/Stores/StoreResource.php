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

    /**
     * Scope queries so merchants only see and edit their own stores.
     * Super admins see all stores (handled by Store::queryForUser in policy context).
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();
        if ($user && !$user->isSuperAdmin()) {
            $query->where('user_id', $user->id);
        }
        return $query;
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
