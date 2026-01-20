<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use Illuminate\Http\Request;

class AdController extends Controller
{
    public function index()
    {
        $ads = Advertisement::orderBy('created_at', 'desc')->get();
        return response()->json($ads);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'zone' => 'required|in:header,sidebar,footer,in-content',
            'ad_type' => 'required|in:html,image,script',
            'content' => 'nullable|string',
            'image_url' => 'nullable|url',
            'link_url' => 'nullable|url',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'is_active' => 'boolean',
        ]);

        $ad = Advertisement::create($validated);

        return response()->json($ad, 201);
    }

    public function show($id)
    {
        $ad = Advertisement::findOrFail($id);
        return response()->json($ad);
    }

    public function update(Request $request, $id)
    {
        $ad = Advertisement::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'zone' => 'sometimes|required|in:header,sidebar,footer,in-content',
            'ad_type' => 'sometimes|required|in:html,image,script',
            'content' => 'nullable|string',
            'image_url' => 'nullable|url',
            'link_url' => 'nullable|url',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'is_active' => 'boolean',
        ]);

        $ad->update($validated);

        return response()->json($ad);
    }

    public function destroy($id)
    {
        $ad = Advertisement::findOrFail($id);
        $ad->delete();

        return response()->json(null, 204);
    }
}
