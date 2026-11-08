<!doctype html>
<html lang="en"><body style="margin:0;background:#f7f5ef;font-family:Arial,sans-serif;color:#253d32;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:32px 16px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:auto;background:white;border-radius:16px;"><tr><td style="padding:32px;">
<p style="font-size:22px;font-weight:bold;margin:0 0 24px;">kawhe.</p>
<h1 style="font-size:24px;">{{ $alert->event === 'verified' ? 'A café has verified their email.' : 'A new café has signed up.' }}</h1>
<p><strong>Owner:</strong> {{ $alert->user->name }}<br><strong>Email:</strong> {{ $alert->user->email }}</p>
<p><strong>Café:</strong> {{ $alert->user->stores()->orderBy('id')->first()?->name ?? 'No active store' }}<br>
<strong>Event time:</strong> {{ $alert->created_at->timezone('Pacific/Auckland')->format('j M Y, g:i a T') }}</p>
@php($tags = $alert->user->signup_attribution ?? [])
<p><strong>Source:</strong> {{ $tags['utm_source'] ?? 'Unattributed' }}<br><strong>Campaign:</strong> {{ $tags['utm_campaign'] ?? 'Not recorded' }}</p>
<p>{{ $alert->event === 'verified' ? 'They can now continue setting up their café. Check their setup progress in your admin dashboard.' : 'Their account is created. Email verification and café setup may still be outstanding.' }}</p>
<p style="margin-top:28px;"><a href="{{ route('admin.merchants.index', ['q' => $alert->user->email]) }}" style="display:inline-block;padding:14px 22px;background:#214d3c;color:white;text-decoration:none;border-radius:8px;">View café in Kawhe Admin</a></p>
<p style="font-size:12px;color:#66756b;">This is a private operational alert for Kawhe. Signup details and campaign tags are supplied by the visitor; attribution does not prove which email was read.</p>
</td></tr></table></td></tr></table></body></html>
