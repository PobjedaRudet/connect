<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Funkcija;
use App\Models\ProductionOrder;
use App\Mail\OrderApprovalMail;
use App\Mail\OrderProxyApprovedInfoMail;
use App\Mail\OrderProductionApprovedInfoMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use App\Mail\OrderFinalApprovedMail;

class ApprovalController extends Controller
{
    /**
     * Canonical approval hierarchy (filter to only functions that participate in production order approvals).
     * Order in DB (Redosljed) is respected among these items.
     */
    private function approvalHierarchy(): array
    {
        $flow = ['Radnik', 'Šef Komercijale', 'Direktor Komercijale', 'Direktor Proizvodnje', 'Šef Operative'];
        return Funkcija::whereIn('Funkcija', $flow)
            ->orderBy('Redosljed')
            ->pluck('Funkcija')
            ->toArray();
    }

    /**
     * Production approval group: Direktor Proizvodnje + Zamjenik1 + Zamjenik2.
     * All three receive the approval email and can sign; first signature closes the other two.
     */
    private function productionApprovalGroup(): array
    {
        return ['Direktor Proizvodnje', 'Zamjenik1', 'Zamjenik2'];
    }

    private function isProductionApprovalFunkcija(?string $funkcija): bool
    {
        return $funkcija !== null && in_array($funkcija, $this->productionApprovalGroup(), true);
    }

    private function effectiveHierarchyFunkcija(string $funkcija): string
    {
        return $this->isProductionApprovalFunkcija($funkcija) ? 'Direktor Proizvodnje' : $funkcija;
    }

    private function closeProductionApprovalGroup(int $orderId, string $signedFunkcija, int $userId, ?string $comment = null): void
    {
        if (!$this->isProductionApprovalFunkcija($signedFunkcija)) {
            return;
        }
        $others = array_values(array_filter($this->productionApprovalGroup(), fn ($f) => $f !== $signedFunkcija));
        if (empty($others)) {
            return;
        }

        Approval::where('order_id', $orderId)
            ->whereIn('Funkcija', $others)
            ->whereNull('Odobreno')
            ->update([
                'UserId' => $userId,
                'Odobreno' => true,
                'DatumOdobravanja' => now(),
                'Komentar' => $comment,
                'signed_by_proxy' => true,
                'updated_at' => now(),
            ]);
    }

    private function notifyProductionGroup(ProductionOrder $order): void
    {
        $partnerName = $order->partner?->name ?? '';
        $totalQty = (float) ($order->details->sum('quantity'));
        $createdAt = optional($order->created_at)->format('Y-m-d H:i');
        $creatorName = $order->creator?->name ?? '';

        $notify = [];
        foreach ($this->productionApprovalGroup() as $pf) {
            $approvalId = Approval::where('order_id', $order->id)->where('Funkcija', $pf)->value('id');
            if (!$approvalId) continue;
            $notify[$pf] = [[
                'OrderNumber' => $order->OrderNumber,
                'Description' => $order->Description,
                'partner' => $partnerName,
                'total_qty' => $totalQty,
                'created_at' => $createdAt,
                'creator' => $creatorName,
                'approval_ids' => [$approvalId],
            ]];
        }

        if (!empty($notify)) {
            $this->notifyNextApprovers($notify);
        }
    }

    private function notifyProductionGroupApprovalInfo(int $orderId, int $approvedByUserId, string $approvedByFunkcija, bool $approvedAsProxy, ?string $comment = null): void
    {
        $order = ProductionOrder::find($orderId);
        if (!$order) {
            return;
        }

        $approvedByName = (string) (User::where('id', $approvedByUserId)->value('name') ?? '');
        $approvedAt = now()->format('Y-m-d H:i');

        // For production notifications, treat Zamjenik approvals as proxy for Direktor Proizvodnje.
        $proxy = $approvedAsProxy || ($approvedByFunkcija !== 'Direktor Proizvodnje');

        $emails = User::whereIn('funkcija', $this->productionApprovalGroup())
            ->pluck('email')
            ->filter()
            ->unique();

        foreach ($emails as $email) {
            Mail::to($email)->queue(new OrderProductionApprovedInfoMail(
                (string) $order->OrderNumber,
                $order->Description,
                $approvedByName,
                $approvedByFunkcija,
                (bool) $proxy,
                $approvedAt,
                $comment
            ));
        }
    }

    private function queueFinalApprovedEmailsForOrder(int $orderId): void
    {
        $order = ProductionOrder::with(['partner','creator','details.product','approvals'])->find($orderId);
        if (!$order) {
            return;
        }

        $summary = $this->summarizeOrderForEmail($order);
        $recipients = $this->getOrderParticipantsEmails($order);
        foreach ($recipients as $email) {
            Mail::to($email)->queue(new OrderFinalApprovedMail([$summary]));
        }
    }

    private function shouldFallbackFinalApprovalToProduction(): bool
    {
        // If there are no Šef Operative users, allow production signer to finalize.
        return !User::where('funkcija', 'Šef Operative')->exists();
    }

    private function maybeFinalizeWhenNoChiefOpsApprovers(int $orderId, int $userId, ?string $comment = null): bool
    {
        if (!$this->shouldFallbackFinalApprovalToProduction()) {
            return false;
        }

        Approval::where('order_id', $orderId)
            ->where('Funkcija', 'Šef Operative')
            ->whereNull('Odobreno')
            ->update([
                'UserId' => $userId,
                'Odobreno' => true,
                'DatumOdobravanja' => now(),
                'Komentar' => $comment,
                'signed_by_proxy' => true,
                'updated_at' => now(),
            ]);

        $allApproved = Approval::where('order_id', $orderId)->whereNull('Odobreno')->doesntExist()
            && Approval::where('order_id', $orderId)->where('Odobreno', false)->doesntExist();
        if ($allApproved) {
            ProductionOrder::where('id', $orderId)->update([
                'Status' => 'odobreno',
                'DatumPrijema' => now(),
            ]);
            return true;
        }

        return false;
    }

