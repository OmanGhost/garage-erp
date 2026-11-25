<?php

namespace App\Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reports\Services\AiAssistant;
use App\Modules\Reports\Services\ReportRegistry;
use App\Modules\Vehicles\Models\VehicleMake;
use App\Support\Export\Exporter;
use App\Support\Options;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->can('reports.view'), 403);

        return view('reports::index', ['groups' => ReportRegistry::groups()]);
    }

    public function show(Request $request, string $key)
    {
        abort_unless(auth()->user()->can('reports.view'), 403);
        $title = ReportRegistry::title($key) ?? abort(404);
        $filters = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'branch_id' => ['nullable', 'integer'], 'department_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'], 'customer_id' => ['nullable', 'integer'], 'days' => ['nullable', 'integer'],
        ]);
        $filters['from'] ??= now()->startOfMonth()->toDateString();
        $filters['to'] ??= now()->toDateString();
        $report = ReportRegistry::run($key, $filters);

        if ($format = $request->query('export')) {
            abort_unless(auth()->user()->can('reports.export') || ($format === 'print' && auth()->user()->can('reports.print')), 403);
            $keys = array_keys($report['columns']);
            $rows = array_map(fn ($r) => array_map(fn ($k) => $r[$k] ?? '', $keys), $report['rows']);
            $totals = $report['totals'] ? array_map(fn ($k) => $report['totals'][$k] ?? '', $keys) : null;
            \App\Support\Audit\AuditLogger::log('report.exported', null, ['report' => $key, 'format' => $format]);

            return Exporter::download($format, $title, array_values($report['columns']), $rows, ['filters' => array_filter($filters), 'totals' => $totals]);
        }

        return view('reports::show', ['key' => $key, 'title' => $title, 'report' => $report, 'f' => $filters,
            'branches' => Options::branches(), 'departments' => Options::departments(), 'users' => Options::users()]);
    }

    public function ai(Request $request, AiAssistant $ai)
    {
        abort_unless(auth()->user()->can('reports.view'), 403);

        return view('reports::ai', ['forecast' => $ai->demandForecast(15), 'makes' => VehicleMake::query()->orderBy('name')->pluck('name', 'id'), 'result' => session('ai_result')]);
    }

    public function diagnose(Request $request, AiAssistant $ai)
    {
        abort_unless(auth()->user()->can('reports.view'), 403);
        $data = $request->validate(['symptom' => ['required_without:dtc', 'nullable', 'string', 'max:500'], 'dtc' => ['nullable', 'string', 'max:10'], 'make_id' => ['nullable', 'integer']]);
        $result = $ai->diagnose($data['symptom'] ?? '', $data['make_id'] ?? null, $data['dtc'] ?? null);

        return back()->withInput()->with('ai_result', json_decode(json_encode($result), true));
    }
}
