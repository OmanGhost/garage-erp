<?php

namespace App\Modules\LossPrevention\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\LossPrevention\Models\FraudAlert;
use App\Support\Audit\AuditLogger;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Fraud & loss prevention: alert dashboard, review, thresholds and audit-log integrity check. */
class LossPreventionController extends Controller
{
    public const SETTINGS = ['max_discount_percent', 'price_override_percent', 'price_drop_percent', 'large_amount', 'write_off_min',
        'adjustments_per_week', 'part_adjustments_30d', 'cash_difference_min', 'after_hours_grace_minutes'];

    protected function can(string $p): void
    {
        abort_unless(auth()->user()->can($p), 403);
    }

    public function index(Request $request)
    {
        $this->can('fraud_alerts.view');
        $status = in_array($request->query('status'), ['unreviewed', 'cleared', 'substantiated', 'all'], true) ? $request->query('status') : 'unreviewed';
        $alerts = FraudAlert::query()->with('user')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when(in_array($request->query('severity'), ['low', 'medium', 'high'], true), fn ($q) => $q->where('severity', $request->query('severity')))
            ->when(in_array($request->query('rule'), FraudAlert::RULES, true), fn ($q) => $q->where('rule', $request->query('rule')))
            ->when($request->integer('user_id'), fn ($q, $u) => $q->where('user_id', $u))
            ->orderByRaw("CASE severity WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")->latest('last_seen_at')->paginate(30)->withQueryString();
        $since = now()->subDays(30);

        return view('lossprevention::index', [
            'alerts' => $alerts, 'status' => $status,
            'stats' => [
                'open' => FraudAlert::query()->where('status', 'unreviewed')->count(),
                'high' => FraudAlert::query()->where('status', 'unreviewed')->where('severity', 'high')->count(),
                'month' => FraudAlert::query()->where('created_at', '>=', $since)->count(),
                'substantiated' => FraudAlert::query()->where('status', 'substantiated')->where('reviewed_at', '>=', $since)->count(),
                'amount' => (float) FraudAlert::query()->where('created_at', '>=', $since)->whereIn('status', ['unreviewed', 'substantiated'])->sum('amount'),
            ],
            'byRule' => FraudAlert::query()->where('created_at', '>=', $since)->selectRaw('rule, COUNT(*) n')->groupBy('rule')->orderByDesc('n')->pluck('n', 'rule'),
            'byUser' => FraudAlert::query()->with('user')->where('created_at', '>=', $since)->whereNotNull('user_id')
                ->selectRaw("user_id, COUNT(*) n, SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) h")->groupBy('user_id')->orderByDesc('h')->orderByDesc('n')->limit(8)->get(),
            'users' => User::query()->where('company_id', tenant()->id())->whereNull('customer_id')->orderBy('name')->pluck('name', 'id'),
            'settings' => collect(self::SETTINGS)->mapWithKeys(fn ($k) => [$k => setting('fraud.'.$k, config('erp.defaults')['fraud.'.$k] ?? null)])->all(),
            'integrity' => session('integrity'),
        ]);
    }

    public function show(FraudAlert $alert)
    {
        $this->can('fraud_alerts.view');

        return view('lossprevention::show', ['a' => $alert->load('user', 'reviewer'),
            'history' => FraudAlert::query()->where('user_id', $alert->user_id)->whereKeyNot($alert->id)->latest('id')->limit(10)->get()]);
    }

    public function review(Request $request, FraudAlert $alert)
    {
        $this->can('fraud_alerts.update');
        $d = $request->validate(['status' => ['required', Rule::in(['cleared', 'substantiated'])], 'review_notes' => ['required', 'string', 'min:5', 'max:2000']]);
        if ((int) $alert->user_id === (int) auth()->id()) {
            throw new BusinessException(__('You cannot review an alert about your own action.'));
        }
        $alert->forceFill(['status' => $d['status'], 'review_notes' => $d['review_notes'], 'reviewed_by' => auth()->id(), 'reviewed_at' => now()])->save();
        AuditLogger::log('fraud_alert.reviewed', $alert, ['status' => $d['status'], 'rule' => $alert->rule]);

        return redirect()->route('loss-prevention.index')->with('success', __('Alert reviewed.'));
    }

    public function saveSettings(Request $request)
    {
        $this->can('settings.update');
        $this->can('fraud_alerts.update');
        $d = $request->validate(collect(self::SETTINGS)->mapWithKeys(fn ($k) => [$k => ['required', 'numeric', 'min:0', 'max:100000']])->all());
        Settings::set(collect($d)->mapWithKeys(fn ($v, $k) => ['fraud.'.$k => (string) $v])->all());

        return back()->with('success', __('Saved.'));
    }

    public function verifyAudit()
    {
        $this->can('fraud_alerts.view');
        $r = AuditLogger::verify(tenant()->id(), now()->subDays(365));        // the last year, to stay within shared-hosting limits
        AuditLogger::log('audit_log.verified', null, ['checked' => $r['checked'], 'tampered' => count($r['tampered'])]);

        return back()->with('integrity', $r)->with($r['tampered'] ? 'error' : 'success',
            $r['tampered'] ? __(':n audit records were changed outside the application!', ['n' => count($r['tampered'])]) : __('The audit log is intact.'));
    }
}
