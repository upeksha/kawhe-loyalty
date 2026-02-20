<?php

namespace App\Filament\Merchant\Resources\Stores\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('reward_title')
                    ->label('Reward')
                    ->searchable(),
                TextColumn::make('reward_target')
                    ->label('Target stamps')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('join_url')
                    ->label('Join URL')
                    ->url(fn ($record) => $record->join_url)
                    ->openUrlInNewTab()
                    ->limit(40)
                    ->copyable()
                    ->copyMessage('URL copied')
                    ->tooltip('Customer sign-up link'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('qr')
                    ->label('QR code')
                    ->icon(Heroicon::OutlinedQrCode)
                    ->url(fn ($record) => route('merchant.stores.qr', $record))
                    ->openUrlInNewTab(),
                Action::make('joinUrl')
                    ->label('Join URL')
                    ->icon(Heroicon::OutlinedLink)
                    ->url(fn ($record) => $record->join_url)
                    ->openUrlInNewTab(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
