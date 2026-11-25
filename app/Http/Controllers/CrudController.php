<?php

namespace App\Http\Controllers;

use App\Http\Requests\CrudRequest;
use App\Modules\Core\Services\FileStorage;
use App\Support\Crud\CrudRegistry;
use App\Support\Crud\CrudResource;
use App\Support\Crud\Field;
use App\Support\Export\Exporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Generic controller for every CrudResource: consistent tables, forms,
 * filters, exports and permission checks across the whole system.
 */
class CrudController extends Controller
{
    protected function resource(string $slug, string $action): CrudResource
    {
        $r = CrudRegistry::resolve($slug);
        if ($r::$module && tenant()->company() && ! tenant()->company()->moduleEnabled($r::$module)) {
            abort(403, __('The :m module is not enabled in your subscription plan.', ['m' => __(config('erp.modules.'.$r::$module.'.label'))]));
        }
        abort_unless($r->can($action), 403);

        return $r;
    }

    protected function find(CrudResource $r, $id): Model
    {
        return $r->scope($r->query())->findOrFail($id);
    }

    public function index(Request $request, string $slug)
    {
        $r = $this->resource($slug, 'view');
        $query = $this->filtered($r, $request);

        if ($format = $request->query('export')) {
            abort_unless($r->can('export') || ($format === 'print' && $r->can('print')), 403);
            $cols = $r->columns();
            $rows = $query->limit(20000)->get()->map(fn ($m) => array_map(fn (Field $f) => $f->plain($m), $cols));
            \App\Support\Audit\AuditLogger::log('export.'.$slug, null, ['format' => $format, 'rows' => $rows->count()]);

            return Exporter::download($format, $r->title(), array_map(fn (Field $f) => $f->getLabel(), $cols), $rows->all(), [
                'filters' => array_filter($request->only(['q', 'from', 'to'])),
            ]);
        }

        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50, 100], true) ? (int) $request->query('per_page') : 25;
        $rows = $query->paginate($perPage)->withQueryString();

        return view('crud.index', ['resource' => $r, 'rows' => $rows, 'slug' => $slug]);
    }

    protected function filtered(CrudResource $r, Request $request): Builder
    {
        $q = $r->scope($r->query());
        $table = $q->getModel()->getTable();
        if ($term = trim((string) $request->query('q'))) {
            $cols = $r->searchColumns();
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $q->where(function ($w) use ($cols, $like, $table) {
                foreach ($cols as $c) {
                    str_contains($c, '.')
                        ? $w->orWhereHas(\Illuminate\Support\Str::before($c, '.'), fn ($x) => $x->where(\Illuminate\Support\Str::after($c, '.'), 'like', $like))
                        : $w->orWhere($table.'.'.$c, 'like', $like);
                }
            });
        }
        foreach ($r->filters() as $f) {
            $v = $request->query('f_'.$f->name);
            if ($v !== null && $v !== '') {
                $f->type === 'checkbox' ? $q->where($table.'.'.$f->name, (bool) $v) : $q->where($table.'.'.$f->name, $v);
            }
        }
        if ($r::$dateColumn) {
            if ($from = $request->query('from')) {
                $q->whereDate($table.'.'.$r::$dateColumn, '>=', $from);
            }
            if ($to = $request->query('to')) {
                $q->whereDate($table.'.'.$r::$dateColumn, '<=', $to);
            }
        }
        $sortable = array_map(fn (Field $f) => $f->name, array_filter($r->columns(), fn (Field $f) => $f->sortable && ! $f->relation && ! $f->displayUsing));
        $sort = in_array($request->query('sort'), $sortable, true) ? $request->query('sort') : $r::$defaultSort;
        $dir = $request->query('dir') === 'asc' ? 'asc' : ($request->query('dir') === 'desc' ? 'desc' : $r::$defaultDir);

        return $q->orderBy($table.'.'.$sort, $dir)->orderBy($table.'.id', 'desc');
    }

    public function create(string $slug)
    {
        $r = $this->resource($slug, 'create');
        $url = $r->createUrl();
        if (! $url) {
            // records of this type are created from their own workflow screen (e.g. receipts from a PO)
            return redirect($r->indexUrl());
        }
        if ($url !== route('crud.create', $slug)) {
            return redirect($url);
        }

        return view('crud.form', ['resource' => $r, 'model' => null, 'slug' => $slug]);
    }

    public function store(CrudRequest $request, string $slug)
    {
        $r = $this->resource($slug, 'create');
        abort_unless($r->createUrl() === route('crud.create', $slug), 403);
        $model = DB::transaction(function () use ($r, $request) {
            $data = $this->payload($r, $request, null);
            $model = new (($r::$model));
            $model->fill($r->fillable($data));
            $r->saving($model, $data, true);
            $model->save();
            $r->saved($model, $data, true);

            return $model;
        });
        $this->storeAttachments($r, $request, $model);

        return redirect($request->input('_continue') ? route('crud.create', $slug) : ($r->showUrl($model) ?? $r->indexUrl()))
            ->with('success', __('Saved successfully.'));
    }

    public function show(string $slug, $id)
    {
        $r = $this->resource($slug, 'view');
        $model = $this->find($r, $id);
        $url = $r->showUrl($model);
        if ($url && $url !== route('crud.show', [$slug, $model->getKey()])) {
            return redirect($url);
        }
        $audits = auth()->user()->can('audit_logs.view')
            ? \App\Modules\Core\Models\AuditLog::query()->where('auditable_type', $model->getMorphClass())->where('auditable_id', $model->getKey())->with('user')->latest('id')->limit(30)->get()
            : collect();

        return view('crud.show', ['resource' => $r, 'model' => $model, 'slug' => $slug, 'audits' => $audits]);
    }

    public function edit(string $slug, $id)
    {
        $r = $this->resource($slug, 'update');
        $model = $this->find($r, $id);
        abort_unless($r->canEditRecord($model), 403, __('This record can no longer be edited.'));
        $url = $r->editUrl($model);
        if (! $url) {
            return redirect($r->showUrl($model) ?? $r->indexUrl());
        }
        if ($url !== route('crud.edit', [$slug, $model->getKey()])) {
            return redirect($url);
        }

        return view('crud.form', ['resource' => $r, 'model' => $model, 'slug' => $slug]);
    }

    public function update(CrudRequest $request, string $slug, $id)
    {
        $r = $this->resource($slug, 'update');
        $model = $this->find($r, $id);
        abort_unless($r->canEditRecord($model), 403, __('This record can no longer be edited.'));
        abort_unless($r->editUrl($model) === route('crud.edit', [$slug, $model->getKey()]), 403);
        DB::transaction(function () use ($r, $request, $model) {
            $data = $this->payload($r, $request, $model);
            $model->fill($r->fillable($data));
            $r->saving($model, $data, false);
            $model->save();
            $r->saved($model, $data, false);
        });
        $this->storeAttachments($r, $request, $model);

        return redirect($r->showUrl($model) ?? $r->indexUrl())->with('success', __('Saved successfully.'));
    }

    public function destroy(string $slug, $id)
    {
        $r = $this->resource($slug, 'delete');
        $model = $this->find($r, $id);
        if ($reason = $r->deleteBlocker($model)) {
            return back()->with('error', $reason);
        }
        try {
            $model->delete();
        } catch (\Illuminate\Database\QueryException) {
            return back()->with('error', __('This record is used by other records and cannot be deleted.'));
        }

        return redirect($r->indexUrl())->with('success', __('Deleted successfully.'));
    }

    protected function payload(CrudResource $r, Request $request, ?Model $model): array
    {
        $data = [];
        foreach ($r->formFields($model) as $f) {
            if ($f->type === 'checkbox') {
                $data[$f->name] = $request->boolean($f->name);
            } elseif ($f->type === 'image') {
                if ($request->hasFile($f->name)) {
                    $data[$f->name] = FileStorage::storePublicImage($request->file($f->name), $r::$slug);
                }
            } elseif ($f->type === 'password') {
                if ($request->filled($f->name)) {
                    $data[$f->name] = $request->input($f->name);
                }
            } elseif ($request->exists($f->name)) {
                $v = $request->input($f->name);
                $data[$f->name] = $v === '' ? null : ($f->type === 'tel' ? omani_phone($v) : $v);
            }
        }

        return $this->fillNotNullDefaults($r, $r->prepare($data, $model));
    }

    /**
     * Empty optional inputs arrive as null. For NOT NULL columns use the column default
     * (or 0 / empty string when it has none) instead of failing with a database error.
     */
    protected function fillNotNullDefaults(CrudResource $r, array $data): array
    {
        static $columns = [];
        $table = (new ($r::$model))->getTable();
        $columns[$table] ??= collect(\Illuminate\Support\Facades\Schema::getColumns($table))->keyBy('name')->all();
        foreach ($data as $key => $value) {
            $col = $columns[$table][$key] ?? null;
            if ($value !== null || ! $col || $col['nullable']) {
                continue;
            }
            $default = $col['default'];
            if (is_string($default)) {
                $default = trim($default, "'\"");
                $default = strtoupper($default) === 'NULL' ? null : $default;
            }
            if ($default === null) {
                $numeric = preg_match('/int|decimal|numeric|float|double|real|bool/i', (string) $col['type_name']);
                $default = $numeric ? 0 : '';
            }
            $data[$key] = $default;
        }

        return $data;
    }

    protected function storeAttachments(CrudResource $r, Request $request, Model $model): void
    {
        if ($r::$hasAttachments && $request->hasFile('attachments')) {
            foreach ((array) $request->file('attachments') as $file) {
                FileStorage::attach($model, $file, 'document');
            }
        }
    }
}
