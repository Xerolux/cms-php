<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RobotsTxt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RobotsTxtController extends Controller
{
    /**
     * Get robots.txt content
     */
    public function index()
    {
        $robots = RobotsTxt::firstOrFail();
        return response()->json($robots);
    }

    /**
     * Update robots.txt content
     */
    public function update(Request $request)
    {
        $robots = RobotsTxt::firstOrFail();

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $robots->update([
            'content' => $validated['content'],
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Robots.txt updated successfully',
            'robots' => $robots,
        ]);
    }

    /**
     * Validate robots.txt content without saving
     */
    public function validateContent(Request $request)
    {
        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $robots = new RobotsTxt(['content' => $validated['content']]);
        $errors = $robots->validateContent();

        return response()->json([
            'valid' => empty($errors),
            'errors' => $errors,
        ]);
    }

    /**
     * Reset to default robots.txt
     */
    public function reset()
    {
        $robots = RobotsTxt::firstOrFail();
        $defaultContent = RobotsTxt::generateDefault();

        $robots->update([
            'content' => $defaultContent,
            'updated_by' => Auth::id(),
            'last_generated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Robots.txt reset to default',
            'robots' => $robots,
        ]);
    }

    /**
     * Get robots.txt as plain text (for public endpoint)
     */
    public function show()
    {
        $robots = RobotsTxt::firstOrFail();
        return response($robots->content)
            ->header('Content-Type', 'text/plain');
    }
}
