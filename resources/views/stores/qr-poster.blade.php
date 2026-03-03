@php
    $bg    = $store->background_color ?? '#7B3F1E';
    $brand = $store->brand_color ?? '#ffffff';

    $hex = ltrim($bg, '#');
    if (strlen($hex) === 6) {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $lum = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        $textColor  = $lum < 0.5 ? '#ffffff' : '#111111';
        $mutedColor = $lum < 0.5 ? '#cccccc' : '#666666';
    } else {
        $textColor  = '#ffffff';
        $mutedColor = '#cccccc';
    }
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

        /* DomPDF-safe full-page vertical layout via display:table */
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
            padding: 12mm 18mm 14mm;
        }

        .brand-panel {
            width: 100%;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 8mm;
            padding: 8mm 8mm 9mm;
        }

        .hero-image {
            width: 100%;
            height: 42mm;
            object-fit: cover;
            border-radius: 6mm;
            display: block;
            margin: 0 auto 7mm;
        }

        .logo {
            width: 24mm;
            height: 24mm;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 7mm;
            display: block;
            background: #ffffff;
            padding: 2mm;
        }

        .reward-title {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 28pt;
            font-weight: bold;
            color: {{ $textColor }};
            line-height: 1.1;
            margin-bottom: 4mm;
        }

        .store-name {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 16pt;
            font-weight: bold;
            color: {{ $textColor }};
            margin-bottom: 3mm;
        }

        .tagline {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11pt;
            color: {{ $mutedColor }};
            margin-bottom: 10mm;
        }

        .promo {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 13pt;
            line-height: 1.4;
            color: {{ $textColor }};
            margin-bottom: 10mm;
        }

        .qr-wrap {
            display: inline-block;
            background: #ffffff;
            border-radius: 6mm;
            padding: 5mm;
            margin-bottom: 10mm;
            border: 2px solid {{ $brand }};
        }
        .qr-wrap img {
            display: block;
            width: 82mm;
            height: 82mm;
        }

        .instruction {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11pt;
            color: {{ $mutedColor }};
            line-height: 1.7;
        }

        .wallet-table {
            display: table;
            margin: 10mm auto 0;
            border-collapse: collapse;
        }
        .wallet-cell {
            display: table-cell;
            padding: 0 4mm;
            vertical-align: middle;
        }
        .wallet-cell img {
            height: 11mm;
            width: auto;
            display: block;
        }

        .footer {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 8pt;
            color: {{ $mutedColor }};
            margin-top: 12mm;
        }
    </style>
</head>
<body>
    <div class="outer">
        <div class="middle">
            <div class="brand-panel">
                @if(!empty($heroImageDataUrl))
                    <img src="{{ $heroImageDataUrl }}" alt="{{ $store->name }} artwork" class="hero-image">
                @endif

                @if(!empty($logoDataUrl))
                    <img src="{{ $logoDataUrl }}" alt="{{ $store->name }}" class="logo">
                @endif

                <div class="store-name">{{ $store->name }}</div>
                <div class="reward-title">{{ $store->reward_title ?: 'Loyalty Rewards' }}</div>
                <div class="tagline">Scan to join our loyalty program</div>

                <div class="promo">{!! $promoHtml !!}</div>

                <div class="qr-wrap">
                    <img src="{{ $qrCodeDataUrl }}" alt="QR Code">
                </div>

                <div class="instruction">Point your camera at the QR code</div>
                <div class="instruction">Join instantly and add your card to Apple Wallet or Google Wallet</div>

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

                <div class="footer">Powered by Rewardly</div>
            </div>
        </div>
    </div>
</body>
</html>
