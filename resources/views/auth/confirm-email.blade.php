<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Verify your email – Kawhe</title>
</head>
<body style="margin:0;background:#f0f7f4;color:#1c3329;font-family:Arial,Helvetica,sans-serif;">
    <main style="max-width:440px;margin:64px auto;padding:28px;background:white;border-radius:16px;">
        <p style="font-weight:bold;">kawhe LOYALTY</p>
        <h1>Verify your email</h1>
        <p>Press the button below to confirm your email address. Opening this page does not verify it.</p>
        <form method="POST" action="{{ $action }}">
            @csrf
            <button type="submit" style="width:100%;padding:16px;border:0;border-radius:8px;background:#2d5d47;color:white;font-size:16px;cursor:pointer;">Verify my email</button>
        </form>
        <p style="font-size:14px;">If you didn’t request this, you can close this page.</p>
    </main>
</body>
</html>
