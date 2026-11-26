<?php

namespace App\Modules\Quality\Crud;

use App\Modules\Quality\Models\CapaAction;
use App\Modules\Quality\Models\Nonconformity;
use App\Modules\Quality\Models\QualityAudit;
use App\Modules\Quality\Models\QualitySop;
use App\Support\Crud\CrudResource;
use App\Support\Crud\Field;
use App\Support\DocumentNumber;
use App\Support\Options;
use Illuminate\Database\Eloquent\Model;

abstract class QualityResource extends CrudResource
{
    public static ?string $module = 'workshop';

    protected static function staff(): array
    {
        return \App\Models\User::query()->where('company_id', tenant()->id())->whereNull('customer_id')->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function headerActions(): array
    {
        return auth()->user()?->can('quality.view') ? [['label' => __('Quality dashboard'), 'url' => route('quality.dashboard'), 'icon' => 'bi-award']] : [];
    }
}

class QualitySopResource extends QualityResource
{
    public static string $model = QualitySop::class;
    public static string $slug = 'quality-sops';
    public static string $permission = 'quality_sops';
    public static string $icon = 'bi-journal-check';
    public static array $with = ['department', 'owner'];
    public static bool $hasAttachments = true;

    public function title(): string { return __('Standard procedures (SOP)'); }
    public function singular(): string { return __('Procedure'); }

    public function fields(): array
    {
        return [
            Field::text('code')->required()->index()->searchable()->col(3),
            Field::text('title')->label(__('Title'))->required()->index()->searchable()->col(6),
            Field::text('version')->label(__('Version'))->default('1.0')->index()->col(3),
            Field::select('department_id', fn () => Options::departments())->label(__('Department'))->relation('department', 'displayName')->index()->filterable()->col(4),
            Field::select('owner_id', fn () => static::staff())->label(__('Owner'))->relation('owner')->col(4),
            Field::select('status', ['draft' => status_label('draft'), 'active' => status_label('active'), 'obsolete' => __('Obsolete')])->default('draft')->index()->filterable()->col(4),
            Field::date('effective_date')->label(__('Effective date'))->index()->col(4),
            Field::date('review_date')->label(__('Next review'))->index()->col(4),
            Field::textarea('content')->label(__('Procedure steps')),
        ];
    }
}

class NonconformityResource extends QualityResource
{
    public static string $model = Nonconformity::class;
    public static string $slug = 'nonconformities';
    public static string $permission = 'nonconformities';
    public static string $icon = 'bi-exclamation-diamond';
    public static array $with = ['jobCard', 'supplier'];
    public static ?string $dateColumn = 'detected_at';
    public static bool $hasAttachments = true;

    public function title(): string { return __('Nonconformities (NCR)'); }
    public function singular(): string { return __('Nonconformity'); }

    public function fields(): array
    {
        $opts = fn (array $keys) => array_combine($keys, array_map('status_label', $keys));

        return [
            Field::text('number')->hideOnForm()->index()->searchable(),
            Field::text('title')->label(__('Title'))->required()->index()->searchable()->col(8),
            Field::date('detected_at')->label(__('Detected on'))->required()->default(today()->toDateString())->index()->col(4),
            Field::select('source', $opts(Nonconformity::SOURCES))->label(__('Source'))->default('internal')->index()->filterable()->col(4),
            Field::select('category', $opts(Nonconformity::CATEGORIES))->label(__('Category'))->default('workmanship')->index()->filterable()->col(4),
            Field::select('severity', $opts(['minor', 'major', 'critical']))->label(__('Severity'))->default('minor')->index()->filterable()->col(4),
            Field::lookup('job_card_id', 'job_cards', 'jobCard', 'number')->label(__('Job card'))->col(4),
            Field::lookup('supplier_id', 'suppliers', 'supplier')->label(__('Supplier'))->col(4),
            Field::lookup('part_id', 'parts', 'part', 'sku')->label(__('Part'))->col(4),
            Field::select('technician_id', fn () => static::staff())->label(__('Technician'))->col(4),
            Field::select('responsible_id', fn () => static::staff())->label(__('Responsible'))->col(4),
            Field::money('cost')->label(__('Cost of poor quality'))->col(4),
            Field::textarea('description'),
            Field::select('status', $opts(Nonconformity::STATUSES))->hideOnForm()->index()->filterable(),
        ];
    }

