<?php

namespace App\Filament\Merchant\Resources\Customers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.name')
                    ->label('Name')
                    ->searchable(),
                TextColumn::make('customer.email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('store.name')
                    ->label('Store')
                    ->searchable(),
                TextColumn::make('stamp_count')
                    ->label('Stamps')
                    ->sortable(),
                TextColumn::make('reward_balance')
                    ->label('Rewards')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
