<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Post;

class AIService
{
    protected string $apiKey;
    protected string $apiUrl;
    protected string $model;
    protected string $deepLApiKey;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
        $this->apiUrl = config('services.openai.api_url', 'https://api.openai.com/v1');
        $this->model = config('services.openai.model', 'gpt-4');
        $this->deepLApiKey = config('services.deepl.api_key', env('DEEPL_API_KEY'));
    }

    /**
     * Generate blog post content with enhanced options
     */
    public function generateContent(array $options): array
    {
        $prompt = $this->buildContentPrompt($options);

        return $this->makeRequest($prompt, [
            'max_tokens' => $options['max_tokens'] ?? 2000,
            'temperature' => $options['temperature'] ?? 0.7,
        ]);
    }

    /**
     * Generate full article with structure
     */
    public function generateFullArticle(array $options): array
    {
        $systemPrompt = "You are an expert content writer specializing in engaging, SEO-optimized blog articles.
You always write with proper structure including introduction, body paragraphs with headings, and conclusion.
Your content is unique, valuable, and tailored to the target audience.";

        $prompt = $this->buildDetailedContentPrompt($options);

        $response = $this->makeRequestWithSystem($prompt, $systemPrompt, [
            'max_tokens' => $options['max_tokens'] ?? 3000,
            'temperature' => $options['temperature'] ?? 0.7,
        ]);

        return $response;
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
     * Optimize content for SEO
     */
    public function optimizeSEO(string $title, string $content): array
    {
        $systemPrompt = "You are an SEO expert. Analyze content and provide specific, actionable SEO recommendations.";

        $prompt = "Analyze this blog post for SEO optimization:

Title: {$title}

Content: {$content}

Provide:
1. SEO Score (0-100)
2. Title recommendations
3. Meta description suggestion (155-160 characters)
4. Keyword density analysis
5. Readability score
6. Internal linking suggestions
7. Specific improvements needed";

        $response = $this->makeRequestWithSystem($prompt, $systemPrompt, [
            'max_tokens' => 1500,
            'temperature' => 0.3,
        ]);

        return $response;
    }

    /**
     * Generate tags based on content
     */
    public function generateTags(string $title, string $content, int $count = 10): array
    {
        $systemPrompt = "You are a content tagging expert. Generate relevant, specific tags that accurately represent the content.";

        $prompt = "Generate {$count} relevant tags for this blog post:

Title: {$title}

Content: {$content}

Return tags as a JSON array of strings. Tags should be:
- Specific and descriptive
- Single words or short phrases
- Lowercase
- Separated by commas
- No special characters or quotes";

        $response = $this->makeRequestWithSystem($prompt, $systemPrompt, [
            'max_tokens' => 200,
            'temperature' => 0.4,
        ]);

        $tagsText = $response['text'] ?? '[]';
        $tags = json_decode($tagsText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Fallback: parse as comma-separated list
            $tags = array_map('trim', explode(',', $tagsText));
        }

        return is_array($tags) ? $tags : [];
    }

    /**
     * Analyze sentiment of content
     */
    public function analyzeSentiment(string $content): array
    {
        $systemPrompt = "You are a sentiment analysis expert. Analyze the emotional tone and sentiment of text.";

        $prompt = "Analyze the sentiment of this content:

{$content}

Provide:
1. Overall sentiment (positive/neutral/negative)
2. Sentiment score (0-100, where 50 is neutral)
3. Emotional tone (professional, casual, emotional, analytical, etc.)
4. Confidence level (0-100)
5. Key emotions detected

Return as JSON.";

        $response = $this->makeRequestWithSystem($prompt, $systemPrompt, [
            'max_tokens' => 300,
            'temperature' => 0.2,
        ]);

        $result = $response['text'] ?? '{}';
        $decoded = json_decode($result, true);

        return $decoded ?? [
            'sentiment' => 'neutral',
            'score' => 50,
            'tone' => 'neutral',
            'confidence' => 50,
            'emotions' => []
        ];
    }

    /**
     * Check for plagiarism using semantic analysis
     */
    public function checkPlagiarism(string $content, array $existingContents = []): array
    {
        $systemPrompt = "You are a plagiarism detection expert. Analyze text similarity.";

        if (empty($existingContents)) {
            // Check against existing posts in database
            $existingContents = Post::where('status', 'published')
                ->latest()
                ->take(20)
                ->pluck('content', 'title')
                ->toArray();
        }

        $prompt = "Check this content for potential plagiarism:

Content to check:
{$content}

Compare against these existing contents:
" . json_encode(array_slice($existingContents, 0, 5, true)) . "

Provide:
1. Overall similarity score (0-100)
2. Potentially similar sections
3. Specific passages that may need rewriting
4. Uniqueness assessment
5. Recommendations for improvement";

        $response = $this->makeRequestWithSystem($prompt, $systemPrompt, [
            'max_tokens' => 1000,
            'temperature' => 0.2,
        ]);

        return $response;
    }

    /**
     * Generate headline suggestions
     */
    public function suggestHeadlines(string $topic, string $content = '', int $count = 10): array
    {
        $systemPrompt = "You are a copywriting expert specializing in creating compelling, click-worthy headlines that convert.";

        $prompt = "Generate {$count} compelling headlines for: {$topic}

";

        if ($content) {
            $prompt .= "Content summary: " . substr($content, 0, 500) . "\n\n";
        }

        $prompt .= "Requirements:
- Mix of power words, numbers, and emotional triggers
- SEO-friendly with potential keywords
- Different styles: how-to, listicle, question, shocking, etc.
- Each headline should be unique and attention-grabbing
- Return as a numbered list";

        $response = $this->makeRequestWithSystem($prompt, $systemPrompt, [
            'max_tokens' => 500,
            'temperature' => 0.8,
        ]);

        $headlines = $response['text'] ?? '';
        $lines = array_filter(array_map('trim', explode("\n", $headlines)));

        // Clean up numbering
        return array_map(function($line) {
            return preg_replace('/^\d+[\.\)]\s*/', '', $line);
        }, $lines);
    }

    /**
     * Translate content using DeepL
     */
    public function translateContent(string $content, string $targetLang, string $sourceLang = 'auto'): array
    {
        if (!$this->deepLApiKey) {
            return [
                'success' => false,
                'error' => 'DeepL API key not configured',
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'DeepL-Auth-Key ' . $this->deepLApiKey,
            ])->timeout(30)->post('https://api-free.deepl.com/v2/translate', [
                'text' => [$content],
                'target_lang' => strtoupper($targetLang),
                'source_lang' => $sourceLang === 'auto' ? null : strtoupper($sourceLang),
                'preserve_formatting' => true,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'translated_text' => $data['translations'][0]['text'] ?? '',
                    'detected_language' => $data['translations'][0]['detected_source_language'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('DeepL translation error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate image using DALL-E
     */
    public function generateImage(string $prompt, string $size = '1024x1024', string $style = 'vivid'): array
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
                ->post("{$this->apiUrl}/images/generations", [
                    'model' => 'dall-e-3',
                    'prompt' => $prompt,
                    'n' => 1,
                    'size' => in_array($size, ['1024x1024', '1792x1024', '1024x1792']) ? $size : '1024x1024',
                    'quality' => 'standard',
                    'style' => in_array($style, ['vivid', 'natural']) ? $style : 'vivid',
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'image_url' => $data['data'][0]['url'] ?? '',
                    'revised_prompt' => $data['data'][0]['revised_prompt'] ?? '',
                ];
            }

            return [
                'success' => false,
                'error' => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('DALL-E image generation error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Proofread and improve content
     */
    public function proofreadContent(string $content): array
    {
        $systemPrompt = "You are a professional editor. Improve content for clarity, grammar, flow, and engagement while maintaining the author's voice.";

        $prompt = "Proofread and improve this content:

{$content}

Provide:
1. Improved version of the content
2. List of changes made
3. Grammar corrections
4. Style improvements
5. Suggestions for further enhancement";

        $response = $this->makeRequestWithSystem($prompt, $systemPrompt, [
            'max_tokens' => 2000,
            'temperature' => 0.3,
        ]);

        return $response;
    }

    /**
     * Generate content ideas for a topic
     */
    public function generateContentIdeas(string $topic, int $count = 5): array
    {
        $systemPrompt = "You are a content strategist expert. Generate innovative, engaging content ideas.";

        $prompt = "Generate {$count} unique blog post ideas for: {$topic}

For each idea provide:
- Catchy title
- Brief description (1-2 sentences)
- Target angle or hook
- Suggested keywords

Format as a numbered list with clear sections.";

        $response = $this->makeRequestWithSystem($prompt, $systemPrompt, [
            'max_tokens' => 1000,
            'temperature' => 0.8,
        ]);

        $ideas = $response['text'] ?? '';
        return array_filter(array_map('trim', explode("\n", $ideas)));
    }

    /**
     * Suggest related posts using semantic similarity
     */
    public function suggestRelatedPosts(string $title, string $content, array $existingPosts = []): array
    {
        if (empty($existingPosts)) {
            $existingPosts = Post::where('status', 'published')
                ->select('id', 'title', 'excerpt')
                ->latest()
                ->take(20)
                ->get()
                ->toArray();
        }

        $existingTitles = collect($existingPosts)->pluck('title', 'id')->take(10)->toArray();

        $systemPrompt = "You are a content recommendation engine. Find semantically similar content.";

        $prompt = "Given this current post:
Title: {$title}
Content: " . substr($content, 0, 1000) . "

And these existing posts:
" . json_encode($existingTitles) . "

Select the 3-5 most related posts based on:
- Topic similarity
- Target audience overlap
- Content complementarity
- User journey progression

Return only the post IDs, one per line.";

        $response = $this->makeRequestWithSystem($prompt, $systemPrompt, [
            'max_tokens' => 150,
            'temperature' => 0.4,
        ]);

        $suggestions = $response['text'] ?? '';
        $ids = array_filter(array_map('trim', explode("\n", $suggestions)));

        // Return full post data for suggested IDs
        return collect($existingPosts)
            ->whereIn('id', $ids)
            ->values()
            ->toArray();
    }

    /**
     * Chat with AI assistant
     */
    public function chat(array $messages, array $options = []): array
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
                    'model' => $options['model'] ?? $this->model,
                    'messages' => $messages,
                    'max_tokens' => $options['max_tokens'] ?? 1000,
                    'temperature' => $options['temperature'] ?? 0.7,
                    'stream' => false,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'message' => $data['choices'][0]['message']['content'] ?? '',
                    'usage' => $data['usage'] ?? [],
                ];
            }

            return [
                'success' => false,
                'error' => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('AI Chat error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * RAG-based chat with knowledge base
     */
    public function ragChat(string $query, array $contextDocuments = []): array
    {
        $systemPrompt = "You are a helpful AI assistant with access to a knowledge base.
Use the provided context to answer questions accurately. If the context doesn't contain enough information, say so.";

        $context = '';
        if (!empty($contextDocuments)) {
            $context = "\n\nKnowledge Base Context:\n";
            foreach ($contextDocuments as $doc) {
                $context .= "- {$doc['title']}: " . substr($doc['content'], 0, 500) . "\n";
            }
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $query . $context],
        ];

        return $this->chat($messages);
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
     * Build detailed content prompt for full articles
     */
    protected function buildDetailedContentPrompt(array $options): string
    {
        $prompt = "Write a comprehensive blog article";

        if ($options['title'] ?? null) {
            $prompt .= " with the title: '{$options['title']}'";
        }

        if ($options['topic'] ?? null) {
            $prompt .= "\nTopic: {$options['topic']}";
        }

        $prompt .= "\n\nRequirements:";

        if ($options['tone'] ?? null) {
            $prompt .= "\n- Tone: {$options['tone']}";
        } else {
            $prompt .= "\n- Tone: Professional yet engaging";
        }

        if ($options['target_audience'] ?? null) {
            $prompt .= "\n- Target Audience: {$options['target_audience']}";
        }

        if ($options['keywords'] ?? null) {
            $prompt .= "\n- Keywords to include: " . implode(', ', $options['keywords']);
        }

        if ($options['word_count'] ?? null) {
            $prompt .= "\n- Length: Approximately {$options['word_count']} words";
        } else {
            $prompt .= "\n- Length: 1000-1500 words";
        }

        $prompt .= "\n\nStructure:";
        $prompt .= "\n1. Compelling introduction that hooks the reader";
        $prompt .= "\n2. Well-organized body with clear headings (H2, H3)";
        $prompt .= "\n3. Practical examples and actionable advice";
        $prompt .= "\n4. Strong conclusion with call-to-action";

        if ($options['outline'] ?? null) {
            $prompt .= "\n\nCustom Outline:\n" . $options['outline'];
        }

        if ($options['research_points'] ?? null) {
            $prompt .= "\n\nKey points to cover:\n" . implode("\n", $options['research_points']);
        }

        $prompt .= "\n\nFormat the content in Markdown.";

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
     * Make API request with custom system prompt
     */
    protected function makeRequestWithSystem(string $prompt, string $systemPrompt, array $options = []): array
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
                            'content' => $systemPrompt,
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

    /**
     * Check if DeepL service is available
     */
    public function isDeepLAvailable(): bool
    {
        return !empty($this->deepLApiKey);
    }
}
