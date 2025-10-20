<?php
namespace App\Http\Controllers;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
    public function numeredlistBihnel(Request $request)
    {
        Log::info('Action taken4');
        $query = $request->input('query');
        $products = Product::where('SkraceniNaziv', '=', "{$query}")
                   ->get();
                   Log::info($products);
        return response()->json($products);
    }

    public function numeredlistBK6(Request $request)
    {
        Log::info('Action taken BK-6');
        $query = $request->input('query');
        // Podrži varijante unosa (BK-6, BK6, BK 6): koristimo LIKE sa normalizacijom
        // Najjednostavnije: ako query sadrži "BK", tražimo proizvode koji imaju BK i 6 uz malo tolerancije
        $normalized = preg_replace('/\s+/', ' ', trim((string) $query));
        $patterns = [
            '%BK-6%',
            '%BK 6%',
            '%BK6%'
        ];
        $products = Product::where(function($q) use ($patterns, $normalized) {
                foreach ($patterns as $p) {
                    $q->orWhere('SkraceniNaziv', 'like', $p);
                }
                // Također pokušaj i tačno poklapanje kao fallback
                $q->orWhere('SkraceniNaziv', '=', $normalized);
            })
            ->get();
        Log::info($products);
        return response()->json($products);
    }

    public function numeredlistBK8(Request $request)
    {
        Log::info('Action taken BK-8');
        $query = $request->input('query');
        $variant = strtoupper((string) $request->input('variant', ''));
        $normalized = preg_replace('/\s+/', ' ', trim((string) $query));
        $patterns = [
            '%BK-8%',
            '%BK 8%',
            '%BK8%'
        ];
        $builder = Product::query();
        // Osnovni uslov: BK-8 proizvodi
        $builder->where(function($q) use ($patterns, $normalized) {
            $q->where('SkraceniNaziv', '=', $normalized);
            foreach ($patterns as $p) {
                $q->orWhere('SkraceniNaziv', 'like', $p);
            }
        });
        // Dodatni uslov: varijanta (LP/MS) – mora biti zadovoljena uz BK-8
        if (in_array($variant, ['LP', 'MS'])) {
            $builder->where(function($q) use ($variant) {
                $like = "%{$variant}%";
                $q->where('SkraceniNaziv', 'like', $like)
                  ->orWhere('SkraceniNaziv', 'like', "% {$variant}%")
                  ->orWhere('SkraceniNaziv', 'like', "%-{$variant}%")
                  ->orWhere('NumeraProizvoda', 'like', $like);
            });
        }
        $products = $builder->get();
        Log::info($products);
        return response()->json($products);
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
