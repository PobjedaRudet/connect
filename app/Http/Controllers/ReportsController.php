<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ReportsController extends Controller
{
    protected function ensureDirectorKomercijale()
    {
    $user = Auth::user();
        if (!$user || ($user->funkcija ?? null) !== 'Direktor Komercijale') {
            abort(403, 'Nedozvoljen pristup');
        }
    }

    public function byCustomers(Request $request)
    {
        $this->ensureDirectorKomercijale();
    [$from, $to] = $this->dateRange($request);
    $status = $request->input('status') ?: 'Odobreno';
        $data = $this->aggByCustomers($from, $to, $status);
        return Inertia::render('Izvjestaji/PoKupcima', [
            'from' => $from,
            'to' => $to,
            'status' => $status,
            'rows' => $data,
        ]);
    }

    public function byProducts(Request $request)
    {
        $this->ensureDirectorKomercijale();
    [$from, $to] = $this->dateRange($request);
    $status = $request->input('status') ?: 'Odobreno';
        $data = $this->aggByProducts($from, $to, $status);
        return Inertia::render('Izvjestaji/PoProizvodima', [
            'from' => $from,
            'to' => $to,
            'status' => $status,
            'rows' => $data,
        ]);
    }

    public function monthly(Request $request)
    {
        $this->ensureDirectorKomercijale();
    $year = (int)($request->input('year') ?: now()->year);
    $status = $request->input('status') ?: 'Odobreno';
        $data = $this->aggMonthly($year, $status);
        return Inertia::render('Izvjestaji/Mjesecni', [
            'year' => $year,
            'status' => $status,
            'rows' => $data,
        ]);
    }

    public function yearly(Request $request)
    {
        $this->ensureDirectorKomercijale();
    $status = $request->input('status') ?: 'Odobreno';
        $data = $this->aggYearly($status);
        return Inertia::render('Izvjestaji/Godisnji', [
            'rows' => $data,
            'status' => $status,
        ]);
    }

    // JSON endpoints
    public function byCustomersJson(Request $request)
    {
        $this->ensureDirectorKomercijale();
    [$from, $to] = $this->dateRange($request);
    $status = $request->input('status') ?: 'Odobreno';
        return response()->json($this->aggByCustomers($from, $to, $status));
    }

    public function byProductsJson(Request $request)
    {
        $this->ensureDirectorKomercijale();
    [$from, $to] = $this->dateRange($request);
    $status = $request->input('status') ?: 'Odobreno';
        return response()->json($this->aggByProducts($from, $to, $status));
    }

    public function monthlyJson(Request $request)
    {
        $this->ensureDirectorKomercijale();
    $year = (int)($request->input('year') ?: now()->year);
    $status = $request->input('status') ?: 'Odobreno';
        return response()->json($this->aggMonthly($year, $status));
    }

    public function yearlyJson(Request $request)
    {
        $this->ensureDirectorKomercijale();
    $status = $request->input('status') ?: 'Odobreno';
        return response()->json($this->aggYearly($status));
    }

    // Helpers
    protected function dateRange(Request $request): array
    {
        $to = $request->input('to') ?: now()->toDateString();
        $from = $request->input('from') ?: now()->copy()->subYear()->toDateString();
        return [$from, $to];
    }

    protected function aggByCustomers(string $from, string $to, ?string $status)
    {
        $q = DB::table('production_order_details as d')
            ->join('production_orders as o', 'o.id', '=', 'd.production_order_id')
            ->leftJoin('partners as p', 'p.id', '=', 'o.partner_id')
            ->whereBetween('o.OrderDate', [$from, $to])
            ->select(
                'o.partner_id',
                DB::raw('COALESCE(p.name, "(bez partnera)") as partner_name'),
                DB::raw('SUM(COALESCE(d.quantity,0)) as total_quantity'),
                DB::raw('COUNT(DISTINCT o.id) as orders_count')
            )
            ->groupBy('o.partner_id', 'p.name')
            ->orderByDesc(DB::raw('SUM(COALESCE(d.quantity,0))'));

        if ($status) {
            $q->where('o.Status', $status);
        }
        return $q->get();
    }

    protected function aggByProducts(string $from, string $to, ?string $status)
    {
        $q = DB::table('production_order_details as d')
            ->join('production_orders as o', 'o.id', '=', 'd.production_order_id')
            ->leftJoin('products as pr', 'pr.id', '=', 'd.product_id')
            ->whereBetween('o.OrderDate', [$from, $to])
            ->select(
                'd.product_id',
                DB::raw('COALESCE(pr.SkraceniNaziv, pr.NumeraProizvoda, CONCAT("Proizvod #", d.product_id)) as product_name'),
                DB::raw('SUM(COALESCE(d.quantity,0)) as total_quantity'),
                DB::raw('COUNT(DISTINCT o.id) as orders_count')
            )
            ->groupBy('d.product_id', 'pr.SkraceniNaziv', 'pr.NumeraProizvoda')
            ->orderByDesc(DB::raw('SUM(COALESCE(d.quantity,0))'));

        if ($status) {
            $q->where('o.Status', $status);
        }
        return $q->get();
    }

    protected function aggMonthly(int $year, ?string $status)
    {
        $q = DB::table('production_order_details as d')
            ->join('production_orders as o', 'o.id', '=', 'd.production_order_id')
            ->whereYear('o.OrderDate', $year)
            ->select(
                DB::raw('YEAR(o.OrderDate) as y'),
                DB::raw('MONTH(o.OrderDate) as m'),
                DB::raw('SUM(COALESCE(d.quantity,0)) as total_quantity'),
                DB::raw('COUNT(DISTINCT o.id) as orders_count')
            )
            ->groupBy(DB::raw('YEAR(o.OrderDate)'), DB::raw('MONTH(o.OrderDate)'))
            ->orderBy(DB::raw('MONTH(o.OrderDate)'));

        if ($status) {
            $q->where('o.Status', $status);
        }
        return $q->get();
    }

    protected function aggYearly(?string $status)
    {
        $q = DB::table('production_order_details as d')
            ->join('production_orders as o', 'o.id', '=', 'd.production_order_id')
            ->select(
                DB::raw('YEAR(o.OrderDate) as y'),
                DB::raw('SUM(COALESCE(d.quantity,0)) as total_quantity'),
                DB::raw('COUNT(DISTINCT o.id) as orders_count')
            )
            ->groupBy(DB::raw('YEAR(o.OrderDate)'))
            ->orderBy(DB::raw('YEAR(o.OrderDate)'));

        if ($status) {
            $q->where('o.Status', $status);
        }
        return $q->get();
    }
}
