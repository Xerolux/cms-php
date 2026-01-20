<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PageController extends Controller
{
    protected SeoService $seoService;

    public function __construct(SeoService $seoService)
    {
        $this->seoService = $seoService;
    }

    public function index(Request $request)
    {
        $query = Page::with(['creator', 'updater']);

        // Filter
        if ($request->has('is_visible')) {
            $query->where('is_visible', $request->boolean('is_visible'));
        }

        if ($request->has('is_in_menu')) {
            $query->where('is_in_menu', $request->boolean('is_in_menu'));
        }

        $pages = $query->orderBy('menu_order')->orderBy('title')->get();

        return response()->json($pages);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'content' => 'required|string',
            'template' => 'in:default,full-width,landing',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'is_visible' => 'boolean',
            'is_in_menu' => 'boolean',
            'menu_order' => 'integer|min:0|max:999',
        ]);

        $page = Page::create([
            ...$validated,
            'slug' => $validated['slug'] ?: str_slug($validated['title']),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return response()->json($page, 201);
    }

    public function show($slug)
    {
        $page = Page::where('slug', $slug)
            ->visible()
            ->with(['creator', 'updater'])
            ->firstOrFail();

        return response()->json($page);
    }

    public function showById($id)
    {
        $page = Page::with(['creator', 'updater'])->findOrFail($id);
        return response()->json($page);
    }

    public function update(Request $request, $id)
    {
        $page = Page::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'content' => 'sometimes|required|string',
            'template' => 'in:default,full-width,landing',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'is_visible' => 'boolean',
            'is_in_menu' => 'boolean',
            'menu_order' => 'integer|min:0|max:999',
        ]);

        if (isset($validated['title']) && !isset($validated['slug'])) {
            $validated['slug'] = str_slug($validated['title']);
        }

        $page->update([
            ...$validated,
            'updated_by' => Auth::id(),
        ]);

        return response()->json($page);
    }

    public function destroy($id)
    {
        $page = Page::findOrFail($id);
        $page->delete();

        return response()->json(null, 204);
    }

    /**
     * Pages für Menü (öffentlich)
     */
    public function menu()
    {
        $pages = Page::inMenu()->get(['id', 'title', 'slug', 'menu_order']);

        return response()->json($pages);
    }
}
