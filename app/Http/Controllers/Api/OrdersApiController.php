<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductionOrder;
use App\Models\Funkcija;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrdersApiController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $funkcija = $this->mapUserToFunkcija($user);

        $perPage = min(max((int) $request->query('per_page', 15), 5), 100);
        $status = $request->query('status');
        $q = trim((string) $request->query('q', ''));
        $pendingForMe = filter_var($request->query('pending_for_me', false), FILTER_VALIDATE_BOOL);

        $query = ProductionOrder::query()
            ->select(['id','OrderNumber','Description','Status','partner_id','user_id','created_at','is_void'])
            ->with(['partner:id,name','creator:id,name'])
            ->withSum('details as total_quantity','quantity')
            ->where('is_void', false)
            ->latest('id');

        // Radnik vidi svoje naloge (osnovno ograničenje za mobilni prikaz)
        if ($funkcija === 'Radnik') {
            $query->where('user_id', $user->id);
        }

        // Pending za mene (ako je odobravalac)
        if ($pendingForMe && $funkcija) {
            $query->whereHas('approvals', function ($q) use ($funkcija) {
                $q->where('Funkcija', $funkcija)->whereNull('Odobreno');
            });
        }

        if (!empty($status)) {
            if ($status === 'na odobrenju') {
                $query->where('Status', 'like', 'na odobrenju%');
            } else {
                $query->where('Status', $status);
            }
        }

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('OrderNumber', 'like', "%{$q}%")
                   ->orWhere('Description', 'like', "%{$q}%");
            });
        }

        $page = $query->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function show(ProductionOrder $order)
    {
        $order->loadMissing([
            'partner:id,name',
            'creator:id,name',
            'details.product:id,Name,TypeOfProduct,VrstaProvodnika',
            'approvals:id,order_id,Funkcija,Odobreno,DatumOdobravanja,Komentar,signed_by_proxy,UserId',
        ]);

        return response()->json([
            'id' => $order->id,
            'OrderNumber' => $order->OrderNumber,
            'Description' => $order->Description,
            'Status' => $order->Status,
            'is_void' => (bool) $order->is_void,
            'partner' => $order->partner?->name,
            'creator' => $order->creator?->name,
            'created_at' => optional($order->created_at)->format('Y-m-d H:i'),
            'total_quantity' => (float) ($order->details->sum('quantity')),
            'details' => $order->details->map(function ($d) {
                return [
                    'product' => $d->product?->Name,
                    'quantity' => (float) $d->quantity,
                ];
            })->values(),
            'approvals' => $order->approvals->map(function ($a) {
                return [
                    'funkcija' => $a->Funkcija,
                    'approved' => $a->Odobreno,
                    'by_user_id' => $a->UserId,
                    'at' => optional($a->DatumOdobravanja)->format('Y-m-d H:i'),
                    'comment' => $a->Komentar,
                    'proxy' => (bool) $a->signed_by_proxy,
                ];
            })->values(),
        ]);
    }

    private function mapUserToFunkcija($user): ?string
    {
        $value = $user->funkcija ?? null;
        if (!$value) return null;
        $trimmed = trim($value);
        $canonical = Funkcija::where('Funkcija', $trimmed)->value('Funkcija');
        if ($canonical) return $canonical;
        $all = Funkcija::pluck('Funkcija');
        foreach ($all as $f) {
            if (mb_strtolower(trim($f), 'UTF-8') === mb_strtolower($trimmed, 'UTF-8')) {
                return $f;
            }
        }
        return null;
    }
}