    public function prepare(array $data, ?Model $m): array
    {
        if (! $m) {
            $data['number'] = DocumentNumber::next('nonconformity');
            $data['status'] = 'open';
            $data['detected_by'] = auth()->id();
            $data['branch_id'] = tenant()->branchId();
        }

        return $data;
    }

    public function showUrl(Model $m): ?string { return route('quality.ncr.show', $m); }
    public function deleteBlocker(Model $m): ?string { return $m->status !== 'open' || $m->actions()->exists() ? __('Nonconformities with actions are kept for the quality record.') : null; }
}

class CapaActionResource extends QualityResource
{
    public static string $model = CapaAction::class;
    public static string $slug = 'capa-actions';
    public static string $permission = 'capa_actions';
    public static string $icon = 'bi-check2-square';
    public static array $with = ['nonconformity', 'owner'];
    public static ?string $dateColumn = 'due_date';

    public function title(): string { return __('Corrective & preventive actions (CAPA)'); }

    public function fields(): array
    {
        return [
            Field::text('ncr')->label(__('NCR'))->display(fn ($a) => '<a href="'.e(route('quality.ncr.show', $a->nonconformity_id)).'">'.e($a->nonconformity?->number).'</a>')->hideOnForm()->index(false),
            Field::select('type', ['corrective' => __('Corrective'), 'preventive' => __('Preventive')])->index()->filterable(),
            Field::text('description')->index()->searchable(),
            Field::select('owner_id', fn () => static::staff())->label(__('Owner'))->relation('owner')->index(),
            Field::date('due_date')->label(__('Due'))->index(),
            Field::select('status', array_combine($s = ['open', 'in_progress', 'done', 'verified', 'cancelled'], array_map('status_label', $s)))->index()->filterable(),
        ];
    }

    public function createUrl(): ?string { return null; }
    public function showUrl(Model $m): ?string { return route('quality.ncr.show', $m->nonconformity_id); }
    public function editUrl(Model $m): ?string { return null; }
}

class QualityAuditResource extends QualityResource
{
    public static string $model = QualityAudit::class;
    public static string $slug = 'quality-audits';
    public static string $permission = 'quality_audits';
    public static string $icon = 'bi-clipboard2-data';
    public static array $with = ['auditor'];
    public static ?string $dateColumn = 'planned_date';

    public function title(): string { return __('Internal audits'); }
    public function singular(): string { return __('Internal audit'); }

    public function fields(): array
    {
        return [
            Field::text('number')->hideOnForm()->index()->searchable(),
            Field::text('title')->label(__('Title'))->required()->index()->searchable()->col(6),
            Field::text('area')->label(__('Area / process'))->index()->filterable()->col(3),
            Field::date('planned_date')->label(__('Planned date'))->required()->default(today()->toDateString())->index()->col(3),
            Field::select('auditor_id', fn () => static::staff())->label(__('Auditor'))->relation('auditor')->index()->col(4),
            Field::select('status', array_combine($s = ['planned', 'in_progress', 'completed'], array_map('status_label', $s)))->hideOnForm()->index()->filterable(),
            Field::decimal('score')->label(__('Score %'))->hideOnForm()->index(),
            Field::textarea('checklist_text')->label(__('Audit questions'))->hideOnShow()
                ->help(__('One question per line. Saving replaces the questions of an audit that has not started.')),
        ];
    }

    public function virtualFields(): array { return ['checklist_text']; }

    public function prepare(array $data, ?Model $m): array
    {
        if (! $m) {
            $data['number'] = DocumentNumber::next('quality_audit');
            $data['status'] = 'planned';
            $data['branch_id'] = tenant()->branchId();
        }

        return $data;
    }

    public function saved(Model $m, array $data, bool $created): void
    {
        $text = trim((string) ($data['checklist_text'] ?? ''));
        if ($text === '' || $m->status !== 'planned') {
            return;
        }
        $m->update(['checklist' => collect(preg_split('/\R/u', $text))->map(fn ($l) => trim($l))->filter()->values()->map(fn ($q) => ['q' => $q, 'result' => null, 'notes' => null])->all()]);
    }

    public function showUrl(Model $m): ?string { return route('quality.audits.show', $m); }
}
