<?php

namespace App\Modules\Workshop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Models\TestDrive;
use App\Modules\Workshop\Models\YardLocation;
use App\Modules\Workshop\Services\VehicleTrackingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Vehicle & key tracking board, scan page (QR / RFID / barcode), key tags and test-drive permits. */
class TrackingController extends Controller
{
    public function __construct(protected VehicleTrackingService $tracking) {}

    protected function can(string $p): void
    {
        abort_unless(auth()->user()->can($p), 403);
    }

    protected function staff()
    {
        return User::query()->where('company_id', tenant()->id())->whereNull('customer_id')->where('is_active', true)->orderBy('name')->pluck('name', 'id');
    }

    public function index(Request $request)
    {
        $this->can('vehicle_tracking.view');
        $cards = JobCard::query()->with('vehicle', 'customer', 'yardLocation', 'keyHolder')->whereIn('status', JobCard::OPEN_STATUSES)->orderBy('received_at')->get();

        return view('workshop::tracking.index', [
            'cards' => $cards,
            'locations' => YardLocation::query()->where('is_active', true)->orderBy('zone')->orderBy('code')->get(),
            'drives' => TestDrive::query()->with('vehicle', 'driver', 'jobCard')->whereIn('status', ['approved', 'out'])->latest('id')->get(),
            'staff' => $this->staff(),
        ]);
    }

    /** Scan box / RFID reader / phone camera: open the job card's tracking page. */
    public function scan(Request $request)
    {
        $this->can('vehicle_tracking.view');
        $code = (string) $request->validate(['code' => ['required', 'string', 'max:200']])['code'];
        $jc = $this->tracking->find($code);

        return $jc ? redirect()->route('tracking.card', [$jc, 'via' => $request->input('via', 'qr')])
            : back()->with('error', __('No open job card found for «:c».', ['c' => $code]));
    }

    public function card(Request $request, JobCard $jobCard)
    {
        $this->can('vehicle_tracking.view');
        $jobCard->load('vehicle', 'customer', 'yardLocation', 'keyHolder', 'testDrives.driver', 'trackingEvents.location', 'trackingEvents.holder', 'trackingEvents.user');

        return view('workshop::tracking.card', [
            'jc' => $jobCard, 'via' => in_array($request->query('via'), ['qr', 'rfid'], true) ? $request->query('via') : 'web',
            'locations' => YardLocation::query()->where('is_active', true)->orderBy('zone')->orderBy('code')->get(),
            'staff' => $this->staff(),
        ]);
    }

    public function action(Request $request, JobCard $jobCard, string $action)
    {
        $this->can('vehicle_tracking.update');
        abort_unless($jobCard->isOpen(), 422, __('The job card is closed.'));
        $via = in_array($request->input('via'), ['qr', 'rfid'], true) ? $request->input('via') : 'web';
        $notes = $request->input('notes');
        match ($action) {
            'move' => $this->tracking->move($jobCard, (int) $request->validate(['yard_location_id' => ['required', 'integer', Rule::exists('yard_locations', 'id')->where('company_id', tenant()->id())]])['yard_location_id'], $via, $notes),
            'key-out' => $this->tracking->keyOut($jobCard, (int) $request->validate(['holder_id' => ['required', 'integer', Rule::exists('users', 'id')->where('company_id', tenant()->id())]])['holder_id'], $via, $notes),
            'key-in' => $this->tracking->keyIn($jobCard, $request->validate(['key_hook' => ['required', 'string', 'max:30']])['key_hook'], $via, $notes),
            'tag' => $this->tracking->tag($jobCard, $request->validate(['key_tag' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-_.]+$/']])['key_tag'] ?? null),
        };

        return back()->with('success', __('Saved.'));
    }

    /** Key tag label (QR + barcode of the key tag code). */
    public function keyTag(JobCard $jobCard)
    {
        $this->can('vehicle_tracking.view');
        if (! $jobCard->key_tag) {
            $this->tracking->tag($jobCard);
        }

        return view('workshop::tracking.key-tag', ['jc' => $jobCard->load('vehicle', 'customer'), 'paper' => '80mm']);
    }

    /* ---------------- test drives ---------------- */

    public function storeDrive(Request $request, JobCard $jobCard)
    {
        $this->can('test_drives.create');
        $data = $request->validate([
            'driver_id' => ['required', 'integer', Rule::exists('users', 'id')->where('company_id', tenant()->id())],
            'purpose' => ['nullable', 'string', 'max:190'], 'route' => ['nullable', 'string', 'max:190'],
            'max_km' => ['nullable', 'integer', 'min:1', 'max:1000'], 'customer_consent' => ['nullable', 'boolean'], 'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $td = $this->tracking->permit($jobCard, $data);

        return back()->with('success', __('Test-drive permit :n issued.', ['n' => $td->number]))
            ->with('print_voucher', route('test-drives.print', $td))->with('print_label', __('Print permit'));
    }

    public function driveAction(Request $request, TestDrive $drive, string $action)
    {
        $this->can($action === 'cancel' ? 'test_drives.create' : 'test_drives.update');
        $fuel = $request->filled('fuel') ? (int) $request->validate(['fuel' => ['integer', 'min:0', 'max:100']])['fuel'] : null;
        match ($action) {
            'start' => $this->tracking->start($drive, (int) $request->validate(['odometer' => ['required', 'integer', 'min:0', 'max:9999999']])['odometer'], $fuel),
            'finish' => $this->tracking->finish($drive, (int) $request->validate(['odometer' => ['required', 'integer', 'min:0', 'max:9999999']])['odometer'], $fuel, $request->input('notes')),
            'cancel' => $this->tracking->cancel($drive),
        };
        $drive->refresh();
        $msg = $action === 'finish' && $drive->overLimit()
            ? __('Test drive returned: :k km — over the :m km limit.', ['k' => $drive->distance(), 'm' => $drive->max_km])
            : __('Saved.');

        return back()->with($action === 'finish' && $drive->overLimit() ? 'warning' : 'success', $msg);
    }

    public function printDrive(TestDrive $drive)
    {
        $this->can('test_drives.view');

        return view('workshop::tracking.test-drive-print', ['td' => $drive->load('jobCard.customer', 'vehicle', 'driver', 'authorizer'), 'paper' => 'A5']);
    }
}
