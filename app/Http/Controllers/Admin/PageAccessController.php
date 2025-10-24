<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Funkcija;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class PageAccessController extends Controller
{
    public function index()
    {
        $pages = Page::with('funkcije')->orderBy('route_name')->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'route_name' => $p->route_name,
                    'description' => $p->description,
                    'allowed_funkcije' => $p->funkcije->pluck('Funkcija')->values()->all(),
                ];
            });
        $funkcije = Funkcija::orderBy('Redosljed')->pluck('Funkcija')->values();

        // Build a list of available named routes for selection (exclude auth/api/debug/etc.)
        $ignorePrefixes = [
            'login', 'logout', 'register', 'password.', 'verification.', 'sanctum.', 'ignition', '_ignition', 'telescope.', 'horizon.', 'debugbar.', 'api.', 'livewire', 'livewire.*', 'spark.', 'cashier.'
        ];
        $ignoreExact = [
            'approvals.email.direct', 'approvals.email.open'
        ];
        $routes = collect(Route::getRoutes())
            ->map(function ($r) {
                return [
                    'name' => $r->getName(),
                    'uri' => $r->uri(),
                    'methods' => $r->methods(),
                    'middleware' => method_exists($r, 'gatherMiddleware') ? $r->gatherMiddleware() : ($r->middleware() ?? []),
                ];
            })
            ->filter(function ($r) use ($ignorePrefixes, $ignoreExact) {
                if (!$r['name']) return false;
                if (str_starts_with($r['name'], 'generated::')) return false;
                if (in_array($r['name'], $ignoreExact, true)) return false;
                foreach ($ignorePrefixes as $p) {
                    // allow dot-notation match or prefix match
                    if ($p && (str_starts_with($r['name'], $p))) {
                        return false;
                    }
                }
                return true;
            })
            ->unique('name')
            ->sortBy('name')
            ->values()
            ->all();

        return Inertia::render('Admin/PageAccess', [
            'pages' => $pages,
            'funkcije' => $funkcije,
            'availableRoutes' => $routes,
        ]);
    }

    public function storePage(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'route_name' => 'required|string|max:150',
            'description' => 'nullable|string|max:255',
        ]);

        $page = Page::updateOrCreate(['route_name' => $data['route_name']], [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return back()->with('status', 'Stranica je sačuvana.');
    }

    public function assign(Request $request)
    {
        $data = $request->validate([
            'page_id' => 'required|integer|exists:pages,id',
            'funkcije' => 'array',
            'funkcije.*' => 'string|exists:funkcije,Funkcija',
        ]);

        $page = Page::findOrFail($data['page_id']);
        $funkcije = $data['funkcije'] ?? [];

        // Sync pivot: funkcija_page
        // Need to build an array mapping of funkcija => ['funkcija' => value] for belongsToMany with non-id key
        $syncData = [];
        foreach ($funkcije as $f) {
            $syncData[$f] = ['funkcija' => $f];
        }
        // Since the foreign key on related is string, we can use sync with plain array of keys
        $page->funkcije()->sync($funkcije);

        return back()->with('status', 'Pristup ažuriran.');
    }
}
