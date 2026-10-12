<?php

namespace App\Http\Controllers;

use App\Models\StoreStaff;
use App\Models\StoreStaffInvitation;
use App\Models\User;
use App\Services\Support\SupportAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class StaffInvitationController extends Controller
{
    public function show(Request $request, string $token)
    {
        $invitation = $this->validInvitation($token);
        if (! $invitation) {
            return response()->view('staff.invalid', [], 410);
        }
        $existing = User::whereRaw('LOWER(email) = ?', [$invitation->email])->exists();
        if (! $request->user() || (Str::lower($request->user()->email) === $invitation->email && ! $request->user()->hasVerifiedEmail())) {
            $request->session()->put('url.intended', route('staff.invitation', ['token' => $token]));
        }

        return response()->view('staff.accept', compact('invitation', 'existing', 'token'))
            ->withHeaders(['Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer']);
    }

    public function accept(Request $request, string $token)
    {
        $user = DB::transaction(function () use ($request, $token) {
            $invitation = $this->validInvitation($token, true);
            abort_unless($invitation, 410, 'This invitation has expired or is no longer available. Ask the owner for a new one.');
            $user = $request->user();
            if ($user) {
                abort_unless(Str::lower($user->email) === $invitation->email && $user->hasVerifiedEmail(), 403, 'Sign in and verify the email address that received this invitation.');
            } else {
                abort_if(User::whereRaw('LOWER(email) = ?', [$invitation->email])->exists(), 403, 'An account with this email already exists. Sign in to accept the invitation.');
                $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'password' => ['required', 'confirmed', Password::defaults()]]);
                $user = User::create(['name' => $data['name'], 'email' => $invitation->email, 'password' => $data['password']]);
                // Possession of the private emailed invitation proves the invited address.
                $user->forceFill(['is_staff_account' => true, 'email_verified_at' => now()])->save();
            }
            abort_if($user->id === $invitation->store->user_id, 403);
            StoreStaff::firstOrCreate(['store_id' => $invitation->store_id, 'user_id' => $user->id], [
                'can_stamp' => $invitation->can_stamp, 'can_redeem' => $invitation->can_redeem,
            ]);
            app(SupportAuditService::class)->log(eventType: 'staff_invitation_accepted', storeId: $invitation->store_id, actorUserId: $user->id, source: 'staff_management');
            $invitation->delete();

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return redirect()->route('merchant.scanner')->with('status', 'You have joined the team. Your store permissions are ready.');
    }

    private function validInvitation(string $token, bool $lock = false): ?StoreStaffInvitation
    {
        $query = StoreStaffInvitation::with('store')->where('token_hash', hash('sha256', $token))->where('expires_at', '>', now());
        $invitation = ($lock ? $query->lockForUpdate() : $query)->first();

        return $invitation && $invitation->store && ! $invitation->store->trashed() && $invitation->store->user_id === $invitation->invited_by ? $invitation : null;
    }
}
