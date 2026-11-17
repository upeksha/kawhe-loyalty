<?php

namespace App\Console\Commands;

use App\Models\StampEvent;
use App\Models\User;
use App\Services\MerchantAcquisition;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;

class KawheAdsPilot extends Command
{
    protected $signature = 'kawhe:ads-pilot
        {--qualify= : Owner ID, manually verified as a real café acquisition}
        {--exclude= : Owner ID for a test, staff, demonstration, duplicate or bot}
        {--activate= : Stamp event ID, manually reviewed as a real customer transaction}
        {--validate= : Validate a qualified, opted-in owner event without saving it to OpenAI}
        {--send= : Send the qualified, opted-in verified registration for an owner ID}
        {--purge : Expire stored ad click references}';

    protected $description = 'Reconcile the Kawhe NZ Ads pilot using first-party acquisition records';

    public function handle(MerchantAcquisition $acquisition): int
    {
        if (! config('ads_pilot.enabled')) {
            $this->error('Kawhe Ads pilot measurement is disabled.');

            return self::FAILURE;
        }

        $selected = array_filter(['qualify', 'exclude', 'activate', 'validate', 'send', 'purge'], fn ($key) => $this->option($key));
        if (count($selected) > 1) {
            $this->error('Choose only one action.');

            return self::FAILURE;
        }

        if ($this->option('purge')) {
            $acquisition->events()->whereNotNull('encrypted_click_reference')
                ->where('occurred_at', '<', now()->subDays(30))->update(['encrypted_click_reference' => null]);

            return self::SUCCESS;
        }

        $action = array_values($selected)[0] ?? null;
        if ($action) {
            $id = filter_var($this->option($action), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (! $id) {
                return self::FAILURE;
            }

            try {
                $ok = match ($action) {
                    'qualify', 'exclude' => ($user = User::find($id)) && $acquisition->classify($user, $action === 'qualify'),
                    'activate' => ($stamp = StampEvent::with('store.user')->find($id))
                        && ($owner = $stamp->store?->user) && $acquisition->confirmActivation($owner, $stamp),
                    'validate', 'send' => $acquisition->sendVerifiedRegistration($id, $action === 'validate'),
                    default => false,
                };
            } catch (ConnectionException) {
                $this->error('Connection failed. Retry with the same owner ID to reuse its event ID.');

                return self::FAILURE;
            }

            if (! $ok) {
                $this->error('No change. Check pilot attribution, qualification, consent, event age and configuration.');

                return self::FAILURE;
            }
            $this->info('Completed '.$action.'.');

            return self::SUCCESS;
        }

        $qualified = $acquisition->events()->select('user_id')->where('event', 'qualified')
            ->whereNotIn('user_id', $acquisition->events()->select('user_id')->where('event', 'excluded'));
        $events = $acquisition->events()->whereIn('user_id', $qualified);
        $this->table(['Metric', 'Count'], [
            ['Signup starts (sessions, may include bots)', $acquisition->events()->where('event', 'signup_started')->count()],
            ['Completed registrations before review', $acquisition->events()->where('event', 'signup_completed')->count()],
            ['Qualified café registrations', (clone $events)->where('event', 'signup_completed')->count()],
            ['Verified café owners', (clone $events)->where('event', 'email_verified')->count()],
            ['Café profiles saved', (clone $events)->where('event', 'cafe_profile_created')->count()],
            ['Programmes published (cafés)', (clone $events)->where('event', 'programme_published')->count()],
            ['Activated cafés (reviewed customer stamp)', (clone $events)->where('event', 'cafe_activated')->count()],
            ['New paying cafés (distinct)', (clone $events)->where('event', 'pro_payment')->distinct()->count('user_id')],
            ['Gross paid invoices (NZD minor units)', (clone $events)->where('event', 'pro_payment')->sum('amount_minor')],
        ]);

        return self::SUCCESS;
    }
}
