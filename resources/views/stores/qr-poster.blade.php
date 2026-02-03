<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan & Collect Rewards - {{ $store->name }}</title>
    <style>
        @page { margin: 0; size: A4 portrait; }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            width: 210mm;
            height: 297mm;
            font-family: 'Helvetica', 'Arial', sans-serif;
            background: #FBF8F4;
            color: #5C3D2E;
            overflow: hidden;
        }
        .page {
            width: 210mm;
            height: 297mm;
            padding: 0;
            background: #FBF8F4;
            text-align: center;
            overflow: hidden;
        }
        .card {
            background: #FFF9F4;
            border: 1px solid #E5DBD3;
            margin: 10mm;
            padding: 12mm 10mm 10mm;
        }
        .logo-wrap {
            margin-bottom: 6mm;
        }
        .logo {
            width: 28mm;
            height: 28mm;
            border-radius: 50%;
            object-fit: cover;
            background: #8B4513;
        }
        .logo-placeholder {
            width: 28mm;
            height: 28mm;
            border-radius: 50%;
            background: #8B4513;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .headline {
            font-family: Georgia, 'Times New Roman', serif;
            font-size: 23pt;
            font-weight: normal;
            color: #5C3D2E;
            margin: 0 0 7mm 0;
        }
        .qr-wrap {
            margin: 0 auto 5mm;
            padding: 4mm;
            background: #fff;
            display: inline-block;
            border: 1px solid #e0e0e0;
        }
        .qr-wrap img,
        .qr-wrap svg {
            display: block;
            width: 55mm;
            height: 55mm;
        }
        .instruction {
            font-size: 11.5pt;
            color: #5C3D2E;
            margin: 0 0 5mm 0;
        }
        .wallet-buttons {
            text-align: center;
            margin-bottom: 8mm;
        }
        .wallet-btn {
            display: inline-block;
            height: 14mm;
            margin: 0 3mm;
        }
        .wallet-btn img {
            height: 100%;
            width: auto;
        }
        .promo {
            background: #6A3A1F;
            color: #F5F5DC;
            padding: 5mm 8mm;
            margin: 0;
            font-family: Georgia, 'Times New Roman', serif;
            font-size: 12pt;
            font-style: italic;
            border-top: 0.6mm solid #5A2D16;
        }
        .promo u { text-decoration: underline; }
        .disclaimer {
            font-size: 8pt;
            color: #A0A0A0;
            margin: 4mm 0 0 0;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="card">
            <div class="logo-wrap">
                @if(!empty($logoDataUrl))
                    <img src="{{ $logoDataUrl }}" alt="{{ $store->name }}" class="logo">
                @else
                    <div class="logo-placeholder">{{ \Illuminate\Support\Str::limit($store->name, 12) }}</div>
                @endif
            </div>
            <h1 class="headline">Scan & Collect Rewards</h1>
            <div class="qr-wrap">
                <img src="{{ $qrCodeDataUrl }}" alt="QR Code">
            </div>
            <p class="instruction">Scan the code & Add to your Wallet</p>
            <div class="wallet-buttons">
                @if(!empty($appleWalletBadgeDataUrl))
                    <a href="{{ $joinUrl }}" class="wallet-btn"><img src="{{ $appleWalletBadgeDataUrl }}" alt="Add to Apple Wallet"></a>
                @endif
                @if(!empty($googleWalletBadgeDataUrl))
                    <a href="{{ $joinUrl }}" class="wallet-btn"><img src="{{ $googleWalletBadgeDataUrl }}" alt="Add to Google Wallet"></a>
                @endif
            </div>
            <div class="promo">{!! $promoHtml !!}</div>
            <p class="disclaimer">No spam. Unsubscribe anytime. iPhone & Android supported.</p>
        </div>
    </div>
</body>
</html>
