<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AIController extends Controller
{
    protected AIService $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Generate content based on topic and options
     */
    public function generateContent(Request $request): JsonResponse
    {
        $request->validate([
            'topic' => 'required|string|max:500',
            'tone' => 'in:professional,casual,friendly,formal,technical',
            'length' => 'in:short,medium,long',
            'keywords' => 'array',
            'keywords.*' => 'string',
        ]);

        try {
            $result = $this->aiService->generateContent(
                $request->topic,
                $request->tone ?? 'professional',
                $request->length ?? 'medium',
                $request->keywords ?? []
            );

            return response()->json([
                'success' => true,
                'content' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate summary for content
     */
    public function generateSummary(Request $request): JsonResponse
    {
        $request->validate([
            'content' => 'required|string',
            'max_sentences' => 'integer|min:1|max:10',
        ]);

        try {
            $summary = $this->aiService->generateSummary(
                $request->content,
                $request->max_sentences ?? 3
            );

            return response()->json([
                'success' => true,
                'summary' => $summary,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate SEO keywords from title and content
     */
    public function generateKeywords(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'count' => 'integer|min:5|max:20',
        ]);

        try {
            $keywords = $this->aiService->generateKeywords(
                $request->title,
                $request->content,
                $request->count ?? 10
            );

            return response()->json([
                'success' => true,
                'keywords' => $keywords,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate meta description
     */
    public function generateMetaDescription(Request $request): JsonResponse
    {
        $request->validate([
            'content' => 'required|string',
            'max_length' => 'integer|min:50|max:300',
        ]);

        try {
            $description = $this->aiService->generateMetaDescription(
                $request->content,
                $request->max_length ?? 160
            );

            return response()->json([
                'success' => true,
                'description' => $description,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Suggest related posts based on title and content
     */
    public function suggestRelated(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'count' => 'integer|min:1|max:10',
        ]);

        try {
            $suggestions = $this->aiService->suggestRelatedPosts(
                $request->title,
                $request->content,
                $request->count ?? 5
            );

            return response()->json([
                'success' => true,
                'suggestions' => $suggestions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Proofread and improve content
     */
    public function proofread(Request $request): JsonResponse
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        try {
            $improved = $this->aiService->proofreadContent($request->content);

            return response()->json([
                'success' => true,
                'improved_content' => $improved,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate content ideas for a topic
     */
    public function generateIdeas(Request $request): JsonResponse
    {
        $request->validate([
            'topic' => 'required|string|max:200',
            'count' => 'integer|min:1|max:20',
        ]);

        try {
            $ideas = $this->aiService->generateContentIdeas(
                $request->topic,
                $request->count ?? 10
            );

            return response()->json([
                'success' => true,
                'ideas' => $ideas,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
