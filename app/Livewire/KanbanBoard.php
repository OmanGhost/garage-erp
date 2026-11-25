<?php

namespace App\Livewire;

use App\Exceptions\BusinessException;
use App\Modules\Workshop\Models\JobCard;
use App\Modules\Workshop\Services\JobCardService;
use App\Modules\Workshop\Services\JobCardWorkflow;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Workshop Kanban: drag job cards between statuses. Every move goes through
 * JobCardService::changeStatus (workflow + business rules + permissions).
 * Live refresh through Reverb ("jobcard-updated") with polling fallback.
 */
class KanbanBoard extends Component
{
    public string $search = '';
    public ?int $department = null;
    public ?int $technician = null;
    public bool $showDelivered = false;

    #[On('jobcard-updated')]
    public function refreshBoard(): void {}

    public function move(int $id, string $to): void
    {
        if (! auth()->user()->can('job_cards.change_status')) {
            $this->dispatch('erp-toast', message: __('You are not allowed to change job card status.'), type: 'danger');

            return;
        }
        $jc = JobCard::query()->findOrFail($id);
        try {
            app(JobCardService::class)->changeStatus($jc, $to, __('Moved on Kanban board'));
            $this->dispatch('erp-toast', message: $jc->number.' → '.status_label($to), type: 'success');
        } catch (BusinessException $e) {
            $this->dispatch('erp-toast', message: $e->getMessage(), type: 'danger');
        }
    }

    public function render()
    {
        $columns = JobCardWorkflow::BOARD;
        if (! $this->showDelivered) {
            $columns = array_values(array_diff($columns, [JobCard::DELIVERED]));
        }
        $term = trim($this->search);
        $cards = JobCard::query()->with(['vehicle.make', 'vehicle.vehicleModel', 'customer', 'department', 'tasks' => fn ($q) => $q->with('technician')])
            ->whereIn('status', $columns)
            ->when($term, fn ($q) => $q->where(fn ($w) => $w->where('number', 'like', "%$term%")
                ->orWhereHas('vehicle', fn ($v) => $v->where('plate_number', 'like', "%$term%"))
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$term%"))))
            ->when($this->department, fn ($q) => $q->where('department_id', $this->department))
            ->when($this->technician, fn ($q) => $q->whereHas('tasks', fn ($t) => $t->where('technician_id', $this->technician)))
            ->when($this->showDelivered, fn ($q) => $q->where(fn ($w) => $w->where('status', '!=', JobCard::DELIVERED)->orWhere('delivered_at', '>=', now()->subDays(3))))
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderBy('promised_at')->limit(500)->get()->groupBy('status');

        return view('livewire.kanban-board', [
            'columns' => $columns, 'cards' => $cards, 'colors' => JobCardWorkflow::COLORS,
            'transitions' => JobCardWorkflow::TRANSITIONS,
            'departments' => \App\Support\Options::departments(), 'technicians' => \App\Support\Options::technicians(),
        ]);
    }
}
