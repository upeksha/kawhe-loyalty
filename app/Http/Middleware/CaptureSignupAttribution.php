<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureSignupAttribution
{
    public function handle(Request $request, Closure $next): Response
    {
        // First tagged visit in this session, valid for at most 30 days. Never
        // retain a full referrer URL, customer-card token or arbitrary query data.
        $saved = $request->session()->get('signup_attribution');
        if (! is_array($saved) || ($saved['captured_at'] ?? 0) < now()->subDays(30)->timestamp) {
            $request->session()->forget('signup_attribution');
            $tags = [];
            foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $key) {
                $value = $request->query($key);
                if (is_string($value)) {
                    $value = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '');
                    if ($value !== '') {
                        $tags[$key] = mb_substr($value, 0, 120);
                    }
                }
            }
            if (isset($tags['utm_source']) || isset($tags['utm_campaign'])) {
                $request->session()->put('signup_attribution', [...$tags, 'captured_at' => now()->timestamp]);
            }
        }

        return $next($request);
    }
}
