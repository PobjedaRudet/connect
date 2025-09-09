<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->input('query');
        // Pretpostavljamo da postoji model Product
        $products = [];
        if (class_exists('App\\Models\\Product')) {
            $products = \App\Models\Product::where('SkraceniNaziv', 'like', "%$query%")
                ->get();
        }
        return response()->json($products);
    }

    public function getCeOznaka(Request $request)
    {
        $naziv = $request->input('naziv');
        $vrstaProvodnika = $request->input('vrstaProvodnika');
        // Dummy podaci, zamijeni sa pravim upitom
        return response()->json([
            'CEMarkNumber' => 'CE-123',
            'HazardClass' => 'II',
            'UNNumber' => 'UN456',
        ]);
    }

    public function numeredlist(Request $request)
    {

        $query = $request->input('query');
        $numera = $request->input('uom_meter');
        $provodnik = $request->input('provodnik');
        $tip = $request->input('tip');
        $products = [];
        if (class_exists('App\\Models\\Product')) {
            $products = \App\Models\Product::where('SkraceniNaziv', $query)
                ->where('uom_meter', $numera)
                ->where('VrstaProvodnika', $provodnik)
                ->where('Tip', $tip)
                ->get();
        }
        return response()->json($products);
    }
}
