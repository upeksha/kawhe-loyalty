@php
    $candidateBg = $program?->brand_color ?? $program?->background_color ?? $store->brand_color ?? $store->background_color ?? '#7B3F1E';
    $brand = $program?->brand_color ?? $store->brand_color ?? '#7B3F1E';

    $hex = ltrim($candidateBg, '#');
    if (strlen($hex) === 6) {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $lum = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        $bg = $lum > 0.78 ? ($program?->background_color ?? $store->background_color ?? '#7B3F1E') : $candidateBg;
    } else {
        $bg = '#7B3F1E';
    }

    $textColor = '#ffffff';
    $mutedColor = '#ffffff';
    if (\App\Support\CoffeeCardSample::programEnabled($program)) {
        [$bg, $textColor] = \App\Support\CardDesign::colors($program);
        $mutedColor = $textColor;
    }
@endphp
@php
    $reward = ($program?->reward_title ?? $store->reward_title) ?: 'Loyalty Rewards';
    $rewardSize = mb_strlen($reward) > 110 ? 16 : (mb_strlen($reward) > 55 ? 28 : 36);
    $logo = $logoDataUrl ?: ($heroImageDataUrl ?? null);
    $logoWidth = 65;
    $logoHeight = 24;
    if ($logo && str_contains($logo, ',')) {
        $dimensions = @getimagesizefromstring(base64_decode(explode(',', $logo, 2)[1]));
        if ($dimensions && $dimensions[0] > 0 && $dimensions[1] > 0) {
            $scale = min(65 / $dimensions[0], 24 / $dimensions[1]);
            $logoWidth = round($dimensions[0] * $scale, 2);
            $logoHeight = round($dimensions[1] * $scale, 2);
        }
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $store->name }} - Join Poster</title>
    <style>
        @page { margin: 0; size: 210mm 297mm; }
        * { margin: 0; padding: 0; }
        body { font-family: 'DejaVu Sans', sans-serif; background: {{ $bg }}; color: {{ $textColor }}; }
        .poster { padding: 16mm 18mm; text-align: center; }
        .logo-panel { background: #ffffff; border-radius: 4mm; width: 82mm; margin: 0 auto 7mm; padding: 6mm 0; line-height: 1; }
        .logo { width: {{ $logoWidth }}mm; height: {{ $logoHeight }}mm; }
        .store-name { font-size: 13pt; font-weight: bold; line-height: 1.4; margin-bottom: 5mm; word-wrap: break-word; }
        .eyebrow { font-size: 10pt; letter-spacing: 2px; margin-bottom: 3mm; }
        .reward-title { font-size: {{ $rewardSize }}pt; font-weight: bold; line-height: 1.12; margin-bottom: 5mm; word-wrap: break-word; }
        .tagline { font-size: 11pt; line-height: 1.5; margin-bottom: 7mm; }
        .qr-wrap { width: 78mm; margin: 0 auto 5mm; padding: 5mm; background: #ffffff; border-radius: 4mm; }
        .qr-wrap img { display: block; width: 78mm; height: 78mm; }
        .scan-label { font-size: 18pt; font-weight: bold; margin-bottom: 3mm; }
        .instruction { font-size: 10pt; line-height: 1.5; }
        .wallet-table { margin: 7mm auto 0; border-collapse: collapse; }
        .wallet-cell { padding: 0 2mm; vertical-align: middle; }
        .wallet-cell img { height: 10mm; width: auto; }
        .footer { position: fixed; bottom: 10mm; left: 0; right: 0; text-align: center; font-size: 8pt; }
        @if(mb_strlen($reward) > 110)
        .poster { padding-top: 10mm; }
        .logo-panel { margin-bottom: 4mm; padding: 3mm 0; }
        .qr-wrap { width: 68mm; }
        .qr-wrap img { width: 68mm; height: 68mm; }
        .wallet-table { margin-top: 4mm; }
        @endif
    </style>
</head>
<body>
    <main class="poster">
        @if($logo)<div class="logo-panel"><img src="{{ $logo }}" alt="{{ $store->name }} logo" class="logo"></div>@endif
        <div class="store-name">{{ $store->name }}</div>
        <div class="eyebrow">YOUR NEXT REWARD</div>
        <h1 class="reward-title">{{ $reward }}</h1>
        <p class="tagline">{{ $posterTagline }}</p>
        <div class="qr-wrap"><img src="{{ $qrCodeDataUrl }}" alt="QR Code"></div>
        <div class="scan-label">Scan to join</div>
        <p class="instruction">Scan to join. Collect stamps. Earn rewards.</p>
        @if(!empty($appleWalletBadgeDataUrl) || !empty($googleWalletBadgeDataUrl))
            <table class="wallet-table"><tr>
                @if(!empty($appleWalletBadgeDataUrl))<td class="wallet-cell"><img src="{{ $appleWalletBadgeDataUrl }}" alt="Add to Apple Wallet"></td>@endif
                @if(!empty($googleWalletBadgeDataUrl))<td class="wallet-cell"><img src="{{ $googleWalletBadgeDataUrl }}" alt="Add to Google Wallet"></td>@endif
            </tr></table>
        @endif
    </main>
    <div class="footer">Powered by Kawhe</div>
</body>
</html>
