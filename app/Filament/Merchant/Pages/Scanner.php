<?php

namespace App\Filament\Merchant\Pages;

use Filament\Pages\Page;

class Scanner extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    protected string $view = 'filament.merchant.pages.scanner';

    public static function getNavigationLabel(): string
    {
        return 'Scanner';
    }
}
