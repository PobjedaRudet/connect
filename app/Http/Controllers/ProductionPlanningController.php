<?php

namespace App\Http\Controllers;

use App\Models\ProductionOrder;
use App\Models\ProductionPlan;
use App\Models\ProductionPlanItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductionPlanningController extends Controller
{
    protected function ensureDirectorProizvodnje()
    {
        $user = Auth::user();
        if (!$user || ($user->funkcija ?? null) !== 'Direktor Proizvodnje') {
            abort(403, 'Nedozvoljen pristup');
        }
    }

    public function index(Request $request)
    {
        $this->ensureDirectorProizvodnje();
        $orders = ProductionOrder::with('partner')
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id','OrderNumber','Description','OrderDate','Status','partner_id']);
        $plans = ProductionPlan::with(['items.order:id,OrderNumber'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();
        return Inertia::render('Planiranje/PlanProizvodnje', [
            'orders' => $orders,
            'plans' => $plans,
            'objekti' => [
                'Laboracija I smjena',
                'Laboracija II smjena',
                'Kompletiranje',
                'Kompletiranje Nonel'
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureDirectorProizvodnje();
        $data = $request->validate([
            'objekat' => 'required|string',
            'laboracija_datum' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.order_id' => 'required|integer|exists:production_orders,id',
            'items.*.start_date' => 'required|date',
            // compare to the sibling field within the same item
            'items.*.end_date' => 'required|date|after_or_equal:start_date',
        ]);

        DB::transaction(function () use ($data) {
            $plan = ProductionPlan::create([
                'objekat' => $data['objekat'],
                'laboracija_datum' => $data['laboracija_datum'] ?? null,
                'planned_by' => Auth::id(),
            ]);

            foreach ($data['items'] as $it) {
                ProductionPlanItem::create([
                    'production_plan_id' => $plan->id,
                    'production_order_id' => $it['order_id'],
                    'start_date' => $it['start_date'],
                    'end_date' => $it['end_date'],
                ]);
            }
        });

        return redirect()->back()->with('success', 'Plan snimljen.');
    }

    public function gantt(Request $request)
    {
        $this->ensureDirectorProizvodnje();
        $from = $request->query('from');
        $to = $request->query('to');
        $objekat = $request->query('objekat');

        // Defaults: show current month span if not specified
        if (!$from || !strtotime($from)) {
            $from = Carbon::now()->startOfMonth()->toDateString();
        }
        if (!$to || !strtotime($to)) {
            $to = Carbon::now()->endOfMonth()->toDateString();
        }

        $items = ProductionPlanItem::with(['order:id,OrderNumber,Description', 'plan:id,objekat'])
            ->when($from, fn($q) => $q->whereDate('end_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('start_date', '<=', $to))
            ->when($objekat, fn($q) => $q->whereHas('plan', fn($qq) => $qq->where('objekat', $objekat)))
            ->orderBy('start_date')
            ->get()
            ->map(function ($it) {
                return [
                    'id' => $it->id,
                    'start_date' => $it->start_date,
                    'end_date' => $it->end_date,
                    'objekat' => $it->plan?->objekat,
                    'order' => $it->order ? [
                        'id' => $it->order->id,
                        'OrderNumber' => $it->order->OrderNumber,
                        'Description' => $it->order->Description,
                    ] : null,
                ];
            });

        $objekti = ProductionPlan::query()->distinct()->pluck('objekat')->filter()->values();

        return Inertia::render('Planiranje/PlanGantt', [
            'items' => $items,
            'from' => $from,
            'to' => $to,
            'objekti' => $objekti,
            'selectedObjekat' => $objekat ?? '',
            'groupBy' => $request->query('groupBy', 'objekat_shift'),
            'zoom' => $request->query('zoom', 'day'),
        ]);
    }
}
