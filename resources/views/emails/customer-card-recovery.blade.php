<!doctype html>
<html lang="en"><body>
    <h1>Your loyalty card at {{ $storeName }}</h1>
    <p>Use the button below to open your card or finish joining. This private link expires in 20 minutes and can be used once.</p>
    <p><a href="{{ $recoveryUrl }}">Open my card</a></p>
    <p>If you did not request this email, you can ignore it. Your card and details have not been changed.</p>
</body></html>
