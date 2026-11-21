<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyAccount;
use App\Models\StampEvent;
use App\Models\Store;
use App\Models\StoreStaff;
use App\Services\Admin\BillingReport;
use App\Services\Admin\OperationsReport;
use App\Services\Billing\UsageService;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function __construct(private OperationsReport $report) {}

    private function page(string $view, array $data)
    {
        return response()->view('admin.operations.'.$view, $data + ['report' => $this->report, 'navigationFilters' => ['range' => $data['f']['range'], 'scope' => $data['f']['scope'], 'from' => $data['f']['from'], 'to' => $data['f']['to']]])
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'same-origin');
    }

    public function index(Request $request)
    {
        $f = $this->report->filters($request);
        $stats = $this->report->summary($f);
        $recent = $this->report->events($f)->with(['store:id,name', 'user:id,name', 'loyaltyAccount.loyaltyProgram:id,name'])->latest()->orderByDesc('id')->limit(6)->get();
        $issues = $this->report->issues($f)->latest()->limit(5)->get();
        $queue = $this->report->queueSnapshot();
        $plans = app(BillingReport::class)->plans($f);

        return $this->page('overview', compact('f', 'stats', 'recent', 'issues', 'queue', 'plans'));
    }

    public function revenue(Request $request, BillingReport $billing)
    {
        $f = $this->report->filters($request);
        $plans = $billing->plans($f);
        $revenue = $billing->revenue($f);
        $owners = $billing->owners($f)->with('subscriptions')->orderBy('name')->orderBy('id')->paginate(20)->withQueryString();
        $usage = app(UsageService::class);
        $selectedStore = $f['store_id'] ? $this->report->stores($f)->find($f['store_id']) : null;

        return $this->page('revenue', compact('f', 'plans', 'revenue', 'owners', 'usage', 'selectedStore'));
    }

    public function stores(Request $request, UsageService $usage)
    {
        $f = $this->report->filters($request);
        $query = $this->report->stores($f)->with('user.subscriptions')->withCount('loyaltyPrograms')
            ->addSelect(['issued_count' => LoyaltyAccount::selectRaw('COUNT(*)')->whereColumn('store_id', 'stores.id'),
                'last_activity' => StampEvent::selectRaw('MAX(created_at)')->whereColumn('store_id', 'stores.id')]);
        if ($f['status'] !== 'all') {
            $query->where($f['status'] === 'archived' ? fn ($q) => $q->whereNotNull('deleted_at') : fn ($q) => $q->whereNull('deleted_at'));
        }
        if ($f['q'] !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$f['q'].'%')->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$f['q'].'%')->orWhere('email', 'like', '%'.$f['q'].'%')));
        }
        $stores = $query->orderBy('name')->orderBy('id')->paginate(20)->withQueryString();
        $this->report->decorateStores($stores->getCollection(), $f);

        return $this->page('stores', compact('f', 'stores', 'usage'));
    }

    public function store(Request $request, int $store)
    {
        $f = $this->report->filters($request);
        // A classified test store remains outside customer scope, even through a direct URL.
        $store = $this->report->stores($f)->with(['user.subscriptions'])->findOrFail($store);
        $programmes = $store->loyaltyPrograms()->withCount('loyaltyAccounts')->paginate(12)->withQueryString();
        $scoped = $this->report->events($f)->where('store_id', $store->id);
        $types = (clone $scoped)->selectRaw('type, SUM(count) as quantity')->groupBy('type')->pluck('quantity', 'type');
        $accounts = LoyaltyAccount::where('store_id', $store->id);
        $metrics = ['cards' => (clone $accounts)->count(), 'customers' => (clone $accounts)->distinct()->count('customer_id'),
            'stamps' => (int) ($types['stamp'] ?? 0), 'undos' => (int) ($types['stamp_undo'] ?? 0), 'redeems' => (int) ($types['redeem'] ?? 0), 'rewards' => (int) (clone $accounts)->sum('reward_balance')];
        $recent = $scoped->with(['store:id,name', 'user:id,name', 'loyaltyAccount.loyaltyProgram:id,name'])->latest()->orderByDesc('id')->limit(12)->get();
        $issues = $this->report->issues($f)->where('store_id', $store->id)->latest()->limit(10)->get();
        $staff = StoreStaff::with('user:id,name,email')->where('store_id', $store->id)->get();
        $usage = app(UsageService::class);

        return $this->page('store', compact('f', 'store', 'programmes', 'metrics', 'recent', 'issues', 'staff', 'usage'));
    }

    public function transactions(Request $request)
    {
        $f = $this->report->filters($request);
        $query = $this->report->events($f)->with(['store:id,name', 'user:id,name', 'loyaltyAccount.loyaltyProgram:id,name']);
        if ($f['type']) {
            $query->where('type', $f['type']);
        }
        if ($f['store_id']) {
            $query->where('store_id', $f['store_id']);
        }
        if ($f['q'] !== '') {
            $query->where(fn ($q) => $q->whereHas('store', fn ($s) => $s->where('name', 'like', '%'.$f['q'].'%'))
                ->orWhereHas('user', fn ($s) => $s->where('name', 'like', '%'.$f['q'].'%'))
                ->orWhereHas('loyaltyAccount.loyaltyProgram', fn ($s) => $s->where('name', 'like', '%'.$f['q'].'%')));
        }
        $events = $query->latest()->orderByDesc('id')->paginate(30)->withQueryString();
        $selectedStore = $f['store_id'] ? $this->report->stores($f)->find($f['store_id']) : null;

        return $this->page('transactions', compact('f', 'events', 'selectedStore'));
    }

    public function issues(Request $request)
    {
        $f = $this->report->filters($request);
        $query = $this->report->issues($f);
        if ($f['store_id']) {
            $query->where('store_id', $f['store_id']);
        }
        $issues = $query->latest()->orderByDesc('id')->paginate(20)->withQueryString();
        $queue = $this->report->queueSnapshot();
        $setup = $this->report->stores($f)->with('user:id,name,email,email_verified_at')->whereNull('deleted_at')
            ->where(fn ($q) => $q->where(fn ($s) => $s->whereNotNull('onboarding_step')->whereNull('onboarding_completed_at'))->orWhereHas('user', fn ($u) => $u->whereNull('email_verified_at')))
            ->latest()->limit(15)->get();

        return $this->page('issues', compact('f', 'issues', 'queue', 'setup'));
    }
}
