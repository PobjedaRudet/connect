<?php

namespace App\Http\Controllers;

use App\Mail\OrderStatusUpdated;
use App\Models\Order;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\Partner;
use App\Models\Funkcija;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductionOrderController extends Controller
{
    private function buildOrderPayload(ProductionOrder $order): array
    {
        $order->load([
            'partner:id,name',
            'creator:id,name,email',
            // Include NumeraProizvoda and UoM_meter so UI can show 'numere proizvoda' after duplicate/edit
            'details.product:id,SkraceniNaziv,Naziv,JedinicaMjere,NumeraProizvoda,UoM_meter',
            'approvals.user:id,name'
        ]);

        return [
            'id' => $order->id,
            'OrderNumber' => $order->OrderNumber,
            'OrderDate' => $order->OrderDate,
            'Description' => $order->Description,
            'Status' => $order->Status,
            'BojaDuzinaProvodnika' => $order->BojaDuzinaProvodnika,
            'Pakovanje' => $order->Pakovanje,
            'Tip' => $order->Tip,
            'AtestPaketa' => $order->AtestPaketa,
            'CeOznaka' => $order->CeOznaka,
            'KlasaOpasnosti' => $order->KlasaOpasnosti,
            'UNBroj' => $order->UNBroj,
            'VrstaProvodnika' => $order->VrstaProvodnika,
            'Metraza' => $order->Metraza,
            'RokIsporuke' => $order->RokIsporuke,
            'DatumPredaje' => $order->DatumPredaje,
            'DatumPrijema' => $order->DatumPrijema,
            'Napomena' => $order->Napomena,
            'token' => $order->token,
            'partner' => $order->partner ? [
                'id' => $order->partner->id,
                'name' => $order->partner->name,
            ] : null,
            'creator' => $order->creator ? [
                'id' => $order->creator->id,
                'name' => $order->creator->name,
                'email' => $order->creator->email,
            ] : null,
            'details' => $order->details->map(function ($d) {
                return [
                    'id' => $d->id,
                    'quantity' => $d->quantity,
                    'note' => $d->note,
                    'product' => $d->product ? [
                        'id' => $d->product->id,
                        'Naziv' => $d->product->Naziv,
                        'SkraceniNaziv' => $d->product->SkraceniNaziv,
                        'JedinicaMjere' => $d->product->JedinicaMjere,
                        'NumeraProizvoda' => $d->product->NumeraProizvoda ?? null,
                        'UoM_meter' => $d->product->UoM_meter ?? null,
                    ] : null,
                ];
            })->values(),
            'approvals' => $order->approvals
                ->sortBy('DatumOdobravanja')
                ->values()
                ->map(function ($a) {
                    return [
                        'id' => $a->id,
                        'Funkcija' => $a->Funkcija,
                        'Odobreno' => $a->Odobreno,
                        'DatumOdobravanja' => optional($a->DatumOdobravanja)->toDateTimeString(),
                        'Komentar' => $a->Komentar,
                        'signed_by_proxy' => (bool)$a->signed_by_proxy,
                        'user' => $a->user ? [ 'id' => $a->user->id, 'name' => $a->user->name ] : null,
                    ];
                }),
        ];
    }
    public function details(ProductionOrder $order)
    {
        $data = $this->buildOrderPayload($order);

        return Inertia::render('Nalozi/NalogDetalji', [
            'order' => $data,
        ]);
    }

    public function detailsJson(ProductionOrder $order)
    {
        $data = $this->buildOrderPayload($order);
        return response()->json(['order' => $data]);
    }
    public function myForSending(Request $request)
    {
        $userId = \Illuminate\Support\Facades\Auth::id();
        $orders = ProductionOrder::where('user_id', $userId)
            ->where(function ($q) {
                $q->whereNull('Status')
                  ->orWhere('Status', 'not like', 'na odobrenju%');
            })
            ->whereNotIn('Status', ['odobreno', 'odbijeno'])
            ->orderByDesc('created_at')
            ->take(200)
            ->get(['id','OrderNumber','Description','Status','created_at']);
        return response()->json(['data' => $orders]);
    }

    public function myCreated(Request $request)
    {
        $userId = \Illuminate\Support\Facades\Auth::id();
        $status = $request->string('status')->toString();
        $q = $request->string('q')->toString();

        $orders = ProductionOrder::where('user_id', $userId)
            ->with([
                'partner:id,name',
                'creator:id,name',
                // include first-level product info to show product name in list
                'details.product:id,SkraceniNaziv,Naziv'
            ])
            // include aggregated total quantity for quick display
            ->withSum('details as total_quantity', 'quantity')
            ->when($status !== '', function ($qq) use ($status) {
                if ($status === 'na odobrenju') {
                    $qq->where('Status', 'like', 'na odobrenju%');
                } else {
                    $qq->where('Status', $status);
                }
            })
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('OrderNumber', 'like', "%$q%")
                      ->orWhere('Description', 'like', "%$q%")
                      ->orWhereHas('partner', function ($p) use ($q) {
                          $p->where('name', 'like', "%$q%");
                      })
                      ->orWhereHas('details.product', function ($p) use ($q) {
                          $p->where('Naziv', 'like', "%$q%")
                            ->orWhere('SkraceniNaziv', 'like', "%$q%");
                      });
                });
            })
            ->orderByDesc('id')
            ->paginate(20, ['id','OrderNumber','OrderDate','Description','partner_id','user_id','Status','created_at']);

        return response()->json($orders);
    }

    public function radnikApproved(Request $request)
    {
        $status = $request->string('status')->toString();
        $q = $request->string('q')->toString();

        $user = Auth::user();
        $targetF = null;
        $myF = null;
        if ($user) {
            $hierarchy = Funkcija::orderBy('Redosljed')->pluck('Funkcija')->toArray();
            $val = $user->funkcija ?? null;
            if ($val) {
                $canonical = Funkcija::where('Funkcija', $val)->value('Funkcija');
                if (!$canonical) {
                    $all = Funkcija::pluck('Funkcija');
                    foreach ($all as $f) {
                        if (mb_strtolower(trim($f), 'UTF-8') === mb_strtolower(trim($val), 'UTF-8')) {
                            $canonical = $f; break;
                        }
                    }
                }
                if ($canonical) {
                    $myF = $canonical;
                    $uPos = array_search($canonical, $hierarchy, true);
                    if ($uPos !== false && isset($hierarchy[$uPos + 1])) {
                        $targetF = $hierarchy[$uPos + 1];
                    }
                }
            }
        }

        // Ensure we filter to orders where my step approved and immediate superior pending
        $orders = ProductionOrder::query()
            ->with(['partner:id,name', 'creator:id,name'])
            ->when($myF !== null, function ($qq) use ($myF) {
                $qq->whereHas('approvals', function ($aq) use ($myF) {
                    $aq->where('Funkcija', $myF)->where('Odobreno', true);
                });
            })
            ->when($targetF !== null, function ($qq) use ($targetF) {
                $qq->whereHas('approvals', function ($aq) use ($targetF) {
                    $aq->where('Funkcija', $targetF)->whereNull('Odobreno');
                });
            })
            ->when($targetF !== null, function ($qq) use ($targetF) {
                $qq->withCount(['approvals as one_up_pending_count' => function ($aq) use ($targetF) {
                    $aq->where('Funkcija', $targetF)->whereNull('Odobreno');
                }]);
            })
            ->when($myF !== null, function ($qq) use ($myF) {
                $qq->withCount(['approvals as my_step_approved_count' => function ($aq) use ($myF) {
                    $aq->where('Funkcija', $myF)->where('Odobreno', true);
                }]);
            })
            ->when($status !== '', function ($qq) use ($status) {
                if ($status === 'na odobrenju') {
                    $qq->where('Status', 'like', 'na odobrenju%');
                } else {
                    $qq->where('Status', $status);
                }
            })
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('OrderNumber', 'like', "%$q%")
                        ->orWhere('Description', 'like', "%$q%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20, ['id','OrderNumber','OrderDate','partner_id','user_id','Status','created_at']);

        $payload = $orders->toArray();
        $payload['one_up_target_funkcija'] = $targetF;
        return response()->json($payload);
    }

    public function created(Request $request)
    {
        $status = $request->string('status')->toString();
        $q = $request->string('q')->toString();

        // Determine current user's immediate superior funkcija (one-up)
        $user = Auth::user();
        $targetF = null;
        $myF = null;
        if ($user) {
            $hierarchy = Funkcija::orderBy('Redosljed')->pluck('Funkcija')->toArray();
            $val = $user->funkcija ?? null;
            if ($val) {
                // Normalize to canonical funkcija value
                $canonical = Funkcija::where('Funkcija', $val)->value('Funkcija');
                if (!$canonical) {
                    $all = Funkcija::pluck('Funkcija');
                    foreach ($all as $f) {
                        if (mb_strtolower(trim($f), 'UTF-8') === mb_strtolower(trim($val), 'UTF-8')) {
                            $canonical = $f; break;
                        }
                    }
                }
                if ($canonical) {
                    $myF = $canonical;
                    $uPos = array_search($canonical, $hierarchy, true);
                    if ($uPos !== false && isset($hierarchy[$uPos + 1])) {
                        $targetF = $hierarchy[$uPos + 1];
                    }
                }
            }
        }

        $orders = ProductionOrder::query()
            ->with(['partner:id,name', 'creator:id,name'])
            ->when($targetF !== null, function ($qq) use ($targetF) {
                $qq->withCount(['approvals as one_up_pending_count' => function ($aq) use ($targetF) {
                    $aq->where('Funkcija', $targetF)->whereNull('Odobreno');
                }]);
            })
            ->when($myF !== null, function ($qq) use ($myF) {
                $qq->withCount(['approvals as my_step_approved_count' => function ($aq) use ($myF) {
                    $aq->where('Funkcija', $myF)->where('Odobreno', true);
                }]);
            })
            ->when($status !== '', function ($qq) use ($status) {
                if ($status === 'na odobrenju') {
                    $qq->where('Status', 'like', 'na odobrenju%');
                } else {
                    $qq->where('Status', $status);
                }
            })
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('OrderNumber', 'like', "%$q%")
                      ->orWhere('Description', 'like', "%$q%")
                      ->orWhereHas('partner', function ($p) use ($q) {
                          $p->where('name', 'like', "%$q%");
                      })
                      ->orWhereHas('details.product', function ($p) use ($q) {
                          $p->where('Naziv', 'like', "%$q%")
                            ->orWhere('SkraceniNaziv', 'like', "%$q%");
                      });
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20, ['id','OrderNumber','OrderDate','Description','partner_id','user_id','Status','created_at']);

        // Return paginator payload and include immediate superior funkcija for tooltip/UI hints
        $payload = $orders->toArray();
        $payload['one_up_target_funkcija'] = $targetF; // can be null if no superior
        return response()->json($payload);
    }
    public function getOrderNumber()
    {
        // Pronađi zadnji OrderNumber iz production_orders i uvećaj za 1
        $lastOrder = ProductionOrder::orderByRaw('CAST(SUBSTRING_INDEX(OrderNumber, "/", 1) AS UNSIGNED) DESC')->first();
        if ($lastOrder && preg_match('/^(\d+)/', $lastOrder->OrderNumber, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }
        $yearShort = date('y');
        $orderNumber = $nextNumber . '/' . $yearShort;
        $workingOrders = ProductionOrder::all();
        return response()->json(['orderNumber' => $orderNumber, 'workingOrders' => $workingOrders]);
    }

    /*     public function create()
    {
        $workingOrders = ProductionOrder::all();
        Log::info($workingOrders);
        Log::info('Create order');

        return view('productionorders.createorder', compact('workingOrders'));
    } */

    public function showForm()
    {
        $workingOrders = ProductionOrder::all();
        $partners = Partner::all(['id', 'name', 'oznaka']);
        Log::info($workingOrders);
        return Inertia::render('Nalozi/NaloziZaProizvodnju', [
            'workingOrders' => $workingOrders,
            'partners' => $partners,
            // ...ostali podaci
        ]);
    }
    public function store(Request $request)
    {
        Log::info('Request data:', $request->all());

        try {
            // Validate input (align with schema; no DueDate column exists)
            $validator = Validator::make($request->all(), [
                'OrderNumber' => 'nullable|string|max:255',
                'partner_id' => 'required|integer|exists:partners,id',
                'OrderDate' => 'required|date',
                'Description' => 'nullable|string|max:1000',
                'AtestPaketa' => 'nullable|string|max:255',
                'Status' => 'nullable|string|max:50',
                'token' => 'nullable|string|max:255',
                'created_by' => 'nullable|integer|exists:users,id',
                'updated_by' => 'nullable|integer|exists:users,id',
                // Product list validation
                'productListNew' => 'required|array|min:1',
                'productListNew.*.id' => 'required|integer|exists:products,id',
                'productListNew.*.quantity' => 'required|numeric|min:1',
            ]);

            if ($validator->fails()) {
                Log::warning('Validation failed for production order', ['errors' => $validator->errors()->toArray()]);
                return response()->json(['message' => 'Neispravni podaci', 'errors' => $validator->errors()], 422);
            }

            // Generate next OrderNumber

            // Pronađi zadnji OrderNumber iz production_orders i uvećaj za 1
            $lastOrder = ProductionOrder::orderByRaw('CAST(SUBSTRING_INDEX(OrderNumber, "/", 1) AS UNSIGNED) DESC')->first();
            if ($lastOrder && preg_match('/^(\d+)/', $lastOrder->OrderNumber, $matches)) {
                $nextNumber = intval($matches[1]) + 1;
            } else {
                $nextNumber = 1;
            }
            $yearShort = date('y');
            $orderNumber = $nextNumber . '/' . $yearShort;

            $orderData = $request->except('productListNew');
            Log::info("Order data prepared:", ['orderData' => $orderData]);
            $orderData['partner_id'] = $request->input('partner_id');
            $orderData['OrderNumber'] = $orderNumber;
            // Ensure non-nullables have sane defaults
            // Always set Status to "Na čekanju" on create (hidden from form)
            $orderData['Status'] = 'Na čekanju';
            if (!array_key_exists('RokIsporuke', $orderData) || $orderData['RokIsporuke'] === null) {
                $orderData['RokIsporuke'] = '';
            }
            // Always keep DatumPrijema null on creation (will be set when Šef Operative approves)
            $orderData['DatumPrijema'] = null;

            $productListNew = $request->input('productListNew', []);

            // Create new order with partner_id and incremented OrderNumber
            // set creator if not provided
            if (!isset($orderData['user_id']) || !$orderData['user_id']) {
                $orderData['user_id'] = Auth::id();
            }
            $order = ProductionOrder::create($orderData);
            Log::info("Order created:", ['order' => $order]);

            // Save order details (products)
            foreach ($productListNew as $product) {
                ProductionOrderDetail::create([
                    'production_order_id' => $order->id,
                    'product_id' => $product['id'],
                    'quantity' => $product['quantity'],
                ]);
            }

            // Optional: additional lookup by token if needed (skipped)

            // Send an email notification
            if (!empty('h.ahmet@pobjeda.com')) {
                Mail::to('h.ahmet@pobjeda.com')->send(new OrderStatusUpdated($order));
            } else {
                Log::warning("Order does not have a customer email, using default email h.ahmet@pobjeda.com:", ['order' => $order]);
                Mail::to('h.ahmet@pobjeda.com')->send(new OrderStatusUpdated($order));
            }

            return response()->json(['message' => 'Order successfully saved!'], 200);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Neispravni podaci', 'errors' => $e->errors()], 422);
        } catch (\PDOException $e) {
            Log::error('DB error while saving production order', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Greška baze pri snimanju naloga.'], 500);
        } catch (Exception $e) {
            Log::error("Error: {$e->getMessage()}");
            return response()->json(['message' => 'An error occurred while saving the order.'], 500);
        }
    }

    public function update(Request $request, ProductionOrder $order)
    {
        // Only creator can edit, and only if not approved by superior yet (i.e., not in 'na odobrenju%' or 'odobreno'/'odbijeno')
        if ($order->user_id !== Auth::id()) {
            return response()->json(['message' => 'Nemate ovlaštenje za izmjenu ovog naloga.'], 403);
        }
        if (($order->Status && str_starts_with(mb_strtolower($order->Status), 'na odobrenju')) || in_array($order->Status, ['odobreno','odbijeno'])) {
            return response()->json(['message' => 'Nalog se ne može uređivati nakon slanja na odobrenje ili finalne odluke.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'partner_id' => 'required|integer|exists:partners,id',
            'OrderDate' => 'required|date',
            'Description' => 'nullable|string|max:1000',
            'AtestPaketa' => 'nullable|string|max:255',
            'token' => 'nullable|string|max:255',
            // Product list validation
            'productListNew' => 'required|array|min:1',
            'productListNew.*.id' => 'required|integer|exists:products,id',
            'productListNew.*.quantity' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Neispravni podaci', 'errors' => $validator->errors()], 422);
        }

        $data = $request->except('productListNew');
        $data['partner_id'] = $request->input('partner_id');
        // Preserve immutable fields
        unset($data['OrderNumber'], $data['user_id'], $data['Status'], $data['DatumPrijema']);

        $order->update($data);

        // Replace details
        ProductionOrderDetail::where('production_order_id', $order->id)->delete();
        foreach ($request->input('productListNew', []) as $product) {
            ProductionOrderDetail::create([
                'production_order_id' => $order->id,
                'product_id' => $product['id'],
                'quantity' => $product['quantity'],
            ]);
        }

        return response()->json(['message' => 'Nalog je ažuriran.']);
    }

    public function duplicate(ProductionOrder $order)
    {
        // Only creator can duplicate
        if ($order->user_id !== Auth::id()) {
            return response()->json(['message' => 'Nemate ovlaštenje za dupliciranje ovog naloga.'], 403);
        }

        // Create next OrderNumber
        $lastOrder = ProductionOrder::orderByRaw('CAST(SUBSTRING_INDEX(OrderNumber, "/", 1) AS UNSIGNED) DESC')->first();
        if ($lastOrder && preg_match('/^(\d+)/', $lastOrder->OrderNumber, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }
        $yearShort = date('y');
        $orderNumber = $nextNumber . '/' . $yearShort;

        $new = $order->replicate([
            'OrderNumber', 'DatumPrijema', 'Status', 'created_at', 'updated_at'
        ]);
        $new->OrderNumber = $orderNumber;
        $new->Status = 'Na čekanju';
        $new->DatumPrijema = null;
        $new->user_id = Auth::id();
        $new->push();

        // Clone details (replicate full record to preserve all per-detail columns)
        $details = ProductionOrderDetail::where('production_order_id', $order->id)->get();
        foreach ($details as $d) {
            $newDetail = $d->replicate();
            $newDetail->production_order_id = $new->id;
            $newDetail->push();
        }

        return response()->json(['message' => 'Nalog je dupliciran.', 'id' => $new->id]);
    }

    public function destroy(ProductionOrder $order)
    {
        // Only the creator can delete and only when order is still pending (Na čekanju)
        if ($order->user_id !== Auth::id()) {
            return response()->json(['message' => 'Nemate ovlaštenje za brisanje ovog naloga.'], 403);
        }

        $status = mb_strtolower($order->Status ?? '');
        // Allow delete if status is empty/null (legacy) or exactly "na čekanju"
        if ($status !== '' && $status !== 'na čekanju') {
            return response()->json(['message' => 'Samo nalozi u statusu "Na čekanju" mogu biti obrisani.'], 422);
        }

        try {
            // Remove related records first (safety if no DB cascade)
            $order->details()->delete();
            $order->approvals()->delete();
            $order->delete();

            return response()->json(['message' => 'Nalog je obrisan.']);
        } catch (\Throwable $e) {
            Log::error('Greška pri brisanju naloga', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Greška pri brisanju naloga.'], 500);
        }
    }
}
