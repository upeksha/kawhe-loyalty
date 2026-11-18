<?php

use App\Models\Store;
use App\Models\User;
use App\Services\Wallet\Artwork\CardStyleArtwork;
use App\Support\CardDesign;
use Illuminate\Support\Facades\Storage;

test('test stamp step previews the saved default program instead of generic store branding', function (string $style) {
    config(['app.env' => 'testing', 'app.url' => 'https://testing.kawhe.shop', 'filesystems.assets_disk' => 'public']);
    Storage::fake('public');
    $owner = User::factory()->create();
    $store = Store::factory()->for($owner)->create([
        'name' => 'TEST Saved Design', 'card_design' => 'simple', 'background_color' => '#FFFFFF',
        'onboarding_completed_at' => now(), 'launch_poster_downloaded_at' => now(),
        'launch_merchant_app_installed_at' => now(),
    ]);
    $program = $store->resolvedDefaultProgram();
    $photo = \Illuminate\Http\UploadedFile::fake()->image('photo.png', 600, 300);
    Storage::disk('public')->put('qa-photo.png', file_get_contents($photo->getRealPath()));
    $program->update([
        'card_design' => $style, 'background_color' => '#263E30', 'card_text_color' => '#FFF4CF',
        'stamp_color' => '#FFD166', 'reward_target' => 7, 'reward_title' => 'Saved reward',
        'stamp_shape' => 'square', 'stamp_icon' => 'heart', 'reward_icon' => 'gift', 'card_pattern' => 'waves',
        'card_photo_path' => 'qa-photo.png',
    ]);
    $response = $this->actingAs($owner)->get(route('merchant.onboarding.launch.show', $store))->assertOk()
        ->assertSee('Apple Wallet')->assertSee('Google Wallet')->assertSee('Check progress')->assertSee('Continue to dashboard')
        ->assertDontSee('kawhe-setup-sample');
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//form[@id="card-design-form"]//input[@name="program_id"]')->item(0)->getAttribute('value'))->toBe((string) $program->id);
    expect($xpath->query('//form[@id="card-design-form"]//input[@name="card_design"]')->item(0)->getAttribute('value'))->toBe($style);
    $payload = ['program_id' => $program->id, 'card_design' => $style, 'background_color' => '#263E30',
        'card_text_color' => '#FFF4CF', 'stamp_color' => '#FFD166', 'reward_target' => 7, 'platform' => 'apple', 'ready' => false];
    if ($style !== 'classic') {
        $art = $this->post(route('merchant.card-design.preview'), $payload)->assertOk();
        expect($art->getContent())->toBe(app(CardStyleArtwork::class)->render(7, 4, false, 'apple', CardDesign::options($program->fresh())));
    }
})->with(['simple', 'illustrated', 'photo', 'classic']);
