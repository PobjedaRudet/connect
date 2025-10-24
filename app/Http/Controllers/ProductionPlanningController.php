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
use App\Models\Holiday;

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
        // Map of objekat => [order_id => percent_sum, ...] za filtriranje dostupnih naloga
        $percentByObjekat = DB::table('production_plan_items as i')
            ->join('production_plans as p','p.id','=','i.production_plan_id')
            ->select('p.objekat','i.production_order_id', DB::raw('SUM(i.percent) as percent_sum'))
            ->groupBy('p.objekat','i.production_order_id')
            ->get()
            ->groupBy('objekat')
            ->map(function ($rows) {
                return $rows->mapWithKeys(function($row) {
                    return [$row->production_order_id => (int)$row->percent_sum];
                });
            });
        return Inertia::render('Planiranje/PlanProizvodnje', [
            'orders' => $orders,
            'plans' => $plans,
            'objekti' => [
                'Laboracija I smjena',
                'Laboracija II smjena',
                'Laboracija Automatika I smjena',
                'Laboracija Automatika II smjena',
                'Kompletiranje',
                'Kompletiranje Nonel'
            ],
            'percentByObjekat' => $percentByObjekat,
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureDirectorProizvodnje();
        $data = $request->validate([
            'objekat' => 'required|string',
            // Laboracija date is required only for Kompletiranje( Nonel )
            'laboracija_datum' => 'nullable|date|required_if:objekat,Kompletiranje,Kompletiranje Nonel',
            'delivery_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.order_id' => 'nullable|integer|exists:production_orders,id',
            'items.*.start_date' => 'required|date',
            'items.*.end_date' => 'required|date|after_or_equal:start_date',
            'items.*.percent' => 'nullable|integer|min:1|max:100',
            'items.*.placeholder_label' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($data) {
            $plan = ProductionPlan::create([
                'objekat' => $data['objekat'],
                'laboracija_datum' => $data['laboracija_datum'] ?? null,
                'delivery_date' => $data['delivery_date'],
                'planned_by' => Auth::id(),
            ]);

            foreach ($data['items'] as $it) {
                $newPercent = $it['percent'] ?? 100;
                $orderId = $it['order_id'] ?? null;
                $placeholder = $it['placeholder_label'] ?? null;
                if (!$orderId && !$placeholder) {
                    abort(422, 'Svaka stavka mora imati ili postojeći nalog ili naziv privremenog naloga.');
                }
                if ($orderId) {
                    // Izračunaj ukupni percent za ovaj nalog u ovom objektu
                    $currentPercent = ProductionPlanItem::whereHas('plan', function($q) use ($data) {
                            $q->where('objekat', $data['objekat']);
                        })
                        ->where('production_order_id', $orderId)
                        ->sum('percent');
                    if ($currentPercent + $newPercent > 100) {
                        abort(422, 'Ukupni procenat za nalog ne može preći 100%.');
                    }
                }
                ProductionPlanItem::create([
                    'production_plan_id' => $plan->id,
                    'production_order_id' => $orderId,
                    'start_date' => $it['start_date'],
                    'end_date' => $it['end_date'],
                    'percent' => $newPercent,
                    'placeholder_label' => $placeholder,
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

        $items = ProductionPlanItem::with([
                'order:id,OrderNumber,Description,partner_id',
                'order.partner:id,name,oznaka',
                'plan:id,objekat'
            ])
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
                    'percent' => $it->percent,
                    'plan_delivery_date' => $it->plan?->delivery_date,
                    'placeholder_label' => $it->placeholder_label,
                    'order' => $it->order ? [
                        'id' => $it->order->id,
                        'OrderNumber' => $it->order->OrderNumber,
                        'Description' => $it->order->Description,
                        'partner' => $it->order->partner ? [
                            'name' => $it->order->partner->name,
                            'oznaka' => $it->order->partner->oznaka,
                        ] : null,
                    ] : null,
                ];
            });

        $objekti = ProductionPlan::query()->distinct()->pluck('objekat')->filter()->values();

        // Holidays list for frontend (YYYY-MM-DD)
        $holidays = Holiday::query()->orderBy('date')->pluck('date')->map(fn($d) => Carbon::parse($d)->toDateString());

        // Orders list for linking placeholders: show recent orders (backend will enforce percent <= 100 on link)
        $availableOrders = ProductionOrder::orderByDesc('id')
            ->limit(200)
            ->get(['id','OrderNumber','Description']);

        return Inertia::render('Planiranje/PlanGantt', [
            'items' => $items,
            'from' => $from,
            'to' => $to,
            'objekti' => $objekti,
            'selectedObjekat' => $objekat ?? '',
            'groupBy' => $request->query('groupBy', 'objekat_shift'),
            'zoom' => $request->query('zoom', 'day'),
            'holidays' => $holidays,
            'availableOrders' => $availableOrders,
        ]);
    }

    public function linkOrder(Request $request)
    {
        $this->ensureDirectorProizvodnje();
        $data = $request->validate([
            'plan_item_id' => 'required|integer|exists:production_plan_items,id',
            'order_id' => 'required|integer|exists:production_orders,id',
        ]);
        return DB::transaction(function () use ($data) {
            $item = ProductionPlanItem::with('plan')->lockForUpdate()->findOrFail($data['plan_item_id']);
            if ($item->production_order_id) {
                abort(422, 'Stavka je već vezana za nalog.');
            }
            $objekat = $item->plan?->objekat;
            $sum = ProductionPlanItem::whereHas('plan', fn($q) => $q->where('objekat', $objekat))
                ->where('production_order_id', $data['order_id'])
                ->sum('percent');
            if ($sum + (int)$item->percent > 100) {
                abort(422, 'Vezivanje prelazi 100% za izabrani nalog.');
            }
            $item->production_order_id = $data['order_id'];
            $item->placeholder_label = null;
            $item->save();
            return response()->json(['status' => 'ok']);
        });
    }

    // ===== Working day helpers (skip Sundays and predefined holidays) =====
    protected function holidayDates(): array
    {
        static $cache = null;
        if ($cache === null) {
            $cache = Holiday::query()->pluck('date')->map(fn($d) => Carbon::parse($d)->toDateString())->all();
        }
        return $cache;
    }

    protected function isNonWorkingDay(Carbon $date): bool
    {
        if ($date->isSunday()) return true;
        return in_array($date->toDateString(), $this->holidayDates(), true);
    }

    protected function nextWorkingDay(Carbon $date): Carbon
    {
        $d = $date->copy();
        while ($this->isNonWorkingDay($d)) {
            $d->addDay();
        }
        return $d;
    }

    protected function addWorkingDays(Carbon $start, int $days): Carbon
    {
        // days is count of working days minus 1 for inclusive end (e.g., 1 day -> same day if working)
        $d = $this->nextWorkingDay($start);
        $remaining = $days;
        while ($remaining > 0) {
            $d->addDay();
            if ($this->isNonWorkingDay($d)) continue;
            $remaining--;
        }
        return $d;
    }

    protected function workingDaysDuration(Carbon $start, Carbon $end): int
    {
        $s = $start->copy();
        $e = $end->copy();
        if ($e->lt($s)) return 0;
        $days = 0;
        for ($d = $s->copy(); $d->lte($e); $d->addDay()) {
            if (!$this->isNonWorkingDay($d)) $days++;
        }
        return max(0, $days);
    }

    public function insert(Request $request)
    {
        $this->ensureDirectorProizvodnje();
        $data = $request->validate([
            'objekat' => 'required|string',
            'order_id' => 'required|integer|exists:production_orders,id',
            'start_date' => 'required|date',
            'duration_days' => 'required|integer|min:1',
            'move_others' => 'sometimes|boolean',
            'laboracija_datum' => 'nullable|date',
        ]);

        $objekat = $data['objekat'];
        $start = Carbon::parse($data['start_date']);
        $duration = (int)$data['duration_days'];
        $moveOthers = (bool)($data['move_others'] ?? false);
        $laboracija = $data['laboracija_datum'] ?? null;

        // Conditional validation for laboracija_datum
        if (in_array($objekat, ['Kompletiranje','Kompletiranje Nonel'], true) && !$laboracija) {
            return back()->withErrors(['laboracija_datum' => 'Datum laboracije je obavezan za izabrani objekat.'])->withInput();
        }

        // Enforce unique order per objekat
        $exists = ProductionPlanItem::where('production_order_id', $data['order_id'])
            ->whereHas('plan', fn($q) => $q->where('objekat', $objekat))
            ->exists();
        if ($exists) {
            return back()->withErrors(['order_id' => 'Ovaj nalog je već planiran u istom objektu.'])->withInput();
        }

        return DB::transaction(function () use ($objekat, $start, $duration, $moveOthers, $data, $laboracija) {
            // Normalize start to next working day
            $newStart = $this->nextWorkingDay($start);
            $newEnd = $this->addWorkingDays($newStart, $duration - 1);

            // Collect existing items on same objekat ordered by start
            $items = ProductionPlanItem::whereHas('plan', fn($q) => $q->where('objekat', $objekat))
                ->orderBy('start_date')->get();

            // Helper closure to detect overlap
            $overlaps = function (Carbon $aStart, Carbon $aEnd, Carbon $bStart, Carbon $bEnd): bool {
                return $aStart->lte($bEnd) && $bStart->lte($aEnd);
            };

            if (!$moveOthers) {
                // Find next available non-overlapping slot
                $guard = 0;
                while ($guard < 1000) { // avoid infinite loop
                    $conflict = null;
                    foreach ($items as $it) {
                        $s = Carbon::parse($it->start_date);
                        $e = Carbon::parse($it->end_date);
                        if ($overlaps($newStart, $newEnd, $s, $e)) { $conflict = $e; break; }
                    }
                    if (!$conflict) break;
                    // move start to next working day after conflict end
                    $newStart = $this->nextWorkingDay($conflict->copy()->addDay());
                    $newEnd = $this->addWorkingDays($newStart, $duration - 1);
                    $guard++;
                }
            } else {
                // Shift subsequent overlapping items to the right preserving their working-day durations
                $currentEnd = $newEnd->copy();
                foreach ($items as $it) {
                    $s = Carbon::parse($it->start_date);
                    $e = Carbon::parse($it->end_date);
                    if ($overlaps($newStart, $currentEnd, $s, $e)) {
                        $itDuration = $this->workingDaysDuration($s, $e);
                        $shiftedStart = $this->nextWorkingDay($currentEnd->copy()->addDay());
                        $shiftedEnd = $this->addWorkingDays($shiftedStart, max(1, $itDuration) - 1);
                        $it->start_date = $shiftedStart->toDateString();
                        $it->end_date = $shiftedEnd->toDateString();
                        $it->save();
                        $currentEnd = $shiftedEnd->copy();
                    } elseif ($s->gt($currentEnd)) {
                        // no conflict and starts after chain; advance chain end
                        $currentEnd = $e->copy();
                    }
                }
            }

            // Create new plan header for this objekat
            $plan = ProductionPlan::create([
                'objekat' => $objekat,
                'planned_by' => Auth::id(),
                'laboracija_datum' => $laboracija,
            ]);

            $item = ProductionPlanItem::create([
                'production_plan_id' => $plan->id,
                'production_order_id' => $data['order_id'],
                'start_date' => $newStart->toDateString(),
                'end_date' => $newEnd->toDateString(),
            ]);

            return redirect()->route('planning.gantt', request()->query())->with('success', 'Plan umetnut.');
        });
    }

    public function bulkInsert(Request $request)
    {
        $this->ensureDirectorProizvodnje();
        $data = $request->validate([
            'objekat' => 'required|string',
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:production_orders,id',
            'start_date' => 'required|date',
            'duration_days' => 'required|integer|min:1',
            'move_others' => 'sometimes|boolean',
            'laboracija_datum' => 'nullable|date',
        ]);

        $objekat = $data['objekat'];
        $start = Carbon::parse($data['start_date']);
        $duration = (int)$data['duration_days'];
        $moveOthers = (bool)($data['move_others'] ?? false);
        $laboracija = $data['laboracija_datum'] ?? null;

        if (in_array($objekat, ['Kompletiranje','Kompletiranje Nonel'], true) && !$laboracija) {
            return back()->withErrors(['laboracija_datum' => 'Datum laboracije je obavezan za izabrani objekat.'])->withInput();
        }

        DB::transaction(function () use ($data, $objekat, $start, $duration, $moveOthers, $laboracija) {
            $cursorStart = $start->copy();
            foreach ($data['order_ids'] as $orderId) {
                // Skip if already planned in objekat
                $exists = ProductionPlanItem::where('production_order_id', $orderId)
                    ->whereHas('plan', fn($q) => $q->where('objekat', $objekat))
                    ->exists();
                if ($exists) continue;

                // Calculate placement for this order using same logic as single insert
                $newStart = $this->nextWorkingDay($cursorStart);
                $newEnd = $this->addWorkingDays($newStart, $duration - 1);

                $items = ProductionPlanItem::whereHas('plan', fn($q) => $q->where('objekat', $objekat))
                    ->orderBy('start_date')->get();

                $overlaps = function (Carbon $aStart, Carbon $aEnd, Carbon $bStart, Carbon $bEnd): bool {
                    return $aStart->lte($bEnd) && $bStart->lte($aEnd);
                };

                if (!$moveOthers) {
                    $guard = 0;
                    while ($guard < 1000) {
                        $conflict = null;
                        foreach ($items as $it) {
                            $s = Carbon::parse($it->start_date);
                            $e = Carbon::parse($it->end_date);
                            if ($overlaps($newStart, $newEnd, $s, $e)) { $conflict = $e; break; }
                        }
                        if (!$conflict) break;
                        $newStart = $this->nextWorkingDay($conflict->copy()->addDay());
                        $newEnd = $this->addWorkingDays($newStart, $duration - 1);
                        $guard++;
                    }
                } else {
                    $currentEnd = $newEnd->copy();
                    foreach ($items as $it) {
                        $s = Carbon::parse($it->start_date);
                        $e = Carbon::parse($it->end_date);
                        if ($overlaps($newStart, $currentEnd, $s, $e)) {
                            $itDuration = $this->workingDaysDuration($s, $e);
                            $shiftedStart = $this->nextWorkingDay($currentEnd->copy()->addDay());
                            $shiftedEnd = $this->addWorkingDays($shiftedStart, max(1, $itDuration) - 1);
                            $it->start_date = $shiftedStart->toDateString();
                            $it->end_date = $shiftedEnd->toDateString();
                            $it->save();
                            $currentEnd = $shiftedEnd->copy();
                        } elseif ($s->gt($currentEnd)) {
                            $currentEnd = $e->copy();
                        }
                    }
                }

                $plan = ProductionPlan::create([
                    'objekat' => $objekat,
                    'planned_by' => Auth::id(),
                    'laboracija_datum' => $laboracija,
                ]);
                ProductionPlanItem::create([
                    'production_plan_id' => $plan->id,
                    'production_order_id' => $orderId,
                    'start_date' => $newStart->toDateString(),
                    'end_date' => $newEnd->toDateString(),
                ]);

                // For next order, keep cursor at same requested start (apply same range)
                // If you want sequential scheduling, uncomment next line:
                // $cursorStart = $this->nextWorkingDay($newEnd->copy()->addDay());
            }
        });

        return redirect()->route('planning.gantt', request()->query())->with('success', 'Masovni unos završen.');
    }
}
