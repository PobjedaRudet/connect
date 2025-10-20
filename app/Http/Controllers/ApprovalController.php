<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Funkcija;
use App\Models\ProductionOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ApprovalController extends Controller
{
    // Batch send selected orders for approval: creates pending Approval rows for each funkcija in hierarchy
    public function sendForApproval(Request $request)
    {
        $data = $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:production_orders,id',
        ]);

        $hierarchy = Funkcija::orderBy('Redosljed')->pluck('Funkcija')->toArray();

        $user = Auth::user();
        $notifyMap = [];
        DB::transaction(function () use ($data, $hierarchy, $user, &$notifyMap) {
            foreach ($data['order_ids'] as $orderId) {
                $order = ProductionOrder::findOrFail($orderId);
                // Only creator can send
                if ($order->user_id !== $user->id) {
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
                    ->orderByRaw("FIELD(Funkcija, '" . implode("','", $hierarchy) . "')")
                    ->pluck('Funkcija')->toArray();
                $nextF = null;
                foreach ($pending as $pf) {
                    if ($pf !== 'Radnik') { $nextF = $pf; break; }
                }
                $order->update(['Status' => $nextF ? ("na odobrenju kod " . $nextF) : 'na odobrenju']);
                if ($nextF) {
                    $notifyMap[$nextF] = $notifyMap[$nextF] ?? [];
                    $notifyMap[$nextF][] = [$order->OrderNumber, $order->Description];
                }
            }
        });

        // Send notification emails to mapped approvers per funkcija
        foreach ($notifyMap as $funkcija => $orders) {
            $recipients = User::where('funkcija', $funkcija)->pluck('email')->filter()->unique()->values()->all();
            if (!empty($recipients)) {
                $subject = 'Novi nalozi na odobrenje';
                $lines = array_map(fn($o) => "- Nalog: {$o[0]} | {$o[1]}", $orders);
                $body = "Poštovani,\n\nSljedeći nalozi čekaju vaše odobrenje ({$funkcija}):\n" . implode("\n", $lines) . "\n\nHvala.";
                foreach ($recipients as $email) {
                    Mail::raw($body, function ($message) use ($email, $subject) {
                        $message->to($email)->subject($subject);
                    });
                }
            }
        }

        return response()->json(['message' => 'Nalozi poslani na odobrenje.']);
    }

    // List all approvals pending for the current user's funkcija based on role mapping
    public function pending(Request $request)
    {
        $user = Auth::user();
        $funkcija = $this->mapUserToFunkcija($user);
        if (!$funkcija) {
            return response()->json(['data' => []]);
        }

        // Show only orders where all previous in hierarchy are approved and current is pending
        $hierarchy = Funkcija::orderBy('Redosljed')->pluck('Funkcija')->toArray();
        $pos = array_search($funkcija, $hierarchy, true);
        if ($pos === false) return response()->json(['data' => []]);
        $prev = array_slice($hierarchy, 0, $pos);

        $orders = ProductionOrder::whereHas('approvals', function ($q) use ($funkcija) {
            $q->where('Funkcija', $funkcija)->whereNull('Odobreno');
        })
            ->when(count($prev) > 0, function ($q) use ($prev) {
                foreach ($prev as $pf) {
                    $q->whereHas('approvals', function ($qq) use ($pf) {
                        $qq->where('Funkcija', $pf)->where('Odobreno', true);
                    });
                }
            })
            ->with(['approvals', 'partner'])
            ->get()
            ->map(function ($order) use ($funkcija) {
                $current = $order->approvals->first(function ($a) use ($funkcija) {
                    return $a->Funkcija === $funkcija && $a->Odobreno === null;
                });
                return [
                    'id' => $order->id,
                    'OrderNumber' => $order->OrderNumber,
                    'Description' => $order->Description,
                    'partner' => $order->partner?->name,
                    'current_approval_id' => $current?->id,
                ];
            });

        return response()->json(['data' => $orders]);
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

        // Enforce that user is the assigned funkcija or proxy for immediate superior when superior absent
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
            // Allow proxy only if approving for immediate superior
            $hierarchy = Funkcija::orderBy('Redosljed')->pluck('Funkcija')->toArray();
            $uPos = array_search($funkcija, $hierarchy, true);
            $aPos = array_search($approval->Funkcija, $hierarchy, true);
            $isImmediateSuperior = ($aPos !== false && $uPos !== false && $aPos === $uPos + 1);
            if (!($proxy && $isImmediateSuperior)) {
                Log::warning('Approval denied: funkcija mismatch', [
                    'user_id' => $user->id,
                    'user_funkcija' => $funkcija,
                    'approval_id' => $approval->id,
                    'approval_funkcija' => $approval->Funkcija,
                    'proxy' => $proxy,
                    'uPos' => $uPos,
                    'aPos' => $aPos,
                ]);
                return response()->json(['message' => 'Niste ovlašteni za odobrenje ovog koraka.'], 403);
            }
            // If proxy, the superior must be absent
            if ($proxy) {
                $superior = Funkcija::where('Funkcija', $approval->Funkcija)->first();
                if (!$superior || !$superior->is_absent) {
                    return response()->json(['message' => 'Zamjensko potpisivanje dozvoljeno samo kada je nadređeni odsutan.'], 422);
                }
            }
        }

        if ($approval->Odobreno !== null) {
            return response()->json(['message' => 'Ovaj korak je već obrađen.'], 422);
        }

        // Ensure all previous approvals are approved
        $hierarchy = Funkcija::orderBy('Redosljed')->pluck('Funkcija')->toArray();
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
            'Odobreno' => true,
            'DatumOdobravanja' => now(),
            'Komentar' => $comment,
            'signed_by_proxy' => $proxy,
        ])->save();

        // If all approved, update order status
        $allApproved = Approval::where('order_id', $approval->order_id)->whereNull('Odobreno')->doesntExist()
            && Approval::where('order_id', $approval->order_id)->where('Odobreno', false)->doesntExist();
        if ($allApproved) {
            // If Šef Operative approved (final step), also stamp DatumPrijema
            $update = ['Status' => 'odobreno'];
            if ($approval->Funkcija === 'Šef Operative') {
                $update['DatumPrijema'] = $approval->DatumOdobravanja ?? now();
            }
            ProductionOrder::where('id', $approval->order_id)->update($update);
            // If Šef Operative approved (final step), notify all involved + creator
            if ($approval->Funkcija === 'Šef Operative') {
                $order = ProductionOrder::with('creator')->find($approval->order_id);
                if ($order) {
                    // Collect recipients: all users with funkcija in this order's approval flow (except mass 'Radnik'), plus the creator
                    $funkcije = Approval::where('order_id', $order->id)->pluck('Funkcija')->unique()->values()->all();
                    $funkcije = array_values(array_filter($funkcije, fn($f) => $f !== 'Radnik'));
                    $roleRecipients = User::whereIn('funkcija', $funkcije)->pluck('email')->filter();
                    $creatorEmail = optional($order->creator)->email;
                    $recipients = collect($roleRecipients)
                        ->when($creatorEmail, fn($c) => $c->push($creatorEmail))
                        ->unique()
                        ->values()
                        ->all();

                    if (!empty($recipients)) {
                        $subject = 'Nalog odobren';
                        $body = "Poštovani,\n\nNalog je odobren.\n\nBroj: {$order->OrderNumber}\nOpis: {$order->Description}\nStatus: odobreno\nOdobrio: Šef Operative\n\nHvala.";
                        foreach ($recipients as $email) {
                            Mail::raw($body, function ($message) use ($email, $subject) {
                                $message->to($email)->subject($subject);
                            });
                        }
                    }
                }
            }
        } else {
            // Otherwise set to next approver funkcija
            $hierarchy = Funkcija::orderBy('Redosljed')->pluck('Funkcija')->toArray();
            $pending = Approval::where('order_id', $approval->order_id)->whereNull('Odobreno')
                ->orderByRaw("FIELD(Funkcija, '" . implode("','", $hierarchy) . "')")
                ->pluck('Funkcija')->toArray();
            $nextF = null;
            foreach ($pending as $pf) {
                if ($pf !== 'Radnik') { $nextF = $pf; break; }
            }
            if ($nextF) {
                ProductionOrder::where('id', $approval->order_id)->update(['Status' => 'na odobrenju kod ' . $nextF]);
                // Notify next approver funkcija by email (e.g., Direktor Komercijale/Proizvodnje, Šef Operative)
                $order = ProductionOrder::find($approval->order_id);
                if ($order) {
                    $recipients = User::where('funkcija', $nextF)->pluck('email')->filter()->unique()->values()->all();
                    if (!empty($recipients)) {
                        $subject = 'Novi nalog na odobrenje';
                        $body = "Poštovani,\n\nNalog: {$order->OrderNumber} | {$order->Description} čeka vaše odobrenje ({$nextF}).\n\nHvala.";
                        foreach ($recipients as $email) {
                            Mail::raw($body, function ($message) use ($email, $subject) {
                                $message->to($email)->subject($subject);
                            });
                        }
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
        $hierarchy = Funkcija::orderBy('Redosljed')->pluck('Funkcija')->toArray();
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

    // Approve one level above (immediate superior) for a given order
    public function approveOneUp(Request $request, ProductionOrder $order)
    {
        $user = Auth::user();
        $funkcija = $this->mapUserToFunkcija($user);
        $comment = $request->input('Komentar');

        if (!$funkcija) {
            return response()->json(['message' => 'Vaša funkcija nije postavljena ili ne postoji u šifrarniku funkcija.'], 403);
        }

        $hierarchy = Funkcija::orderBy('Redosljed')->pluck('Funkcija')->toArray();
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
        $prev = array_slice($hierarchy, 0, $targetPos);
        foreach ($prev as $pf) {
            $prevApproval = Approval::where('order_id', $order->id)->where('Funkcija', $pf)->first();
            if (!$prevApproval || $prevApproval->Odobreno !== true) {
                return response()->json(['message' => 'Prethodni koraci nisu odobreni.'], 422);
            }
        }

        // Perform approval as proxy (one-up)
        $approval->fill([
            'UserId' => $user->id,
            'Odobreno' => true,
            'DatumOdobravanja' => now(),
            'Komentar' => $comment,
            'signed_by_proxy' => true,
        ])->save();

        // Check if all approved now
        $allApproved = Approval::where('order_id', $order->id)->whereNull('Odobreno')->doesntExist()
            && Approval::where('order_id', $order->id)->where('Odobreno', false)->doesntExist();
        if ($allApproved) {
            $update = ['Status' => 'odobreno'];
            if ($approval->Funkcija === 'Šef Operative') {
                $update['DatumPrijema'] = $approval->DatumOdobravanja ?? now();
            }
            ProductionOrder::where('id', $order->id)->update($update);
            if ($approval->Funkcija === 'Šef Operative') {
                $orderR = ProductionOrder::with('creator')->find($order->id);
                if ($orderR) {
                    $funkcije = Approval::where('order_id', $orderR->id)->pluck('Funkcija')->unique()->values()->all();
                    $funkcije = array_values(array_filter($funkcije, fn($f) => $f !== 'Radnik'));
                    $roleRecipients = User::whereIn('funkcija', $funkcije)->pluck('email')->filter();
                    $creatorEmail = optional($orderR->creator)->email;
                    $recipients = collect($roleRecipients)
                        ->when($creatorEmail, fn($c) => $c->push($creatorEmail))
                        ->unique()
                        ->values()
                        ->all();

                    if (!empty($recipients)) {
                        $subject = 'Nalog odobren';
                        $body = "Poštovani,\n\nNalog je odobren.\n\nBroj: {$orderR->OrderNumber}\nOpis: {$orderR->Description}\nStatus: odobreno\nOdobrio: Šef Operative\n\nHvala.";
                        foreach ($recipients as $email) {
                            Mail::raw($body, function ($message) use ($email, $subject) {
                                $message->to($email)->subject($subject);
                            });
                        }
                    }
                }
            }
        } else {
            // Move status to next pending funkcija
            $pending = Approval::where('order_id', $order->id)->whereNull('Odobreno')
                ->orderByRaw("FIELD(Funkcija, '" . implode("','", $hierarchy) . "')")
                ->pluck('Funkcija')->toArray();
            $nextF = null;
            foreach ($pending as $pf) {
                if ($pf !== 'Radnik') { $nextF = $pf; break; }
            }
            if ($nextF) {
                ProductionOrder::where('id', $order->id)->update(['Status' => 'na odobrenju kod ' . $nextF]);
                $recipients = User::where('funkcija', $nextF)->pluck('email')->filter()->unique()->values()->all();
                if (!empty($recipients)) {
                    $subject = 'Novi nalog na odobrenje';
                    $body = "Poštovani,\n\nNalog: {$order->OrderNumber} | {$order->Description} čeka vaše odobrenje ({$nextF}).\n\nHvala.";
                    foreach ($recipients as $email) {
                        Mail::raw($body, function ($message) use ($email, $subject) {
                            $message->to($email)->subject($subject);
                        });
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
}

