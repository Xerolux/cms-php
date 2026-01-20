<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    protected string $apiKey;
    protected string $apiUrl;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
        $this->apiUrl = config('services.openai.api_url', 'https://api.openai.com/v1');
        $this->model = config('services.openai.model', 'gpt-3.5-turbo');
    }

    /**
     * Generate blog post content
     */
    public function generateContent(array $options): array
    {
        $prompt = $this->buildContentPrompt($options);

        return $this->makeRequest($prompt, [
            'max_tokens' => $options['max_tokens'] ?? 1000,
            'temperature' => $options['temperature'] ?? 0.7,
        ]);
    }

    /**
     * Generate summary for post
     */
    public function generateSummary(string $content, int $length = 150): string
    {
        $prompt = "Summarize the following blog post in {$length} characters or less:\n\n{$content}";

        $response = $this->makeRequest($prompt, [
            'max_tokens' => 200,
            'temperature' => 0.5,
        ]);

        return $response['text'] ?? '';
    }

    /**
     * Generate SEO keywords
     */
    public function generateKeywords(string $title, string $content): array
    {
        $prompt = "Generate 10 SEO keywords for the following blog post. Return as comma-separated values:\n\nTitle: {$title}\n\nContent: {$content}";

        $response = $this->makeRequest($prompt, [
            'max_tokens' => 100,
            'temperature' => 0.3,
        ]);

        $keywords = $response['text'] ?? '';
        return array_map('trim', explode(',', $keywords));
    }

    /**
     * Generate meta description
     */
    public function generateMetaDescription(string $content, int $length = 160): string
    {
        return $this->generateSummary($content, $length);
    }

    /**
     * Suggest related posts
     */
    public function suggestRelatedPosts(string $title, string $content, array $existingPosts = []): array
    {
        $existingTitles = collect($existingPosts)->pluck('title')->take(10)->implode("\n");

        $prompt = "From the following existing blog post titles, suggest 3 most related posts for:\n\n{$title}\n\nExisting posts:\n{$existingTitles}\n\nReturn only the titles, one per line.";

        $response = $this->makeRequest($prompt, [
            'max_tokens' => 150,
            'temperature' => 0.4,
        ]);

        $suggestions = $response['text'] ?? '';
        return array_filter(array_map('trim', explode("\n", $suggestions)));
    }

    /**
     * Proofread and improve content
     */
    public function proofreadContent(string $content): array
    {
        $prompt = "Proofread the following blog post content. Provide improved version and suggestions:\n\n{$content}";

        return $this->makeRequest($prompt, [
            'max_tokens' => 2000,
            'temperature' => 0.3,
        ]);
    }

    /**
     * Generate content ideas
     */
    public function generateContentIdeas(string $topic, int $count = 5): array
    {
        $prompt = "Generate {$count} blog post title ideas for topic: {$topic}. Return as numbered list.";

        $response = $this->makeRequest($prompt, [
            'max_tokens' => 300,
            'temperature' => 0.8,
        ]);

        $ideas = $response['text'] ?? '';
        return array_filter(array_map('trim', explode("\n", $ideas)));
    }

    /**
     * Build content generation prompt
     */
    protected function buildContentPrompt(array $options): string
    {
        $prompt = "Write a blog post";

        if ($options['title'] ?? null) {
            $prompt .= " titled '{$options['title']}'";
        }

        if ($options['topic'] ?? null) {
            $prompt .= " about '{$options['topic']}'";
        }

        if ($options['tone'] ?? null) {
            $prompt .= " with a {$options['tone']} tone";
        }

        if ($options['length'] ?? null) {
            $prompt .= " of approximately {$options['length']} words";
        }

        if ($options['keywords'] ?? null) {
            $prompt .= " including keywords: " . implode(', ', $options['keywords']);
        }

        if ($options['outline'] ?? null) {
            $prompt .= "\n\nFollow this outline:\n" . $options['outline'];
        }

        return $prompt;
    }

    /**
     * Make API request
     */
    protected function makeRequest(string $prompt, array $options = []): array
    {
        if (!$this->apiKey) {
            return [
                'success' => false,
                'error' => 'OpenAI API key not configured',
            ];
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(60)
                ->post("{$this->apiUrl}/chat/completions", [
                    'model' => $this->model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a helpful blog content writer assistant.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'max_tokens' => $options['max_tokens'] ?? 500,
                    'temperature' => $options['temperature'] ?? 0.7,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'text' => $data['choices'][0]['message']['content'] ?? '',
                    'usage' => $data['usage'] ?? [],
                ];
            }

            return [
                'success' => false,
                'error' => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('AI Service error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if AI service is available
     */
    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }
}
