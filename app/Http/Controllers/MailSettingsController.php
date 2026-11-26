<?php

namespace App\Http\Controllers;

use App\Modules\Core\Models\Integration;
use App\Support\Audit\AuditLogger;
use App\Support\Mail\MailService;
use Illuminate\Http\Request;

/** Company e-mail (SMTP) settings: save, enable / disable and send a test message. */
class MailSettingsController extends Controller
{
    public function save(Request $request)
    {
        abort_unless($request->user()->can('settings.update'), 403);
        $integration = Integration::query()->firstOrNew(['provider' => 'smtp']);
        $old = $integration->credentials ?? [];
        $data = $request->validate(MailService::rules() + ['is_active' => ['nullable', 'boolean']]);
        $data['password'] = filled($data['password'] ?? null) ? $data['password'] : ($old['password'] ?? null); // blank = keep the saved password
        $integration->credentials = array_intersect_key($data, array_flip(MailService::FIELDS));
        $integration->is_active = $request->boolean('is_active');
        $integration->save();
        AuditLogger::log('settings.smtp_updated', $integration, ['host' => $data['host'], 'active' => $integration->is_active]);

        return redirect()->to(route('settings.edit').'#smtp')->with('success', __('E-mail settings saved.'));
    }

    public function test(Request $request)
    {
        abort_unless($request->user()->can('settings.update'), 403);
        $to = $request->validate(['test_to' => ['required', 'email']])['test_to'];
        $c = Integration::query()->where('provider', 'smtp')->first()?->credentials;
        if (! MailService::valid($c)) {
            return redirect()->to(route('settings.edit').'#smtp')->with('error', __('Save the SMTP settings first.'));
        }
        $r = MailService::test($c, $to);

        return redirect()->to(route('settings.edit').'#smtp')->with($r['ok'] ? 'success' : 'error', $r['message']);
    }
}
