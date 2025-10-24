<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PartnerController extends Controller
{
    protected function ensureRadnikAccess()
    {
        $user = Auth::user();
        if (!$user) abort(401);
        // Dozvoli bar Radnik i više (ostale funkcije takođe mogu unositi kupce)
        // Ako želite restriktivnije, promijenite uslov u ($user->funkcija ?? null) === 'Radnik'
        return true;
    }

    public function index(Request $request)
    {
        $this->ensureRadnikAccess();
        $q = trim((string)$request->input('q', ''));
        $query = Partner::query();
        if ($q !== '') {
            $query->where(function($w) use ($q) {
                $w->where('name','like',"%$q%")
                  ->orWhere('email','like',"%$q%")
                  ->orWhere('phone','like',"%$q%")
                  ->orWhere('oznaka','like',"%$q%")
                  ->orWhere('city','like',"%$q%")
                  ->orWhere('country','like',"%$q%");
            });
        }
        // Samo kupci po defaultu
        $query->where(function($w){ $w->whereNull('type')->orWhere('type','kupac'); });
        $partners = $query->orderBy('name')->paginate(15)->withQueryString();
        return Inertia::render('Partneri/ListaKupaca', [
            'q' => $q,
            'partners' => $partners,
        ]);
    }

    public function create()
    {
        $this->ensureRadnikAccess();
        return Inertia::render('Partneri/NoviKupac');
    }

    public function store(Request $request)
    {
        $this->ensureRadnikAccess();
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['nullable','email','max:255'],
            'address' => ['nullable','string','max:255'],
            'phone' => ['nullable','string','max:100'],
            'type' => ['nullable','string','max:50'],
            'oznaka' => ['nullable','string','max:50'],
            'city' => ['nullable','string','max:120'],
            'country' => ['nullable','string','max:120'],
        ]);

        // Podrazumijevano označi kao kupac ako nije dato
        if (!isset($data['type']) || $data['type'] === null || $data['type'] === '') {
            $data['type'] = 'kupac';
        }

        $partner = Partner::create($data);

        return response()->json([
            'ok' => true,
            'id' => $partner->id,
            'partner' => $partner,
            'message' => 'Kupac je uspješno unesen.',
        ], 201);
    }

    public function edit(Partner $partner)
    {
        $this->ensureRadnikAccess();
        return Inertia::render('Partneri/UrediKupca', [ 'partner' => $partner ]);
    }

    public function update(Request $request, Partner $partner)
    {
        $this->ensureRadnikAccess();
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['nullable','email','max:255'],
            'address' => ['nullable','string','max:255'],
            'phone' => ['nullable','string','max:100'],
            'type' => ['nullable','string','max:50'],
            'oznaka' => ['nullable','string','max:50'],
            'city' => ['nullable','string','max:120'],
            'country' => ['nullable','string','max:120'],
        ]);
        if (!isset($data['type']) || $data['type'] === null || $data['type'] === '') {
            $data['type'] = 'kupac';
        }
        $partner->update($data);
        return redirect()->route('partners.index')->with('success', 'Kupac je ažuriran.');
    }
}
