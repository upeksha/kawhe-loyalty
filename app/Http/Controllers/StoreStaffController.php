<?php

namespace App\Http\Controllers;

use App\Mail\StoreStaffInvitationMail;
use App\Models\Store;
use App\Models\StoreStaff;
use App\Models\StoreStaffInvitation;
use App\Services\Support\SupportAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreStaffController extends Controller
{
    public function index(Request $request)
    {
        $stores = $request->user()->stores()->orderBy('name')->get();
        $ids = $stores->modelKeys();
        $members = StoreStaff::with('user')->whereIn('store_id', $ids)->get()->groupBy('store_id');
        $invitations = StoreStaffInvitation::whereIn('store_id', $ids)->get()->groupBy('store_id');

        return view('staff.index', compact('stores', 'members', 'invitations'));
    }

    public function invite(Request $request, Store $store)
    {
        $this->owner($request, $store);
        $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = Str::lower(trim($request->input('email')));
        $permissions = $this->permissions($request);
        if ($email === Str::lower($request->user()->email) || StoreStaff::where('store_id', $store->id)->whereHas('user', fn ($q) => $q->whereRaw('LOWER(email) = ?', [$email]))->exists()) {
            throw ValidationException::withMessages(['email' => 'This person already has access. Update their permissions below.']);
        }
        $token = Str::random(64);
        $invitation = StoreStaffInvitation::updateOrCreate(['store_id' => $store->id, 'email' => $email], $permissions + [
            'invited_by' => $request->user()->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7),
        ]);
        try {
            $url = rtrim(config('app.url'), '/').route('staff.invitation', ['token' => $token], false);
            Mail::to($email)->queue(new StoreStaffInvitationMail($url, $store->name));
        } catch (\Throwable $e) {
            StoreStaffInvitation::whereKey($invitation->id)->where('token_hash', hash('sha256', $token))->delete();
            throw ValidationException::withMessages(['email' => 'We could not queue the invitation. Please try again shortly.']);
        }
        $this->audit($request, $store, 'staff_invited', ['invitation_id' => $invitation->id] + $permissions);

        return back()->with('status', 'Invitation queued. Sending another invitation to the same email replaces the previous link.');
    }

    public function update(Request $request, Store $store, StoreStaff $member)
    {
        $this->owner($request, $store);
        abort_unless($member->store_id === $store->id, 404);
        $permissions = $this->permissions($request);
        $member->update($permissions);
        $this->audit($request, $store, 'staff_permissions_updated', ['staff_user_id' => $member->user_id] + $permissions);

        return back()->with('status', 'Staff permissions updated.');
    }

    public function revoke(Request $request, Store $store, StoreStaff $member)
    {
        $this->owner($request, $store);
        abort_unless($member->store_id === $store->id, 404);
        $id = $member->user_id;
        DB::transaction(function () use ($store, $member) {
            StoreStaffInvitation::where('store_id', $store->id)->where('email', Str::lower($member->user->email))->delete();
            $member->delete();
        });
        $this->audit($request, $store, 'staff_revoked', ['staff_user_id' => $id]);

        return back()->with('status', 'Staff access revoked.');
    }

    public function cancel(Request $request, Store $store, StoreStaffInvitation $invitation)
    {
        $this->owner($request, $store);
        abort_unless($invitation->store_id === $store->id, 404);
        $invitation->delete();
        $this->audit($request, $store, 'staff_invitation_cancelled', ['invitation_id' => $invitation->id]);

        return back()->with('status', 'Invitation cancelled.');
    }

    private function owner(Request $request, Store $store): void
    {
        abort_unless($store->user_id === $request->user()->id && ! $store->trashed(), 403);
    }

    private function permissions(Request $request): array
    {
        $request->validate(['can_stamp' => ['nullable', 'boolean'], 'can_redeem' => ['nullable', 'boolean']]);
        $permissions = ['can_stamp' => $request->boolean('can_stamp'), 'can_redeem' => $request->boolean('can_redeem')];
        if (! $permissions['can_stamp'] && ! $permissions['can_redeem']) {
            throw ValidationException::withMessages(['permissions' => 'Choose at least one permission, or revoke access.']);
        }

        return $permissions;
    }

    private function audit(Request $request, Store $store, string $event, array $metadata): void
    {
        app(SupportAuditService::class)->log(eventType: $event, storeId: $store->id, actorUserId: $request->user()->id, source: 'staff_management', metadata: $metadata);
    }
}
