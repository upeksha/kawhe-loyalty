<?php

namespace App\Filament\Merchant\Resources\Stores\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class StoreForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('address'),
                TextInput::make('reward_target')
                    ->numeric(),
                TextInput::make('reward_title')
                    ->required()
                    ->default('Free coffee'),
                TextInput::make('brand_color'),
                TextInput::make('logo_path'),
                TextInput::make('background_color'),
                TextInput::make('pass_logo_path'),
                FileUpload::make('pass_hero_image_path')
                    ->image(),
                Toggle::make('require_verification_for_redemption')
                    ->required(),
                TextInput::make('join_short_code'),
            ]);
    }
}
