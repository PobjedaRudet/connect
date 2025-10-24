<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductsController extends Controller
{

    public function index(Request $request)
    {
        $q = trim((string)$request->get('q', ''));
        $query = Product::query();
        if ($q !== '') {
            $query->where(function($sub) use ($q){
                $sub->where('Naziv', 'like', "%$q%");
                $sub->orWhere('SkraceniNaziv', 'like', "%$q%");
                $sub->orWhere('Code', 'like', "%$q%");
                $sub->orWhere('Tip', 'like', "%$q%");
                $sub->orWhere('JedinicaMjere', 'like', "%$q%");
            });
        }
        $products = $query->orderBy('Naziv')->paginate(10)->withQueryString();

        return Inertia::render('Proizvodi/ListaProizvoda', [
            'q' => $q,
            'products' => $products,
        ]);
    }

    public function create()
    {
        return Inertia::render('Proizvodi/NoviProizvod');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'Naziv' => 'required|string|max:255',
            'Tip' => 'nullable|string|max:255',
            'SkraceniNaziv' => 'nullable|string|max:255',
            'JedinicaMjere' => 'nullable|string|max:255',
            'Code' => 'nullable|string|max:255',
            'UoM_meter' => 'nullable|string|max:255',
            'UsporenjeMs' => 'nullable|integer',
            'UNNumber' => 'nullable|string|max:16',
            'HazardClass' => 'nullable|string|max:255',
            'CEMarkNumber' => 'nullable|string|max:255',
            'NumeraProizvoda' => 'nullable|integer',
            'VrstaProvodnika' => 'nullable|string|max:255',
        ]);

        $product = Product::create($validated);

        return redirect()->route('products.edit', $product)->with('success', 'Proizvod kreiran.');
    }

    public function edit(Product $product)
    {
        return Inertia::render('Proizvodi/UrediProizvod', [
            'product' => $product,
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'Naziv' => 'required|string|max:255',
            'Tip' => 'nullable|string|max:255',
            'SkraceniNaziv' => 'nullable|string|max:255',
            'JedinicaMjere' => 'nullable|string|max:255',
            'Code' => 'nullable|string|max:255',
            'UoM_meter' => 'nullable|string|max:255',
            'UsporenjeMs' => 'nullable|integer',
            'UNNumber' => 'nullable|string|max:16',
            'HazardClass' => 'nullable|string|max:255',
            'CEMarkNumber' => 'nullable|string|max:255',
            'NumeraProizvoda' => 'nullable|integer',
            'VrstaProvodnika' => 'nullable|string|max:255',
        ]);

        $product->update($validated);

        return back()->with('success', 'Proizvod ažuriran.');
    }
}