    private function notifyNextApprovers(array $ordersByFunkcija): void
    {
        foreach ($ordersByFunkcija as $funkcija => $orders) {
            $recipients = User::where('funkcija', $funkcija)->get(['id','email'])->filter(fn($u) => !empty($u->email));
            foreach ($recipients as $userRec) {
                Mail::to($userRec->email)->queue(new OrderApprovalMail($orders, $funkcija, $userRec->id));
            }
        }
    }
    // Batch send selected orders for approval: creates pending Approval rows for each funkcija in hierarchy
    public function sendForApproval(Request $request)
    {
        $data = $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:production_orders,id',
        ]);

    $hierarchy = $this->approvalHierarchy();

        $user = Auth::user();
        Log::info('Slanje naloga na odobrenje', ['user_id' => $user->id, 'order_ids' => $data['order_ids']]);
        $notifyMap = [];
        DB::transaction(function () use ($data, $hierarchy, $user, &$notifyMap) {
            foreach ($data['order_ids'] as $orderId) {
                $order = ProductionOrder::with(['partner', 'creator', 'details.product'])->findOrFail($orderId);
                // Only creator can send
                Log::info('Provjera ovlaštenja za slanje naloga', ['user_id' => $user->id, 'order_id' => $orderId, 'order_creator_id' => $order->user_id]);
                if ($order->user_id !== $user->id) {
                    Log::warning('Neovlašten pokušaj slanja naloga na odobrenje', ['user_id' => $user->id, 'order_id' => $orderId, 'order_creator_id' => $order->user_id]);
                    abort(403, 'Nemate ovlaštenje za slanje ovog naloga.');
                }
                foreach ($hierarchy as $funkcija) {
                    Approval::firstOrCreate([
                        'order_id' => $orderId,
                        'Funkcija' => $funkcija,
                    ], [
                        'UserId' => null,
                        'Odobreno' => null,
                        'DatumOdobravanja' => null,
                        'Komentar' => null,
                        'signed_by_proxy' => false,
                    ]);
                }

                // Add production substitutes approvals so they can also receive emails and sign.
                foreach (['Zamjenik1', 'Zamjenik2'] as $pf) {
                    Approval::firstOrCreate([
                        'order_id' => $orderId,
                        'Funkcija' => $pf,
                    ], [
                        'UserId' => null,
                        'Odobreno' => null,
                        'DatumOdobravanja' => null,
                        'Komentar' => null,
                        'signed_by_proxy' => false,
                    ]);
                }

                // Auto-approve Radnik step by the creator immediately
                if (in_array('Radnik', $hierarchy, true)) {
                    $radnikApproval = Approval::where('order_id', $orderId)
                        ->where('Funkcija', 'Radnik')
                        ->first();
                    if ($radnikApproval && $radnikApproval->Odobreno === null) {
                        $radnikApproval->fill([
                            'UserId' => $user->id,
                            'Odobreno' => true,
                            'DatumOdobravanja' => now(),
                            'signed_by_proxy' => false,
                        ])->save();
                    }
                }
                // Determine next approver funkcija (skip Radnik if present) and set status accordingly
                $pending = Approval::where('order_id', $order->id)->whereNull('Odobreno')
                    ->whereIn('Funkcija', $hierarchy)
                    ->orderByRaw("FIELD(Funkcija, '" . implode("','", $hierarchy) . "')")
                    ->pluck('Funkcija')->toArray();
                $nextF = null;
                foreach ($pending as $pf) {
                    if ($pf !== 'Radnik') { $nextF = $pf; break; }
                }
                $order->update(['Status' => $nextF ? ("na odobrenju kod " . $nextF) : 'na odobrenju']);
                if ($nextF) {
                    if ($nextF === 'Direktor Proizvodnje') {
                        // Notify DP + Zamjenici with their own approval IDs.
                        $partnerName = $order->partner?->name ?? '';
                        $totalQty = (float) ($order->details->sum('quantity'));
                        $createdAt = optional($order->created_at)->format('Y-m-d H:i');
                        $creatorName = $order->creator?->name ?? '';
                        foreach ($this->productionApprovalGroup() as $pf) {
                            $approvalId = Approval::where('order_id', $order->id)->where('Funkcija', $pf)->value('id');
                            if (!$approvalId) continue;
                            $notifyMap[$pf] = $notifyMap[$pf] ?? [];
                            $notifyMap[$pf][] = [
                                'OrderNumber' => $order->OrderNumber,
                                'Description' => $order->Description,
                                'partner' => $partnerName,
                                'total_qty' => $totalQty,
                                'created_at' => $createdAt,
                                'creator' => $creatorName,
                                'approval_ids' => [$approvalId],
                            ];
                        }
                    } else {
                        $notifyMap[$nextF] = $notifyMap[$nextF] ?? [];
                        // Prepare enriched info for email with approval id
                        $partnerName = $order->partner?->name ?? '';
                        $totalQty = (float) ($order->details->sum('quantity'));
                        $createdAt = optional($order->created_at)->format('Y-m-d H:i');
                        $creatorName = $order->creator?->name ?? '';
                        $approvalId = Approval::where('order_id', $order->id)->where('Funkcija', $nextF)->value('id');
                        $notifyMap[$nextF][] = [
                            'OrderNumber' => $order->OrderNumber,
                            'Description' => $order->Description,
                            'partner' => $partnerName,
                            'total_qty' => $totalQty,
                            'created_at' => $createdAt,
                            'creator' => $creatorName,
                            'approval_ids' => $approvalId ? [$approvalId] : [],
                        ];
                    }
                }
            }
        });

        // Send notification emails to mapped approvers per funkcija (queued)
        $this->notifyNextApprovers($notifyMap);

        return response()->json(['message' => 'Nalozi poslani na odobrenje.']);
    }

    // List all approvals pending for the current user's funkcija based on role mapping
    public function pending(Request $request)
    {
        $user = Auth::user();
        $funkcija = $this->mapUserToFunkcija($user);
        if (!$funkcija) {
            return $request->boolean('summary')
                ? response()->json(['count' => 0])
                : response()->json(['data' => []]);
        }

        // Show only orders where all previous in hierarchy are approved and current is pending
        $hierarchy = $this->approvalHierarchy();
        $effective = $this->effectiveHierarchyFunkcija($funkcija);
        $pos = array_search($effective, $hierarchy, true);
        if ($pos === false) {
            return $request->boolean('summary')
                ? response()->json(['count' => 0])
                : response()->json(['data' => []]);
        }
        $prev = array_slice($hierarchy, 0, $pos);
        $base = ProductionOrder::query()
            ->where('is_void', false)
            ->whereHas('approvals', function ($q) use ($funkcija) {
                $q->where('Funkcija', $funkcija)->whereNull('Odobreno');
            })
            ->when(count($prev) > 0, function ($q) use ($prev) {
                foreach ($prev as $pf) {
                    $q->whereHas('approvals', function ($qq) use ($pf) {
                        $qq->where('Funkcija', $pf)->where('Odobreno', true);
                    });
                }
            });

        if ($request->boolean('summary')) {
            return response()->json(['count' => (clone $base)->count()]);
        }

        $orders = $base
            ->with(['approvals:id,order_id,Funkcija,Odobreno', 'partner:id,name', 'creator:id,name'])
            ->withSum('details as total_quantity', 'quantity')
            ->select(['id','OrderNumber','Description','partner_id','user_id','created_at','OrderDate'])
            ->latest('id')
            ->get()
            ->map(function ($order) use ($funkcija) {
                $current = $order->approvals->first(function ($a) use ($funkcija) {
                    return $a->Funkcija === $funkcija && $a->Odobreno === null;
                });
                $createdAt = optional($order->created_at)->format('Y-m-d H:i');
                $creatorName = $order->creator?->name ?? null;
                return [
                    'id' => $order->id,
                    'OrderNumber' => $order->OrderNumber,
                    'Description' => $order->Description,
                    'partner' => $order->partner?->name,
                    'total_quantity' => (float) ($order->total_quantity ?? 0),
                    'current_approval_id' => $current?->id,
                    'created_at' => $createdAt,
                    'OrderDate' => $order->OrderDate,
                    'creator_name' => $creatorName,
                    'user_name' => $creatorName,
                ];
            });

        return response()->json(['data' => $orders]);
    }

    // JSON API: pending approvals for the user's funkcija (or specific funkcija for admins)
    // Route: GET /api/v1/approvals/pending
    // Query params:
    // - per_page (int, default 15, max 100)
    // - page (int)
    // - q (search in OrderNumber/Description/partner/creator)
    // - summary=1 (only returns { count })
    // - funkcija=... (admin-only override to view another funkcija)
    public function pendingApi(Request $request)
    {
        $user = Auth::user();
        $isAdmin = (bool) ($user?->isadmin ?? false);
        $funkcija = $this->mapUserToFunkcija($user);
        $requestedFunkcija = (string) $request->query('funkcija', '');
        if ($isAdmin && $requestedFunkcija !== '') {
            $funkcija = $requestedFunkcija;
        }

        if (!$funkcija) {
            // No funkcija mapped; empty
            if ((string) $request->query('summary', '0') === '1') {
                return response()->json(['count' => 0]);
            }
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => (int) $request->query('per_page', 15),
                    'total' => 0,
                    'last_page' => 1,
                ],
            ]);
        }

        // Summary only
        if ((string) $request->query('summary', '0') === '1') {
            $count = Approval::query()
                ->join('production_orders', 'production_orders.id', '=', 'approvals.order_id')
                ->whereNull('approvals.Odobreno')
                ->where('approvals.Funkcija', $funkcija)
                ->where(function ($q) {
                    $q->whereNull('production_orders.is_void')->orWhere('production_orders.is_void', false);
                })
                ->count();
            return response()->json(['count' => $count]);
        }

        $perPage = min(max((int) $request->query('per_page', 15), 5), 100);
        $q = trim((string) $request->query('q', ''));

        $query = Approval::query()
            ->select([
                'approvals.id as approval_id',
                'production_orders.id as order_id',
                'production_orders.OrderNumber',
                'production_orders.Description',
                'production_orders.created_at',
                'partners.name as partner_name',
                'users.name as creator_name',
            ])
            ->join('production_orders', 'production_orders.id', '=', 'approvals.order_id')
            ->leftJoin('partners', 'partners.id', '=', 'production_orders.partner_id')
            ->leftJoin('users', 'users.id', '=', 'production_orders.user_id')
            ->whereNull('approvals.Odobreno')
            ->where('approvals.Funkcija', $funkcija)
            ->where(function ($w) {
                $w->whereNull('production_orders.is_void')->orWhere('production_orders.is_void', false);
            });

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('production_orders.OrderNumber', 'like', "%{$q}%")
                  ->orWhere('production_orders.Description', 'like', "%{$q}%")
                  ->orWhere('partners.name', 'like', "%{$q}%")
                  ->orWhere('users.name', 'like', "%{$q}%");
            });
        }

        $page = $query->orderByDesc('approvals.id')->paginate($perPage);

        // Compute total quantities per order in one extra query
        $orderIds = collect($page->items())->pluck('order_id')->filter()->unique()->all();
        $totals = empty($orderIds) ? collect() : ProductionOrder::query()
            ->whereIn('id', $orderIds)
            ->withSum('details as total_quantity', 'quantity')
            ->pluck('total_quantity', 'id');

        $hierarchy = $this->approvalHierarchy();
        $effective = $this->effectiveHierarchyFunkcija($funkcija);
        $uPos = array_search($effective, $hierarchy, true);

        $items = collect($page->items())->map(function ($row) use ($funkcija, $totals) {
            $totalQty = 0.0;
            if ($totals instanceof \Illuminate\Support\Collection) {
                $totalQty = (float) ($totals[$row->order_id] ?? 0);
            }
            return [
                'approval_id' => (int) $row->approval_id,
                'order_id' => (int) $row->order_id,
                'order_number' => $row->OrderNumber,
                'description' => $row->Description,
                'partner' => $row->partner_name,
                'creator' => $row->creator_name,
                'created_at' => optional($row->created_at)->format('Y-m-d H:i'),
                'total_quantity' => $totalQty,
                'current_funkcija' => $funkcija,
                'can_proxy_up' => false,
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function approve(Request $request, Approval $approval)
    {
        $user = Auth::user();
        $funkcija = $this->mapUserToFunkcija($user);
        $proxy = $request->boolean('proxy', false);
        $comment = $request->input('Komentar');

        if (!$funkcija) {
            Log::warning('Approval denied: user has no valid funkcija', [
                'user_id' => $user?->id,
                'user_funkcija' => $user?->funkcija,
                'approval_id' => $approval->id,
                'approval_funkcija' => $approval->Funkcija,
            ]);
            return response()->json(['message' => 'Vaša funkcija nije postavljena ili ne postoji u šifrarniku funkcija.'], 403);
        }

        // Enforce that user is the assigned funkcija, or allowed production-group signer
        if ($approval->Funkcija !== $funkcija) {
            // If same order has a pending approval for user's funkcija, switch to that (UI may have sent wrong id)
            if (!$proxy) {
                $correctApproval = Approval::where('order_id', $approval->order_id)
                    ->where('Funkcija', $funkcija)
                    ->whereNull('Odobreno')
                    ->first();
                if ($correctApproval) {
                    $approval = $correctApproval;
                }
            }
        }

        if ($approval->Funkcija !== $funkcija) {
            // Allow any production-group member to sign any production-group approval.
            if ($this->isProductionApprovalFunkcija($funkcija) && $this->isProductionApprovalFunkcija((string) $approval->Funkcija)) {
                $proxy = true;
            } else {
                // Otherwise, keep the existing rule: only the target funkcija can approve.
                Log::warning('Approval denied: funkcija mismatch', [
                    'user_id' => $user->id,
                    'user_funkcija' => $funkcija,
                    'approval_id' => $approval->id,
                    'approval_funkcija' => $approval->Funkcija,
                    'proxy' => $proxy,
                ]);
                return response()->json(['message' => 'Niste ovlašteni za odobrenje ovog koraka.'], 403);
            }
        }

        if ($approval->Odobreno !== null) {
            return response()->json(['message' => 'Ovaj korak je već obrađen.'], 422);
        }

        // Ensure all previous approvals are approved (use effective hierarchy position for production substitutes)
        $hierarchy = $this->approvalHierarchy();
        $effective = $this->effectiveHierarchyFunkcija((string) $approval->Funkcija);
        $aPos = array_search($effective, $hierarchy, true);
        $prev = array_slice($hierarchy, 0, $aPos);
        foreach ($prev as $pf) {
            $prevApproval = Approval::where('order_id', $approval->order_id)->where('Funkcija', $pf)->first();
            if (!$prevApproval || $prevApproval->Odobreno !== true) {
                return response()->json(['message' => 'Prethodni koraci nisu odobreni.'], 422);
            }
        }

        $approval->fill([
            'UserId' => $user->id,
            'Odobreno' => true,
            'DatumOdobravanja' => now(),
            'Komentar' => $comment,
            'signed_by_proxy' => $proxy,
        ])->save();

        $fallbackFinalized = false;

        // If any production-group member signed, close the rest of the production group.
        if ($this->isProductionApprovalFunkcija((string) $approval->Funkcija)) {
            $this->closeProductionApprovalGroup((int) $approval->order_id, (string) $approval->Funkcija, (int) $user->id, $comment);
            $fallbackFinalized = $this->maybeFinalizeWhenNoChiefOpsApprovers((int) $approval->order_id, (int) $user->id, $comment);
            $this->notifyProductionGroupApprovalInfo((int) $approval->order_id, (int) $user->id, (string) $funkcija, (bool) $proxy, $comment);
        }

        // If all approved, update order status
        $allApproved = Approval::where('order_id', $approval->order_id)->whereNull('Odobreno')->doesntExist()
            && Approval::where('order_id', $approval->order_id)->where('Odobreno', false)->doesntExist();
        if ($allApproved) {
            // If Šef Operative approved (final step), also stamp DatumPrijema
            $update = ['Status' => 'odobreno'];
            if ($approval->Funkcija === 'Šef Operative' || $fallbackFinalized) {
                $update['DatumPrijema'] = $approval->DatumOdobravanja ?? now();
            }
            ProductionOrder::where('id', $approval->order_id)->update($update);
            // If Šef Operative approved (final step), notify all participants (creator + actual approvers) with HTML email
            if ($approval->Funkcija === 'Šef Operative' || $fallbackFinalized) {
                $this->queueFinalApprovedEmailsForOrder((int) $approval->order_id);
            }
        } else {
            // Otherwise set to next approver funkcija
            $hierarchy = $this->approvalHierarchy();
            $pending = Approval::where('order_id', $approval->order_id)->whereNull('Odobreno')
                ->whereIn('Funkcija', $hierarchy)
                ->orderByRaw("FIELD(Funkcija, '" . implode("','", $hierarchy) . "')")
                ->pluck('Funkcija')->toArray();
            $nextF = null;
            foreach ($pending as $pf) {
                if ($pf !== 'Radnik') { $nextF = $pf; break; }
            }
            if ($nextF) {
                ProductionOrder::where('id', $approval->order_id)->update(['Status' => 'na odobrenju kod ' . $nextF]);
                // Notify next approver funkcija with signed links
                $order = ProductionOrder::with(['partner','creator','details'])->find($approval->order_id);
                if ($order) {
                    if ($nextF === 'Direktor Proizvodnje') {
                        $this->notifyProductionGroup($order);
                    } else {
                        $partnerName = $order->partner?->name ?? '';
                        $totalQty = (float) ($order->details->sum('quantity'));
                        $createdAt = optional($order->created_at)->format('Y-m-d H:i');
                        $creatorName = $order->creator?->name ?? '';
                        $approvalId = Approval::where('order_id', $order->id)->where('Funkcija', $nextF)->value('id');
                        $ordersForMail = [[
                            'OrderNumber' => $order->OrderNumber,
                            'Description' => $order->Description,
                            'partner' => $partnerName,
                            'total_qty' => $totalQty,
                            'created_at' => $createdAt,
                            'creator' => $creatorName,
                            'approval_ids' => $approvalId ? [$approvalId] : [],
                        ]];
                        $this->notifyNextApprovers([$nextF => $ordersForMail]);
                    }
                }
            }
        }

        return response()->json(['message' => 'Odobreno.']);
    }

    public function reject(Request $request, Approval $approval)
    {
        $user = Auth::user();
        $funkcija = $this->mapUserToFunkcija($user);
        $comment = $request->validate(['Komentar' => 'required|string|max:255'])['Komentar'];

        if ($approval->Funkcija !== $funkcija) {
            return response()->json(['message' => 'Niste ovlašteni za odbijanje ovog koraka.'], 403);
        }

        if ($approval->Odobreno !== null) {
            return response()->json(['message' => 'Ovaj korak je već obrađen.'], 422);
        }

        // Ensure previous are approved (only allow rejecting your turn)
    $hierarchy = $this->approvalHierarchy();
        $aPos = array_search($approval->Funkcija, $hierarchy, true);
        $prev = array_slice($hierarchy, 0, $aPos);
        foreach ($prev as $pf) {
            $prevApproval = Approval::where('order_id', $approval->order_id)->where('Funkcija', $pf)->first();
            if (!$prevApproval || $prevApproval->Odobreno !== true) {
                return response()->json(['message' => 'Prethodni koraci nisu odobreni.'], 422);
            }
        }

        $approval->fill([
            'UserId' => $user->id,
            'Odobreno' => false,
            'DatumOdobravanja' => now(),
            'Komentar' => $comment,
            'signed_by_proxy' => false,
        ])->save();

        ProductionOrder::where('id', $approval->order_id)->update(['Status' => 'odbijeno']);

        return response()->json(['message' => 'Odbijeno.']);
    }

    // Bulk approve multiple approvals by current user's funkcija, consolidating notification to next funkcija
    public function bulkApprove(Request $request)
    {
        $data = $request->validate([
            'approval_ids' => 'required|array|min:1',
            'approval_ids.*' => 'integer|exists:approvals,id',
        ]);

        $user = Auth::user();
        $funkcija = $this->mapUserToFunkcija($user);
        if (!$funkcija) {
            return response()->json(['message' => 'Vaša funkcija nije postavljena ili ne postoji u šifrarniku funkcija.'], 403);
        }

    $hierarchy = $this->approvalHierarchy();
        $notifyMap = []; // nextFunkcija => [enriched order info]
        $finalNotify = []; // recipientEmail => [order summaries] for final approval by Šef Operative (or fallback finalization)
        $productionApprovedNotify = []; // list of production approvals to inform DP+Zamjenici
        $ok = 0; $fail = 0;

        DB::transaction(function () use ($data, $user, $funkcija, $hierarchy, &$notifyMap, &$ok, &$fail, &$finalNotify, &$productionApprovedNotify) {
            foreach ($data['approval_ids'] as $id) {
                /** @var Approval $approval */
                $approval = Approval::find($id);
                if (!$approval) { $fail++; continue; }

                // Enforce it's the user's own funkcija approval (or production-group cross-sign) and still pending
                $isOwn = ($approval->Funkcija === $funkcija);
                $isProdGroupCross = (!$isOwn && $this->isProductionApprovalFunkcija($funkcija) && $this->isProductionApprovalFunkcija((string) $approval->Funkcija));
                if ((!$isOwn && !$isProdGroupCross) || $approval->Odobreno !== null) { $fail++; continue; }

                // Ensure all previous approvals are approved (use effective hierarchy position for production substitutes)
                $effective = $this->effectiveHierarchyFunkcija((string) $approval->Funkcija);
                $aPos = array_search($effective, $hierarchy, true);
                $prev = array_slice($hierarchy, 0, $aPos);
                $prevAllApproved = true;
                foreach ($prev as $pf) {
                    $prevApproval = Approval::where('order_id', $approval->order_id)->where('Funkcija', $pf)->first();
                    if (!$prevApproval || $prevApproval->Odobreno !== true) { $prevAllApproved = false; break; }
                }
                if (!$prevAllApproved) { $fail++; continue; }

                // Approve current
                $approval->fill([
                    'UserId' => $user->id,
                    'Odobreno' => true,
                    'DatumOdobravanja' => now(),
                    'signed_by_proxy' => $isProdGroupCross,
                ])->save();

                $fallbackFinalized = false;

                if ($this->isProductionApprovalFunkcija((string) $approval->Funkcija)) {
                    $this->closeProductionApprovalGroup((int) $approval->order_id, (string) $approval->Funkcija, (int) $user->id, null);
                    $fallbackFinalized = $this->maybeFinalizeWhenNoChiefOpsApprovers((int) $approval->order_id, (int) $user->id, null);

                    $productionApprovedNotify[] = [
                        'order_id' => (int) $approval->order_id,
                        'user_id' => (int) $user->id,
                        'funkcija' => (string) $funkcija,
                        'proxy' => (bool) $isProdGroupCross,
                        'comment' => null,
                    ];
                }

                // Move order to next step or finalize
                $order = ProductionOrder::with(['partner','creator','details.product'])->find($approval->order_id);
                if (!$order) { $fail++; continue; }

                $allApproved = Approval::where('order_id', $order->id)->whereNull('Odobreno')->doesntExist()
                    && Approval::where('order_id', $order->id)->where('Odobreno', false)->doesntExist();
                if ($allApproved) {
                    $update = ['Status' => 'odobreno'];
                    if ($approval->Funkcija === 'Šef Operative' || $fallbackFinalized) {
                        $update['DatumPrijema'] = $approval->DatumOdobravanja ?? now();
                    }
                    $order->update($update);
                    // If final step performed by Šef Operative, prepare consolidated participant notifications
                    if ($approval->Funkcija === 'Šef Operative' || $fallbackFinalized) {
                        $summary = $this->summarizeOrderForEmail($order);
                        $recipients = $this->getOrderParticipantsEmails($order);
                        foreach ($recipients as $email) {
                            $finalNotify[$email] = $finalNotify[$email] ?? [];
                            $finalNotify[$email][] = $summary;
                        }
                    }
                } else {
                    $pending = Approval::where('order_id', $order->id)->whereNull('Odobreno')
                        ->whereIn('Funkcija', $hierarchy)
                        ->orderByRaw("FIELD(Funkcija, '" . implode("','", $hierarchy) . "')")
                        ->pluck('Funkcija')->toArray();
                    $nextF = null;
                    foreach ($pending as $pf) { if ($pf !== 'Radnik') { $nextF = $pf; break; } }
                    if ($nextF) {
                        $order->update(['Status' => 'na odobrenju kod ' . $nextF]);
                        // Enrich info for consolidated email to nextF
                        $partnerName = $order->partner?->name ?? '';
                        $totalQty = (float) ($order->details->sum('quantity'));
                        $createdAt = optional($order->created_at)->format('Y-m-d H:i');
                        $creatorName = $order->creator?->name ?? '';
                        if ($nextF === 'Direktor Proizvodnje') {
                            foreach ($this->productionApprovalGroup() as $pf) {
                                $approvalId = Approval::where('order_id', $order->id)->where('Funkcija', $pf)->value('id');
                                if (!$approvalId) continue;
                                $notifyMap[$pf] = $notifyMap[$pf] ?? [];
                                $notifyMap[$pf][] = [
                                    'OrderNumber' => $order->OrderNumber,
                                    'Description' => $order->Description,
                                    'partner' => $partnerName,
                                    'total_qty' => $totalQty,
                                    'created_at' => $createdAt,
                                    'creator' => $creatorName,
                                    'approval_ids' => [$approvalId],
                                ];
                            }
                        } else {
                            $approvalId = Approval::where('order_id', $order->id)->where('Funkcija', $nextF)->value('id');
                            $notifyMap[$nextF] = $notifyMap[$nextF] ?? [];
                            $notifyMap[$nextF][] = [
                                'OrderNumber' => $order->OrderNumber,
                                'Description' => $order->Description,
                                'partner' => $partnerName,
                                'total_qty' => $totalQty,
                                'created_at' => $createdAt,
                                'creator' => $creatorName,
                                'approval_ids' => $approvalId ? [$approvalId] : [],
                            ];
                        }
                    }
                }

                $ok++;
            }
        });

        // Send consolidated email per next funkcija with signed links (queued)
        $this->notifyNextApprovers($notifyMap);

        // Inform production group (DP + Zamjenik1/Zamjenik2) about production approvals
        foreach ($productionApprovedNotify as $n) {
            $this->notifyProductionGroupApprovalInfo((int) $n['order_id'], (int) $n['user_id'], (string) $n['funkcija'], (bool) $n['proxy'], $n['comment']);
        }

        // Send consolidated final-approval emails to participants (Šef Operative case)
        foreach ($finalNotify as $email => $summaries) {
            if (empty($summaries)) continue;
            Mail::to($email)->queue(new OrderFinalApprovedMail($summaries));
        }

        return response()->json(['message' => "Odobreno: {$ok}, Neuspješno: {$fail}."]);
    }

    private function buildOrderEmailLine(ProductionOrder $order): string
    {
        $partnerName = $order->partner?->name ?? '';
        $type = $order->Tip ?: optional($order->details->first()?->product)->TypeOfProduct;
        $metraza = $order->Metraza;
        $provodnik = $order->VrstaProvodnika ?: optional($order->details->first()?->product)->VrstaProvodnika;
        $totalQty = (float) ($order->details->sum('quantity'));
        $createdAt = optional($order->created_at)->format('Y-m-d H:i');
        $creatorName = $order->creator?->name ?? '';
        $desc = $order->Description ?? '';
        return "- Nalog: {$order->OrderNumber} | {$desc} | Kupac: {$partnerName} | Tip: {$type} | Metraža: {$metraza} | Provodnik: {$provodnik} | Količina: {$totalQty} | Kreirano: {$createdAt} | Kreirao: {$creatorName}";
    }

    private function summarizeOrderForEmail(ProductionOrder $order): array
    {
        $partnerName = $order->partner?->name ?? '';
        $totalQty = (float) ($order->details->sum('quantity'));
        $createdAt = optional($order->created_at)->format('Y-m-d H:i');
        $creatorName = $order->creator?->name ?? '';
        return [
            'OrderNumber' => $order->OrderNumber,
            'Description' => $order->Description,
            'partner' => $partnerName,
            'total_qty' => $totalQty,
            'created_at' => $createdAt,
            'creator' => $creatorName,
        ];
    }

    private function getOrderParticipantsEmails(ProductionOrder $order): array
    {
        // Creator + all actual approvers for this order
        $creatorEmail = optional($order->creator)->email;
        $approvals = $order->relationLoaded('approvals') ? $order->approvals : Approval::where('order_id', $order->id)->get();

        // 1) Emails stvarnih potpisnika (UserId koji su odobrili)
        $userIds = $approvals->whereNotNull('UserId')->where('Odobreno', true)->pluck('UserId')->unique()->values()->all();
        $approverEmails = empty($userIds) ? collect() : User::whereIn('id', $userIds)->pluck('email');

        // 2) Ako je potpisano kao proxy, obavijesti i osobe na ciljnoj funkciji (za koje je neko potpisao)
        $proxyTargets = $approvals
            ->where('Odobreno', true)
            ->where('signed_by_proxy', true)
            ->pluck('Funkcija')
            ->unique()
            ->values()
            ->all();
        $proxyTargetEmails = empty($proxyTargets) ? collect() : User::whereIn('funkcija', $proxyTargets)->pluck('email');

        $recipients = collect([$creatorEmail])
            ->merge($approverEmails)
            ->merge($proxyTargetEmails)
            ->filter()
            ->unique();

        // If Direktor Proizvodnje is getting the email, Zamjenik1/Zamjenik2 should also get it.
        $dpEmails = User::where('funkcija', 'Direktor Proizvodnje')->pluck('email')->filter();
        if ($dpEmails->intersect($recipients)->isNotEmpty()) {
            $subEmails = User::whereIn('funkcija', ['Zamjenik1', 'Zamjenik2'])->pluck('email')->filter();
            $recipients = $recipients->merge($subEmails);
        }

        return $recipients
            ->unique()
            ->values()
            ->all();
    }

    // Approve one level above (immediate superior) for a given order
    public function approveOneUp(Request $request, ProductionOrder $order)
    {
        $user = Auth::user();
        $funkcija = $this->mapUserToFunkcija($user);
        $comment = $request->input('Komentar');

        if (!$funkcija) {
            return response()->json(['message' => 'Vaša funkcija nije postavljena ili ne postoji u šifrarniku funkcija.'], 403);
        }

    $hierarchy = $this->approvalHierarchy();
        $uPos = array_search($funkcija, $hierarchy, true);
        if ($uPos === false) {
            return response()->json(['message' => 'Vaša funkcija nije u hijerarhiji.'], 403);
        }

        $targetPos = $uPos + 1;
        if (!isset($hierarchy[$targetPos])) {
            return response()->json(['message' => 'Nema nadređenog nivoa za odobravanje.'], 422);
        }

        $targetF = $hierarchy[$targetPos];
        // Find pending approval for target funkcija
        $approval = Approval::where('order_id', $order->id)
            ->where('Funkcija', $targetF)
            ->whereNull('Odobreno')
            ->first();
        if (!$approval) {
            return response()->json(['message' => 'Nema otvorenog koraka za nadređeni nivo.'], 422);
        }

        // Ensure all steps prior to target are approved
            $prevToUser = array_slice($hierarchy, 0, $uPos);
            $missing = [];
            foreach ($prevToUser as $pf) {
                $prevApproval = Approval::where('order_id', $order->id)->where('Funkcija', $pf)->first();
                if (!$prevApproval || $prevApproval->Odobreno !== true) {
                    $missing[] = $pf;
                }
            }
            if (!empty($missing)) {
                Log::warning('approveOneUp blocked: missing previous steps', [
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                    'user_funkcija' => $funkcija,
                    'missing' => $missing,
                ]);
                return response()->json(['message' => 'Prethodni koraci nisu odobreni: ' . implode(', ', $missing) . '.'], 422);
            }

        // Auto-approve the user's own pending step if it exists
        $myApproval = Approval::where('order_id', $order->id)
            ->where('Funkcija', $funkcija)
            ->whereNull('Odobreno')
            ->first();
        if ($myApproval) {
            $myApproval->fill([
                'UserId' => $user->id,
                'Odobreno' => true,
                'DatumOdobravanja' => now(),
                'Komentar' => $comment,
                'signed_by_proxy' => false,
            ])->save();
        }

        // Perform approval as proxy (one-up)
        $approval->fill([
            'UserId' => $user->id,
            'Odobreno' => true,
            'DatumOdobravanja' => now(),
            'Komentar' => $comment,
            'signed_by_proxy' => true,
        ])->save();

        // Inform Direktor Komercijale when Šef Komercijale approved as proxy.
        if ($funkcija === 'Šef Komercijale' && $targetF === 'Direktor Komercijale') {
            $emails = User::where('funkcija', 'Direktor Komercijale')->pluck('email')->filter();
            foreach ($emails as $email) {
                Mail::to($email)->queue(new OrderProxyApprovedInfoMail(
                    (string) $order->OrderNumber,
                    $order->Description,
                    $funkcija,
                    (string) ($user->name ?? ''),
                    $targetF,
                    (string) now(),
                    $comment
                ));
            }
        }

        $fallbackFinalized = false;
        if ($this->isProductionApprovalFunkcija((string) $approval->Funkcija)) {
            $this->closeProductionApprovalGroup((int) $order->id, (string) $approval->Funkcija, (int) $user->id, $comment);
            $fallbackFinalized = $this->maybeFinalizeWhenNoChiefOpsApprovers((int) $order->id, (int) $user->id, $comment);
            $this->notifyProductionGroupApprovalInfo((int) $order->id, (int) $user->id, (string) $funkcija, true, $comment);
        }

        // Check if all approved now
        $allApproved = Approval::where('order_id', $order->id)->whereNull('Odobreno')->doesntExist()
            && Approval::where('order_id', $order->id)->where('Odobreno', false)->doesntExist();
        if ($allApproved) {
            $update = ['Status' => 'odobreno'];
            if ($approval->Funkcija === 'Šef Operative' || $fallbackFinalized) {
                $update['DatumPrijema'] = $approval->DatumOdobravanja ?? now();
            }
            ProductionOrder::where('id', $order->id)->update($update);
            if ($approval->Funkcija === 'Šef Operative' || $fallbackFinalized) {
                $this->queueFinalApprovedEmailsForOrder((int) $order->id);
            }
        } else {
            // Move status to next pending funkcija
            $pending = Approval::where('order_id', $order->id)->whereNull('Odobreno')
                ->whereIn('Funkcija', $hierarchy)
                ->orderByRaw("FIELD(Funkcija, '" . implode("','", $hierarchy) . "')")
                ->pluck('Funkcija')->toArray();
            $nextF = null;
            foreach ($pending as $pf) {
                if ($pf !== 'Radnik') { $nextF = $pf; break; }
            }

            if ($nextF) {
                ProductionOrder::where('id', $order->id)->update(['Status' => 'na odobrenju kod ' . $nextF]);
                $partnerName = $order->partner?->name ?? '';
                $totalQty = (float) ($order->details()->sum('quantity'));
                $createdAt = optional($order->created_at)->format('Y-m-d H:i');
                $creatorName = $order->creator?->name ?? '';
                $approvalId = Approval::where('order_id', $order->id)->where('Funkcija', $nextF)->value('id');
                $ordersForMail = [[
                    'OrderNumber' => $order->OrderNumber,
                    'Description' => $order->Description,
                    'partner' => $partnerName,
                    'total_qty' => $totalQty,
                    'created_at' => $createdAt,
                    'creator' => $creatorName,
                    'approval_ids' => $approvalId ? [$approvalId] : [],
                ]];
                if ($nextF === 'Direktor Proizvodnje') {
                    $this->notifyProductionGroup($order);
                } else {
                    $recipients = User::where('funkcija', $nextF)->get(['id','email'])->filter(fn($u) => !empty($u->email));
                    foreach ($recipients as $userRec) {
                        Mail::to($userRec->email)->queue(new OrderApprovalMail($ordersForMail, $nextF, $userRec->id));
                    }
                }
            }
        }

        return response()->json(['message' => 'Odobreno (1 nivo iznad).']);
    }

    private function mapUserToFunkcija($user): ?string
    {
        // Prefer explicit users.funkcija (FK to funkcije) with normalization
        $value = $user->funkcija ?? null;
        if (!$value) return null;
        $trimmed = trim($value);
        // Exact match first
        $canonical = Funkcija::where('Funkcija', $trimmed)->value('Funkcija');
        if ($canonical) return $canonical;
        // Case-insensitive fallback
        $all = Funkcija::pluck('Funkcija');
        foreach ($all as $f) {
            if (mb_strtolower(trim($f), 'UTF-8') === mb_strtolower($trimmed, 'UTF-8')) {
                return $f; // return canonical stored value
            }
        }
        return null;
    }

    // Signed-link: approve specific approval ids directly
    public function emailDirectApprove(Request $request)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Nevažeći ili istekao potpisani link.');
        }

        $uid = (int) $request->query('uid');
        $csv = (string) $request->query('approval_ids', '');
        $ids = array_values(array_filter(array_map('intval', array_filter(explode(',', $csv)))));
        if (empty($ids)) {
            return redirect()->route('approvals.mine')->with('status', 'Nema naloga za odobrenje.');
        }

        $user = Auth::user();
        if (!$user || $user->id !== $uid) {
            // Force login as the intended user
            return redirect()->guest(route('login'));
        }

        $funkcija = $this->mapUserToFunkcija($user);
        if (!$funkcija) {
            return redirect()->route('approvals.mine')->with('error', 'Vaša funkcija nije postavljena.');
        }

        // Approve many like bulkApprove but restricted to provided ids
        $ok = 0; $fail = 0;
        $productionApprovedNotify = [];
        $fallbackFinalizedOrders = [];
        $hierarchy = $this->approvalHierarchy();
        DB::transaction(function () use (&$ok, &$fail, $ids, $user, $funkcija, $hierarchy, &$productionApprovedNotify, &$fallbackFinalizedOrders) {
            foreach ($ids as $id) {
                $approval = Approval::find($id);
                if (!$approval) { $fail++; continue; }
                // Validate ownership and pending
            $isOwn = ($approval->Funkcija === $funkcija);
            $isProdGroupCross = (!$isOwn && $this->isProductionApprovalFunkcija($funkcija) && $this->isProductionApprovalFunkcija((string) $approval->Funkcija));
            if ((!$isOwn && !$isProdGroupCross) || $approval->Odobreno !== null) { $fail++; continue; }
                // Validate order not void or final
                $order = ProductionOrder::find($approval->order_id);
                if (!$order || $order->is_void || in_array($order->Status, ['odobreno','odbijeno'])) { $fail++; continue; }
                // Ensure all previous approvals are approved (use effective hierarchy position for production substitutes)
                $effective = $this->effectiveHierarchyFunkcija((string) $approval->Funkcija);
                $aPos = array_search($effective, $hierarchy, true);
                $prev = array_slice($hierarchy, 0, $aPos);
                $prevAllApproved = true;
                foreach ($prev as $pf) {
                    $prevApproval = Approval::where('order_id', $approval->order_id)->where('Funkcija', $pf)->first();
                    if (!$prevApproval || $prevApproval->Odobreno !== true) { $prevAllApproved = false; break; }
                }
                if (!$prevAllApproved) { $fail++; continue; }

                $approval->fill([
                    'UserId' => $user->id,
                    'Odobreno' => true,
                    'DatumOdobravanja' => now(),
                    'signed_by_proxy' => $isProdGroupCross,
                ])->save();

                $fallbackFinalized = false;

                if ($this->isProductionApprovalFunkcija((string) $approval->Funkcija)) {
                    $this->closeProductionApprovalGroup((int) $approval->order_id, (string) $approval->Funkcija, (int) $user->id, null);
                    $fallbackFinalized = $this->maybeFinalizeWhenNoChiefOpsApprovers((int) $approval->order_id, (int) $user->id, null);
                    $productionApprovedNotify[] = [
                        'order_id' => (int) $approval->order_id,
                        'user_id' => (int) $user->id,
                        'funkcija' => (string) $funkcija,
                        'proxy' => (bool) $isProdGroupCross,
                        'comment' => null,
                    ];
                    if ($fallbackFinalized) {
                        $fallbackFinalizedOrders[] = (int) $approval->order_id;
                    }
                }

                // Move order forward or finalize
                $allApproved = Approval::where('order_id', $approval->order_id)->whereNull('Odobreno')->doesntExist()
                    && Approval::where('order_id', $approval->order_id)->where('Odobreno', false)->doesntExist();
                if ($allApproved) {
                    $update = ['Status' => 'odobreno'];
                    if ($approval->Funkcija === 'Šef Operative' || $fallbackFinalized) {
                        $update['DatumPrijema'] = $approval->DatumOdobravanja ?? now();
                    }
                    ProductionOrder::where('id', $approval->order_id)->update($update);

                    // If Šef Operative finalized via email link, notify all participants
                    if ($approval->Funkcija === 'Šef Operative' || $fallbackFinalized) {
                        try {
                            $this->queueFinalApprovedEmailsForOrder((int) $approval->order_id);
                        } catch (\Throwable $e) {
                            Log::error('EmailDirectApprove: failed to queue final approved mail', [
                                'order_id' => (int) $approval->order_id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                } else {
                    $pending = Approval::where('order_id', $approval->order_id)->whereNull('Odobreno')
                        ->whereIn('Funkcija', $hierarchy)
                        ->orderByRaw("FIELD(Funkcija, '" . implode("','", $hierarchy) . "')")
                        ->pluck('Funkcija')->toArray();
                    $nextF = null;
                    foreach ($pending as $pf) { if ($pf !== 'Radnik') { $nextF = $pf; break; } }
                    if ($nextF) {
                        ProductionOrder::where('id', $approval->order_id)->update(['Status' => 'na odobrenju kod ' . $nextF]);

                        // Notify next approver(s) by email (same as other approve flows)
                        $orderForMail = ProductionOrder::with(['partner','creator','details.product'])
                            ->find($approval->order_id);
                        if ($orderForMail) {
                            $ordersForMail = [[
                                'OrderNumber' => $orderForMail->OrderNumber,
                                'Description' => $orderForMail->Description,
                                'partner' => optional($orderForMail->partner)->name,
                                'total_qty' => (float) ($orderForMail->details->sum('quantity')),
                                'approval_ids' => Approval::where('order_id', $orderForMail->id)
                                    ->where('Funkcija', $nextF)
                                    ->whereNull('Odobreno')
                                    ->pluck('id')
                                    ->values()
                                    ->all(),
                            ]];

                            if ($nextF === 'Direktor Proizvodnje') {
                                $this->notifyProductionGroup($orderForMail);
                            } else {
                                $recipients = User::where('funkcija', $nextF)->get(['id','email']);
                                foreach ($recipients as $userRec) {
                                    if (!$userRec->email) continue;
                                    try {
                                        Mail::to($userRec->email)->queue(new OrderApprovalMail($ordersForMail, $nextF, $userRec->id));
                                    } catch (\Throwable $e) {
                                        Log::error('EmailDirectApprove: failed to queue next approver mail', [
                                            'order_id' => $orderForMail->id,
                                            'nextF' => $nextF,
                                            'user_id' => $userRec->id,
                                            'error' => $e->getMessage(),
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                }

                $ok++;
            }
        });

        foreach ($productionApprovedNotify as $n) {
            $this->notifyProductionGroupApprovalInfo((int) $n['order_id'], (int) $n['user_id'], (string) $n['funkcija'], (bool) $n['proxy'], $n['comment']);
        }

        return redirect()->route('approvals.mine')->with('status', "Odobreno: {$ok}, Neuspješno: {$fail}.");
    }

    // Signed-link: open pending approvals list for user; require login
    public function emailOpenPending(Request $request)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Nevažeći ili istekao potpisani link.');
        }
        $uid = (int) $request->query('uid');
        $user = Auth::user();
        if (!$user || $user->id !== $uid) {
            return redirect()->guest(route('login'));
        }
        $funkcija = $this->mapUserToFunkcija($user);
        if ($funkcija === 'Direktor Komercijale') {
            return redirect()->route('approvals.director.sales');
        }
        if ($funkcija === 'Direktor Proizvodnje') {
            return redirect()->route('approvals.director.production');
        }
        if ($funkcija === 'Zamjenik1' || $funkcija === 'Zamjenik2') {
            return redirect()->route('approvals.director.production');
        }
        if ($funkcija === 'Šef Operative') {
            return redirect()->route('approvals.chief.operations');
        }
        return redirect()->route('approvals.mine');
    }
}

