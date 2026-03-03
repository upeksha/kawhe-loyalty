@php
    $candidateBg = $store->brand_color ?? $store->background_color ?? '#7B3F1E';
    $brand = $store->brand_color ?? '#7B3F1E';

    $hex = ltrim($candidateBg, '#');
    if (strlen($hex) === 6) {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $lum = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        $bg = $lum > 0.78 ? ($store->background_color ?? '#7B3F1E') : $candidateBg;
    } else {
        $bg = '#7B3F1E';
    }

    $textColor = '#ffffff';
    $mutedColor = '#ffffff';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $store->name }} – Join Poster</title>
    <style>
        @page {
            margin: 0;
            size: 210mm 297mm;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            width: 210mm;
            height: 297mm;
            overflow: hidden;
            background: {{ $bg }};
        }

        .outer {
            display: table;
            width: 210mm;
            height: 297mm;
            background: {{ $bg }};
        }
        .middle {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
            padding: 12mm 16mm 12mm;
        }

        .hero-image {
            width: 28mm;
            height: 28mm;
            object-fit: cover;
            border-radius: 50%;
            display: block;
            margin: 0 auto 6mm;
            background: #ffffff;
        }

        .logo {
            width: 22mm;
            height: 22mm;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 6mm;
            display: block;
            background: #ffffff;
            padding: 1.5mm;
        }

        .reward-title {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 27pt;
            font-weight: bold;
            color: {{ $textColor }};
            line-height: 1.1;
            margin-bottom: 4mm;
        }

        .store-name {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 14pt;
            font-weight: 600;
            color: {{ $textColor }};
            margin-bottom: 4mm;
        }

        .tagline {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11pt;
            color: {{ $mutedColor }};
            margin-bottom: 8mm;
        }

        .qr-wrap {
            display: inline-block;
            background: #ffffff;
            padding: 4mm;
            margin-bottom: 9mm;
        }
        .qr-wrap img {
            display: block;
            width: 82mm;
            height: 82mm;
        }

        .instruction {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10pt;
            color: {{ $textColor }};
            line-height: 1.55;
            margin-bottom: 1.5mm;
        }

        .wallet-table {
            display: table;
            margin: 6mm auto 0;
            border-collapse: collapse;
        }
        .wallet-cell {
            display: table-cell;
            padding: 0 3mm;
            vertical-align: middle;
        }
        .wallet-cell img {
            height: 12mm;
            width: auto;
            display: block;
        }

        .footer {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 8pt;
            color: {{ $textColor }};
            margin-top: 22mm;
        }
    </style>
</head>
<body>
    <div class="outer">
        <div class="middle">
            @if(!empty($logoDataUrl))
                <img src="{{ $logoDataUrl }}" alt="{{ $store->name }}" class="logo">
            @elseif(!empty($heroImageDataUrl))
                <img src="{{ $heroImageDataUrl }}" alt="{{ $store->name }} artwork" class="hero-image">
            @endif

            <div class="reward-title">{{ $store->reward_title ?: 'Loyalty Rewards' }}</div>
            <div class="store-name">{{ $store->name }}</div>
            <div class="tagline">Scan the QR code to join our loyalty program</div>

            <div class="qr-wrap">
                <img src="{{ $qrCodeDataUrl }}" alt="QR Code">
            </div>

            <div class="instruction">Save your card to your wallet. Collect stamps. Get rewards.</div>

            @if(!empty($appleWalletBadgeDataUrl) || !empty($googleWalletBadgeDataUrl))
                <div class="wallet-table">
                    @if(!empty($appleWalletBadgeDataUrl))
                        <div class="wallet-cell">
                            <img src="{{ $appleWalletBadgeDataUrl }}" alt="Add to Apple Wallet">
                        </div>
                    @endif
                    @if(!empty($googleWalletBadgeDataUrl))
                        <div class="wallet-cell">
                            <img src="{{ $googleWalletBadgeDataUrl }}" alt="Add to Google Wallet">
                        </div>
                    @endif
                </div>
            @endif

            <div class="footer">Powered by Kawhe</div>
        </div>
    </div>
</body>
</html>
