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
     * Generate full article content
     */
    public function generateFullArticle(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'sometimes|string|max:200',
            'topic' => 'required_without:title|string|max:500',
            'tone' => 'in:professional,casual,friendly,formal,technical,conversational',
            'target_audience' => 'sometimes|string|max:200',
            'keywords' => 'array',
            'keywords.*' => 'string|max:50',
            'word_count' => 'integer|min:100|max:5000',
            'outline' => 'sometimes|string',
            'research_points' => 'array',
            'research_points.*' => 'string',
            'temperature' => 'numeric|min:0|max:2',
        ]);

        try {
            $result = $this->aiService->generateFullArticle([
                'title' => $request->title,
                'topic' => $request->topic,
                'tone' => $request->tone ?? 'professional',
                'target_audience' => $request->target_audience,
                'keywords' => $request->keywords ?? [],
                'word_count' => $request->word_count ?? 1500,
                'outline' => $request->outline,
                'research_points' => $request->research_points ?? [],
                'temperature' => $request->temperature ?? 0.7,
            ]);

            return response()->json([
                'success' => $result['success'] ?? false,
                'content' => $result['text'] ?? '',
                'usage' => $result['usage'] ?? [],
                'error' => $result['error'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
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
                $request->all()
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
     * Optimize content for SEO
     */
    public function optimizeSEO(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string|min:100',
        ]);

        try {
            $result = $this->aiService->optimizeSEO(
                $request->title,
                $request->content
            );

            return response()->json([
                'success' => $result['success'] ?? false,
                'analysis' => $result['text'] ?? '',
                'usage' => $result['usage'] ?? [],
                'error' => $result['error'] ?? null,
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
            'content' => 'required|string|min:50',
            'max_length' => 'integer|min:50|max:500',
        ]);

        try {
            $summary = $this->aiService->generateSummary(
                $request->content,
                $request->max_length ?? 150
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
     * Generate tags based on content
     */
    public function generateTags(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string|min:100',
            'count' => 'integer|min:3|max:30',
        ]);

        try {
            $tags = $this->aiService->generateTags(
                $request->title,
                $request->content,
                $request->count ?? 10
            );

            return response()->json([
                'success' => true,
                'tags' => $tags,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check for plagiarism
     */
    public function checkPlagiarism(Request $request): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|min:100',
            'existing_content' => 'array',
            'existing_content.*.title' => 'string',
            'existing_content.*.content' => 'string',
        ]);

        try {
            $result = $this->aiService->checkPlagiarism(
                $request->content,
                $request->existing_content ?? []
            );

            return response()->json([
                'success' => $result['success'] ?? false,
                'analysis' => $result['text'] ?? '',
                'error' => $result['error'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Analyze sentiment of content
     */
    public function analyzeSentiment(Request $request): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|min:50',
        ]);

        try {
            $sentiment = $this->aiService->analyzeSentiment($request->content);

            return response()->json([
                'success' => true,
                'sentiment' => $sentiment,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Suggest headlines for content
     */
    public function suggestHeadlines(Request $request): JsonResponse
    {
        $request->validate([
            'topic' => 'required|string|max:200',
            'content' => 'sometimes|string',
            'count' => 'integer|min:5|max:20',
        ]);

        try {
            $headlines = $this->aiService->suggestHeadlines(
                $request->topic,
                $request->content ?? '',
                $request->count ?? 10
            );

            return response()->json([
                'success' => true,
                'headlines' => $headlines,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Translate content using DeepL
     */
    public function translateContent(Request $request): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|min:10',
            'target_language' => 'required|string|size:2',
            'source_language' => 'sometimes|string|size:2',
        ]);

        try {
            $result = $this->aiService->translateContent(
                $request->content,
                $request->target_language,
                $request->source_language ?? 'auto'
            );

            return response()->json([
                'success' => $result['success'] ?? false,
                'translated_text' => $result['translated_text'] ?? '',
                'detected_language' => $result['detected_language'] ?? null,
                'error' => $result['error'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate image using DALL-E
     */
    public function generateImage(Request $request): JsonResponse
    {
        $request->validate([
            'prompt' => 'required|string|min:10|max:4000',
            'size' => 'in:1024x1024,1792x1024,1024x1792',
            'style' => 'in:vivid,natural',
        ]);

        try {
            $result = $this->aiService->generateImage(
                $request->prompt,
                $request->size ?? '1024x1024',
                $request->style ?? 'vivid'
            );

            return response()->json([
                'success' => $result['success'] ?? false,
                'image_url' => $result['image_url'] ?? '',
                'revised_prompt' => $result['revised_prompt'] ?? '',
                'error' => $result['error'] ?? null,
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
            $keywords = $this->aiService->generateTags(
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
            $description = $this->aiService->generateSummary(
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
            'content' => 'required|string|min:50',
        ]);

        try {
            $improved = $this->aiService->proofreadContent($request->content);

            return response()->json([
                'success' => $improved['success'] ?? false,
                'improved_content' => $improved['text'] ?? '',
                'usage' => $improved['usage'] ?? [],
                'error' => $improved['error'] ?? null,
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

    /**
     * Chat with AI assistant
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'messages' => 'required|array|min:1',
            'messages.*.role' => 'required|in:system,user,assistant',
            'messages.*.content' => 'required|string',
            'model' => 'sometimes|string',
            'temperature' => 'sometimes|numeric|min:0|max:2',
            'max_tokens' => 'sometimes|integer|min:1|max:4000',
        ]);

        try {
            $result = $this->aiService->chat(
                $request->messages,
                [
                    'model' => $request->model,
                    'temperature' => $request->temperature ?? 0.7,
                    'max_tokens' => $request->max_tokens ?? 1000,
                ]
            );

            return response()->json([
                'success' => $result['success'] ?? false,
                'message' => $result['message'] ?? '',
                'usage' => $result['usage'] ?? [],
                'error' => $result['error'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * RAG-based chat with knowledge base
     */
    public function ragChat(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:10|max:2000',
            'context_documents' => 'array',
            'context_documents.*.title' => 'required|string',
            'context_documents.*.content' => 'required|string',
        ]);

        try {
            $result = $this->aiService->ragChat(
                $request->query,
                $request->context_documents ?? []
            );

            return response()->json([
                'success' => $result['success'] ?? false,
                'response' => $result['message'] ?? '',
                'usage' => $result['usage'] ?? [],
                'error' => $result['error'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check AI service availability
     */
    public function checkAvailability(): JsonResponse
    {
        return response()->json([
            'available' => $this->aiService->isAvailable(),
            'deepl_available' => $this->aiService->isDeepLAvailable(),
        ]);
    }
}
