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

        /* DomPDF-safe full-page vertical centering via display:table */
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
            padding: 10mm 18mm;
        }

        /* Logo */
        .logo {
            width: 20mm;
            height: 20mm;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 6mm;
            display: block;
        }

        /* Reward title */
        .reward-title {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 32pt;
            font-weight: bold;
            color: {{ $textColor }};
            line-height: 1.1;
            margin-bottom: 3mm;
        }

        /* Store name */
        .store-name {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 14pt;
            font-weight: bold;
            color: {{ $textColor }};
            margin-bottom: 2mm;
        }

        /* Tagline */
        .tagline {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11pt;
            color: {{ $mutedColor }};
            margin-bottom: 8mm;
        }

        /* QR */
        .qr-wrap {
            display: inline-block;
            background: #ffffff;
            padding: 4mm;
            margin-bottom: 8mm;
        }
        .qr-wrap img {
            display: block;
            width: 62mm;
            height: 62mm;
        }

        /* Instructions */
        .instruction {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10pt;
            color: {{ $mutedColor }};
            line-height: 1.6;
        }

        /* Wallet badges — use table so DomPDF renders side-by-side */
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
            height: 10mm;
            width: auto;
            display: block;
        }

        /* Footer */
        .footer {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 8pt;
            color: {{ $mutedColor }};
            margin-top: 8mm;
        }
    </style>
</head>
<body>
    <div class="outer">
        <div class="middle">

            @if(!empty($logoDataUrl))
                <img src="{{ $logoDataUrl }}" alt="{{ $store->name }}" class="logo">
            @endif

            <div class="reward-title">{{ $store->reward_title ?: 'Loyalty Rewards' }}</div>
            <div class="store-name">{{ $store->name }}</div>
            <div class="tagline">Scan to join our loyalty program</div>

            <div class="qr-wrap">
                <img src="{{ $qrCodeDataUrl }}" alt="QR Code">
            </div>

            <div class="instruction">Point your camera at the QR code</div>
            <div class="instruction">Add to Apple Wallet or Google Wallet</div>

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
</body>
</html>
