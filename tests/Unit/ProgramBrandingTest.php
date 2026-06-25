<?php

use App\Support\ProgramBranding;

test('resolve uses program colors when present', function () {
    $program = new \App\Models\LoyaltyProgram([
        'brand_color' => '#FF0000',
        'background_color' => '#000000',
        'name' => 'Test Card',
    ]);

    $store = new \App\Models\Store([
        'brand_color' => '#00FF00',
        'background_color' => '#FFFFFF',
        'name' => 'Test Store',
    ]);

    $theme = ProgramBranding::resolve($program, $store);

    expect($theme->brand)->toBe('#FF0000')
        ->and($theme->bg)->toBe('#000000')
        ->and(strtoupper($theme->textOnBg))->toBe('#FFFFFF');
});

test('resolve falls back to store colors when program colors missing', function () {
    $program = new \App\Models\LoyaltyProgram(['name' => 'Test Card']);
    $store = new \App\Models\Store([
        'brand_color' => '#112233',
        'background_color' => '#AABBCC',
        'name' => 'Test Store',
    ]);

    $theme = ProgramBranding::resolve($program, $store);

    expect($theme->brand)->toBe('#112233')
        ->and($theme->bg)->toBe('#AABBCC');
});

test('resolve uses defaults for invalid hex values', function () {
    $theme = ProgramBranding::resolve(null, null);

    expect(strtoupper($theme->brand))->toBe(strtoupper(ProgramBranding::DEFAULT_BRAND))
        ->and($theme->bg)->toBe(ProgramBranding::DEFAULT_BACKGROUND);
});

test('invalid brand hex falls back to default brand not background', function () {
    $program = new \App\Models\LoyaltyProgram([
        'brand_color' => 'not-a-color',
        'background_color' => '#FFFFFF',
    ]);

    $theme = ProgramBranding::resolve($program, null);

    expect(strtoupper($theme->brand))->toBe(strtoupper(ProgramBranding::DEFAULT_BRAND))
        ->and($theme->bg)->toBe('#FFFFFF');
});

test('join input tokens stay readable on dark and light join cards', function () {
    $darkTheme = ProgramBranding::fromColors('#3D7659', '#1F2937');
    $lightBgTheme = ProgramBranding::fromColors('#3D7659', '#FFFFFF');

    expect($darkTheme->joinInputText)->toBe('#F8FAFC')
        ->and($darkTheme->joinInputBg)->not->toContain('rgba(255,255,255,0.06)')
        ->and($lightBgTheme->joinInputText)->toBe('#F8FAFC')
        ->and($lightBgTheme->joinInputBg)->not->toBe('#FFFFFF')
        ->and($lightBgTheme->joinInputBg)->not->toBe($lightBgTheme->joinInputText);
});

test('css variable block includes input tokens', function () {
    $theme = ProgramBranding::fromColors('#3D7659', '#1F2937');
    $block = $theme->cssVariableBlock('.customer-page');

    expect($block)
        ->toContain('--program-input-bg:')
        ->toContain('--program-input-text:');
});

test('card title prefers program name', function () {
    $program = new \App\Models\LoyaltyProgram([
        'name' => 'Coffee Rewards',
        'reward_title' => 'Free coffee',
    ]);
    $store = new \App\Models\Store(['name' => 'Main St Cafe']);

    expect(ProgramBranding::cardTitle($program, $store))->toBe('Coffee Rewards');
});

test('card subtitle shows store when title differs', function () {
    $program = new \App\Models\LoyaltyProgram(['name' => 'VIP Card']);
    $store = new \App\Models\Store(['name' => 'Main St Cafe']);

    expect(ProgramBranding::cardSubtitle($program, $store))->toBe('Main St Cafe');
});

test('card subtitle is null when title matches store name', function () {
    $program = new \App\Models\LoyaltyProgram(['name' => 'Main St Cafe']);
    $store = new \App\Models\Store(['name' => 'Main St Cafe']);

    expect(ProgramBranding::cardSubtitle($program, $store))->toBeNull();
});

test('loyalty card tokens derive surface colors from background', function () {
    $theme = ProgramBranding::fromColors('#3D7659', '#1F2937');

    expect($theme->cssVariables())
        ->toHaveKeys([
            'loyalty-surface',
            'loyalty-inner',
            'loyalty-divider',
            'loyalty-card-bg',
            'card-muted-on-bg',
            'brand-glow-28',
        ])
        ->and($theme->loyaltyCardBg)->toContain('linear-gradient(135deg');
});

test('resolve from account uses program colors via accessors', function () {
    $store = new \App\Models\Store([
        'name' => 'Test Store',
        'brand_color' => '#111111',
        'background_color' => '#222222',
    ]);
    $program = new \App\Models\LoyaltyProgram([
        'name' => 'VIP Card',
        'brand_color' => '#AA0000',
        'background_color' => '#000000',
    ]);
    $account = new \App\Models\LoyaltyAccount;
    $account->setRelation('store', $store);
    $account->setRelation('loyaltyProgram', $program);

    $theme = ProgramBranding::resolveFromAccount($account);

    expect($theme->brand)->toBe('#AA0000')
        ->and($theme->bg)->toBe('#000000');
});
