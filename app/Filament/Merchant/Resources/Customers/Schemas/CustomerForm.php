<?php

namespace App\Filament\Merchant\Resources\Customers\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('phone')
                    ->tel(),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('email_verification_token_hash')
                    ->email(),
                DateTimePicker::make('email_verification_expires_at'),
                DateTimePicker::make('email_verification_sent_at'),
            ]);
    }
}
