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
        $query = $request->input('query');
        $metraza = $request->input('metraza');

        Log::info('numeredlistBihnel called', [
            'query' => $query,
            'metraza' => $metraza,
            'metraza_type' => gettype($metraza)
        ]);

        // Koristimo LIKE sa UPPER() za case-insensitive pretragu kao kod BK-8
        $normalizedQuery = strtoupper(trim((string) $query));
        $productsQuery = Product::whereRaw('UPPER(SkraceniNaziv) LIKE ?', ["%{$normalizedQuery}%"]);

        // Ako je metraža proslijeđena, dodaj je u filter
        if ($metraza !== null && $metraza !== '') {
            $normalizedMetraza = str_replace(',', '.', (string) $metraza);
            $numericMetraza = is_numeric($normalizedMetraza) ? $normalizedMetraza : $metraza;
            $productsQuery->where(function ($q) use ($metraza, $normalizedMetraza, $numericMetraza) {
                $q->where('UoM_meter', '=', $metraza)
                    ->orWhere('UoM_meter', '=', $normalizedMetraza)
                    ->orWhereRaw('REPLACE(UoM_meter, \',\', \'.\') = ?', [$normalizedMetraza])
                    ->orWhereRaw('CAST(REPLACE(UoM_meter, \',\', \'.\') AS DECIMAL(18,6)) = ?', [floatval($numericMetraza)]);
            });
            Log::info('Added UoM_meter flexible filter', [
                'original' => $metraza,
                'normalized' => $normalizedMetraza,
                'numeric' => $numericMetraza
            ]);
        }

        $products = $productsQuery->get();
        Log::info('numeredlistBihnel results', [
            'count' => $products->count(),
            'sql' => $productsQuery->toSql(),
            'bindings' => $productsQuery->getBindings()
        ]);

        return response()->json($products);
    }

    public function numeredlistPSED(Request $request)
    {
        $query = $request->input('query');
        $metraza = $request->input('metraza');
        $tip = $request->input('tip');

        Log::info('numeredlistPSED called', [
            'query' => $query,
            'metraza' => $metraza,
            'metraza_type' => gettype($metraza),
            'tip' => $tip
        ]);

        // Koristimo LIKE sa UPPER() za case-insensitive pretragu
        $normalizedQuery = strtoupper(trim((string) $query));
        $productsQuery = Product::whereRaw('UPPER(SkraceniNaziv) LIKE ?', ["%{$normalizedQuery}%"]);

        // Ako je metraža proslijeđena, dodaj je u filter
        if ($metraza !== null && $metraza !== '') {
            $normalizedMetraza = str_replace(',', '.', (string) $metraza);
            $numericMetraza = is_numeric($normalizedMetraza) ? $normalizedMetraza : $metraza;
            $productsQuery->where(function ($q) use ($metraza, $normalizedMetraza, $numericMetraza) {
                // Direktno poređenje sa originalnom vrednošću (za slučaj da je u bazi sa zarezom)
                $q->where('UoM_meter', '=', $metraza)
                    // Poređenje sa normalizovanom vrednošću (za slučaj da je u bazi sa tačkom)
                    ->orWhere('UoM_meter', '=', $normalizedMetraza)
                    // Konvertuj vrednosti iz baze (zameni zarez sa tačkom) i uporedi
                    ->orWhereRaw('REPLACE(UoM_meter, \',\', \'.\') = ?', [$normalizedMetraza])
                    // CAST na DECIMAL za numeričko poređenje
                    ->orWhereRaw('CAST(REPLACE(UoM_meter, \',\', \'.\') AS DECIMAL(18,6)) = ?', [floatval($numericMetraza)]);
            });
            Log::info('Added UoM_meter flexible filter', [
                'original' => $metraza,
                'normalized' => $normalizedMetraza,
                'numeric' => $numericMetraza
            ]);
        }

        // Ako je Tip proslijeđen, filtriraj po Naziv koloni (npr. "/A" ili "/B" na kraju naziva)
        if ($tip !== null && $tip !== '' && $tip !== '-') {
            $productsQuery->whereRaw('UPPER(Naziv) LIKE ?', ["%/{$tip}%"]);
            Log::info('Added Tip filter', ['tip' => $tip]);
        }

        $products = $productsQuery->get();
        Log::info('numeredlistPSED results', [
            'count' => $products->count(),
            'sql' => $productsQuery->toSql(),
            'bindings' => $productsQuery->getBindings()
        ]);

        return response()->json($products);
    }

    public function numeredlistMSED(Request $request)
    {
        $query = $request->input('query');
        $metraza = $request->input('metraza');
        $vrstaProvodnika = $request->input('vrstaProvodnika');
        $tip = $request->input('tip');

        Log::info('numeredlistMSED called', [
            'query' => $query,
            'metraza' => $metraza,
            'vrstaProvodnika' => $vrstaProvodnika,
            'tip' => $tip
        ]);

        // Koristimo LIKE sa UPPER() za case-insensitive pretragu
        // Ali spriječavamo matching ako prije MSED dolazi slovo (npr. MMSED)
        $normalizedQuery = strtoupper(trim((string) $query));

        // Prvo dobijamo sve proizvode koji sadrže MSED
        $allProducts = Product::whereRaw('UPPER(SkraceniNaziv) LIKE ?', ["%{$normalizedQuery}%"])->get();

        // Filtriramo da isključimo one gdje prije MSED dolazi slovo (npr. MMSED)
        $filteredProducts = $allProducts->filter(function ($product) use ($normalizedQuery) {
            $skraceniNaziv = strtoupper($product->SkraceniNaziv);
            $pos = strpos($skraceniNaziv, $normalizedQuery);

            if ($pos === false) return false;

            // Provjeri da li prije MSED dolazi slovo
            if ($pos > 0) {
                $charBefore = $skraceniNaziv[$pos - 1];
                // Ako je prije slovo (A-Z), isključi proizvod
                if (ctype_alpha($charBefore)) {
                    return false;
                }
            }
            return true;
        });

        // Primjeni dodatne filtere na filtriranoj kolekciji
        if ($metraza !== null && $metraza !== '') {
            $normalizedMetraza = str_replace(',', '.', (string) $metraza);
            $filteredProducts = $filteredProducts->filter(function ($product) use ($metraza, $normalizedMetraza) {
                $val = (string) $product->UoM_meter;
                $valNorm = str_replace(',', '.', $val);
                return $val === (string) $metraza || $val === $normalizedMetraza || $valNorm === $normalizedMetraza;
            });
            Log::info('Added UoM_meter flexible filter', [
                'original' => $metraza,
                'normalized' => $normalizedMetraza
            ]);
        }

        if ($vrstaProvodnika !== null && $vrstaProvodnika !== '' && $vrstaProvodnika !== '-') {
            $filteredProducts = $filteredProducts->where('VrstaProvodnika', '=', $vrstaProvodnika);
            Log::info('Added VrstaProvodnika filter', ['vrstaProvodnika' => $vrstaProvodnika]);
        }

        if ($tip !== null && $tip !== '' && $tip !== '-') {
            $filteredProducts = $filteredProducts->filter(function ($product) use ($tip) {
                return stripos($product->Naziv, "/{$tip}") !== false;
            });
            Log::info('Added Tip filter', ['tip' => $tip]);
        }

        $products = $filteredProducts->values();
        Log::info('numeredlistMSED results', [
            'count' => $products->count()
        ]);

        return response()->json($products);
    }

    public function numeredlistBK6(Request $request)
    {
        Log::info('Action taken BK-6');
        $query = $request->input('query');
        // Podrži varijante unosa (BK-6, BK6, BK 6): koristimo LIKE sa normalizacijom
        // Najjednostavnije: ako query sadrži "BK", tražimo proizvode koji imaju BK i 6 uz malo tolerancije
        $normalized = preg_replace('/\s+/', ' ', trim((string) $query));
        // Detektuj porodicu (BK ili DK) da ne miješamo rezultate
        $family = null;
        if (preg_match('/\b([BD]K)[-\s]*6\b/i', $normalized, $m)) {
            $family = strtoupper($m[1]);
        } elseif (stripos($normalized, 'DK') !== false) {
            $family = 'DK';
        } elseif (stripos($normalized, 'BK') !== false) {
            $family = 'BK';
        }
        $patterns = ($family === 'DK')
            ? ['%DK-6%', '%DK 6%', '%DK6%']
            : ['%BK-6%', '%BK 6%', '%BK6%'];
        $products = Product::where(function ($q) use ($patterns, $normalized) {
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
        $normalizedUpper = strtoupper($normalized);
        // Detektuj porodicu (BK ili DK) da ne miješamo rezultate
        $family = null;
        if (preg_match('/\b([BD]K)[-\s]*8\b/i', $normalized, $m)) {
            $family = strtoupper($m[1]);
        } elseif (stripos($normalized, 'DK') !== false) {
            $family = 'DK';
        } elseif (stripos($normalized, 'BK') !== false) {
            $family = 'BK';
        }
        // First try (flexible, case-insensitive): collapse all non-alphanumerics to wildcard and compare in UPPER()
        // Example: "bk-8 lp" -> needle "BK%8%LP", matches both "BK-8 LP" and "BK 8 LP" variants
        $needleFlexible = preg_replace('/[^A-Z0-9]+/i', '%', $normalizedUpper);
        $primaryResults = Product::query()
            ->whereRaw('UPPER(SkraceniNaziv) LIKE ?', ['%' . $needleFlexible . '%'])
            ->get();
        if ($primaryResults->isNotEmpty()) {
            return response()->json($primaryResults);
        }

        // Fallback: start with family constraint to avoid cross-family bleed (BK vs DK)
        $builder = Product::query();
        if ($family === 'BK') {
            $builder->where(function ($q) {
                $q->whereRaw('UPPER(SkraceniNaziv) LIKE ?', ['%BK-8%'])
                    ->orWhereRaw('UPPER(SkraceniNaziv) LIKE ?', ['%BK 8%'])
                    ->orWhereRaw('UPPER(SkraceniNaziv) LIKE ?', ['%BK8%']);
            });
        } elseif ($family === 'DK') {
            $builder->where(function ($q) {
                $q->whereRaw('UPPER(SkraceniNaziv) LIKE ?', ['%DK-8%'])
                    ->orWhereRaw('UPPER(SkraceniNaziv) LIKE ?', ['%DK 8%'])
                    ->orWhereRaw('UPPER(SkraceniNaziv) LIKE ?', ['%DK8%']);
            });
        }
        // Variant detection: prioritize explicit param, else parse from query text
        $variantToken = in_array($variant, ['LP', 'MS'], true)
            ? $variant
            : (preg_match('/\b(LP|MS)\b/i', $normalized, $mv) ? strtoupper($mv[1]) : null);
        // For BK family, if LP/MS variant was provided or detected, require it in either SkraceniNaziv or NumeraProizvoda
        if ($family === 'BK' && $variantToken) {
            $builder->where(function ($q) use ($variantToken) {
                $like = "%{$variantToken}%";
                $q->whereRaw('UPPER(SkraceniNaziv) LIKE ?', [strtoupper($like)])
                    ->orWhereRaw('UPPER(SkraceniNaziv) LIKE ?', [strtoupper(" %{$variantToken}%")])
                    ->orWhereRaw('UPPER(SkraceniNaziv) LIKE ?', [strtoupper("%-{$variantToken}%")])
                    ->orWhereRaw('CAST(NumeraProizvoda AS CHAR) LIKE ?', [$like]);
            });
        }
        $products = $builder->get();
        // Optional: if no results, relax to only family filter (ignore variant)
        if ($products->isEmpty() && $family !== null) {
            $relaxed = Product::query();
            if ($family === 'BK') {
                $relaxed->where(function ($q) {
                    $q->whereRaw('UPPER(SkraceniNaziv) LIKE ?', ['%BK-8%'])
                        ->orWhereRaw('UPPER(SkraceniNaziv) LIKE ?', ['%BK 8%'])
                        ->orWhereRaw('UPPER(SkraceniNaziv) LIKE ?', ['%BK8%']);
                });
            } else {
                $relaxed->where(function ($q) {
                    $q->whereRaw('UPPER(SkraceniNaziv) LIKE ?', ['%DK-8%'])
                        ->orWhereRaw('UPPER(SkraceniNaziv) LIKE ?', ['%DK 8%'])
                        ->orWhereRaw('UPPER(SkraceniNaziv) LIKE ?', ['%DK8%']);
                });
            }
            $products = $relaxed->get();
        }
        // Ultimate safety net: if still empty and user clearly asked for BK-8 LP/MS, build a very permissive filter
        if ($products->isEmpty() && $family === 'BK' && $variantToken) {
            $products = Product::query()
                ->whereRaw('UPPER(SkraceniNaziv) LIKE ?', ['%BK%'])
                ->whereRaw('UPPER(SkraceniNaziv) LIKE ?', ['%8%'])
                ->whereRaw('UPPER(SkraceniNaziv) LIKE ?', ['%' . $variantToken . '%'])
                ->get();
        }
        Log::info($products);
        return response()->json($products);
    }

    public function numeredlist(Request $request)
    {
        $query = (string) $request->input('query', '');
        $uom = $request->input('uom_meter'); // može imati 2,5 ili 2.5
        $provodnik = $request->input('provodnik');
        $tip = $request->input('tip');

        $normalizedQuery = strtoupper(trim($query));
        $builder = Product::query()
            ->whereRaw('UPPER(SkraceniNaziv) LIKE ?', ["%{$normalizedQuery}%"]);

        // Fleksibilan filter za UoM_meter (podržava 2,5 i 2.5 i numeričko poređenje)
        if ($uom !== null && $uom !== '') {
            $original = (string) $uom;
            $dotForm = str_replace(',', '.', $original);
            $commaForm = str_replace('.', ',', $original); // ako je poslan 2.5, dodaj i 2,5
            $numeric = is_numeric($dotForm) ? (float) $dotForm : null;
            $builder->where(function ($q) use ($original, $dotForm, $commaForm, $numeric) {
                $q->where('UoM_meter', '=', $original)
                    ->orWhere('UoM_meter', '=', $dotForm)
                    ->orWhere('UoM_meter', '=', $commaForm)
                    ->orWhereRaw('REPLACE(UoM_meter, ",", ".") = ?', [$dotForm]);
                if ($numeric !== null) {
                    // Ako je kolona numerička ili se može kastovati
                    $q->orWhereRaw('CAST(REPLACE(UoM_meter, ",", ".") AS DECIMAL(18,6)) = ?', [$numeric]);
                }
            });
            Log::info('numeredlist UoM_meter flexible filter', [
                'original' => $original,
                'dotForm' => $dotForm,
                'commaForm' => $commaForm,
                'numeric' => $numeric,
            ]);
        }

        // Vrsta provodnika (tačno poklapanje) ako je proslijeđena i nije '-'
        if ($provodnik !== null && $provodnik !== '' && $provodnik !== '-') {
            $builder->where('VrstaProvodnika', '=', $provodnik);
            Log::info('numeredlist provodnik filter', ['provodnik' => $provodnik]);
        }

        // Tip: tražimo unutar Naziv pattern "/A" ili "/B" itd.
        if ($tip !== null && $tip !== '' && $tip !== '-') {
            $tipUpper = strtoupper($tip);
            $builder->whereRaw('UPPER(Naziv) LIKE ?', ["%/{$tipUpper}%"]);
            Log::info('numeredlist tip filter', ['tip' => $tipUpper]);
        }

        $products = $builder->get();

        // Sort stabilizacija: prvo numerički UoM_meter ako se može parsirati, zatim NumeraProizvoda
        $sorted = $products->sort(function ($a, $b) {
            $am = (float) str_replace(',', '.', (string) $a->UoM_meter);
            $bm = (float) str_replace(',', '.', (string) $b->UoM_meter);
            if ($am == $bm) {
                $an = (int) ($a->NumeraProizvoda ?? 0);
                $bn = (int) ($b->NumeraProizvoda ?? 0);
                return $an <=> $bn;
            }
            return $am <=> $bm;
        })->values();

        Log::info('numeredlist results', [
            'count' => $sorted->count(),
            'query' => $query,
            'uom_raw' => $uom,
            'provodnik' => $provodnik,
            'tip' => $tip,
        ]);

        return response()->json($sorted);
    }
}
