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
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('address')
                    ->maxLength(255),
                TextInput::make('reward_target')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->default(10),
                TextInput::make('reward_title')
                    ->required()
                    ->maxLength(255)
                    ->default('Free coffee'),
                TextInput::make('brand_color')
                    ->regex('/^#[0-9A-Fa-f]{6}$/'),
                TextInput::make('background_color')
                    ->regex('/^#[0-9A-Fa-f]{6}$/'),
                FileUpload::make('logo_path')
                    ->image()
                    ->directory('logos')
                    ->disk('public'),
                FileUpload::make('pass_logo_path')
                    ->image()
                    ->directory('pass-logos')
                    ->disk('public'),
                FileUpload::make('pass_hero_image_path')
                    ->image()
                    ->directory('pass-heroes')
                    ->disk('public'),
                Toggle::make('require_verification_for_redemption')
                    ->default(false),
            ]);
    }
}
