<?php

namespace App\Http\Controllers;

use App\Mail\ConfirmCustomerReplyTo;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class StoreCustomerEmailController extends Controller
{
    public function update(Request $request, Store $store)
    {
        Gate::authorize('update', $store);

        $validated = $request->validateWithBag('customerEmail', [
            'welcome_note' => ['nullable', 'string', 'max:400'],
            'reply_to' => ['nullable', 'email:rfc', 'max:254'],
        ]);

        $replyTo = $validated['reply_to'] ?? null;
        $token = DB::transaction(function () use ($store, $validated, $replyTo) {
            $locked = Store::query()->lockForUpdate()->findOrFail($store->id);
            $locked->customer_welcome_note = $validated['welcome_note'] ?? null;
            $token = null;

            if (! $replyTo || strcasecmp($replyTo, $locked->customer_reply_to ?? '') === 0) {
                $locked->customer_reply_to = $replyTo ? $locked->customer_reply_to : null;
                $locked->pending_customer_reply_to = null;
                $locked->customer_reply_to_token_hash = null;
            } else {
                $token = Str::random(64);
                $locked->pending_customer_reply_to = $replyTo;
                $locked->customer_reply_to_token_hash = hash('sha256', $token);
            }

            $locked->save();

            return $token;
        });

        $response = redirect(route('merchant.stores.edit', $store).'#customer-emails');
        if (! $token) {
            return $response->with('customer_email_status', 'Customer email settings saved.');
        }

        $url = URL::temporarySignedRoute('store.customer-email.confirm', now()->addHour(), [
            'store' => $store->id,
            'token' => $token,
        ], absolute: false);

        try {
            Mail::to($replyTo)->queue(new ConfirmCustomerReplyTo($store->name, rtrim(config('app.url'), '/').$url));
        } catch (\Throwable) {
            Store::whereKey($store->id)->where('customer_reply_to_token_hash', hash('sha256', $token))
                ->update(['pending_customer_reply_to' => null, 'customer_reply_to_token_hash' => null]);

            return $response->withErrors(['reply_to' => 'The confirmation email could not be queued. Enter the address again to retry.'], 'customerEmail')
                ->withInput();
        }

        return $response->with('customer_email_status', 'Settings saved. Check the new address for a confirmation email.');
    }

    public function preview(Store $store)
    {
        Gate::authorize('update', $store);

        return response()->view('emails.customer-welcome', [
            'customerName' => 'there',
            'store' => $store,
            'verificationUrl' => '#email-preview',
            'isPreview' => true,
            'requiresVerification' => $store->resolvedDefaultProgram()?->require_verification_for_redemption
                ?? $store->require_verification_for_redemption ?? true,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function showConfirmation(Store $store, string $token, Request $request)
    {
        $this->assertPending($store, $token);

        return response()->view('stores.confirm-customer-reply-to', [
            'store' => $store,
            'confirmUrl' => $request->getRequestUri(),
        ])->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'private, no-store');
    }

    public function confirm(Store $store, string $token)
    {
        $this->assertPending($store, $token);

        $updated = Store::whereKey($store->id)
            ->where('customer_reply_to_token_hash', hash('sha256', $token))
            ->update([
                'customer_reply_to' => $store->pending_customer_reply_to,
                'pending_customer_reply_to' => null,
                'customer_reply_to_token_hash' => null,
            ]);

        abort_unless($updated === 1, 410);

        return redirect()->route('store.customer-email.confirmed');
    }

    private function assertPending(Store $store, string $token): void
    {
        abort_unless($store->pending_customer_reply_to
            && hash_equals($store->customer_reply_to_token_hash ?? '', hash('sha256', $token)), 410);
    }
}
