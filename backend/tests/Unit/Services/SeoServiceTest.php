<?php

namespace Tests\Unit\Services;

use App\Models\Post;
use App\Models\User;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Media;
use App\Services\SeoService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SeoServiceTest extends TestCase
{
    protected SeoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SeoService();
        Config::set('app.url', 'https://example.com');
        Config::set('app.name', 'Test Blog');
    }

    public function test_generate_open_graph_tags()
    {
        $author = User::factory()->create(['name' => 'John Doe', 'display_name' => 'John']);
        $category = Category::factory()->create(['name' => 'Tech']);
        $tag = Tag::factory()->create(['name' => 'PHP']);
        $media = Media::factory()->create([
            'url' => 'https://example.com/image.jpg',
            'width' => 1200,
            'height' => 630,
            'alt_text' => 'Test Image',
        ]);

        $post = Post::factory()->create([
            'title' => 'Test Post',
            'slug' => 'test-post',
            'excerpt' => 'Test excerpt',
            'meta_title' => 'Custom Meta Title',
            'meta_description' => 'Custom Meta Description',
            'published_at' => now(),
            'author_id' => $author->id,
        ]);

        $post->featuredImage()->associate($media);
        $post->categories()->attach($category->id);
        $post->tags()->attach($tag->id);

        $tags = $this->service->generateOpenGraphTags($post);

        $this->assertIsArray($tags);
        $this->assertEquals('article', $tags['og:type']);
        $this->assertEquals('Test Blog', $tags['og:site_name']);
        $this->assertEquals('Custom Meta Title', $tags['og:title']);
        $this->assertEquals('Custom Meta Description', $tags['og:description']);
        $this->assertEquals('https://example.com/blog/test-post', $tags['og:url']);
        $this->assertEquals('https://example.com/image.jpg', $tags['og:image']);
        $this->assertEquals(1200, $tags['og:image:width']);
        $this->assertEquals(630, $tags['og:image:height']);
        $this->assertEquals('Test Image', $tags['og:image:alt']);
    }

    public function test_generate_open_graph_tags_with_fallback_values()
    {
        $author = User::factory()->create(['name' => 'Jane Doe']);
        $post = Post::factory()->create([
            'title' => 'Another Test Post',
            'slug' => 'another-test-post',
            'excerpt' => 'Fallback excerpt',
            'meta_title' => null,
            'meta_description' => null,
            'published_at' => null,
            'author_id' => $author->id,
        ]);

        $tags = $this->service->generateOpenGraphTags($post);

        $this->assertEquals('Another Test Post', $tags['og:title']);
        $this->assertEquals('Fallback excerpt', $tags['og:description']);
        $this->assertEquals('https://example.com/default-og-image.jpg', $tags['og:image']);
    }

    public function test_generate_twitter_card_tags()
    {
        $media = Media::factory()->create([
            'url' => 'https://example.com/twitter-image.jpg',
            'alt_text' => 'Twitter Image',
        ]);

        $post = Post::factory()->create([
            'title' => 'Twitter Post',
            'slug' => 'twitter-post',
            'excerpt' => 'Twitter excerpt',
            'meta_title' => 'Custom Twitter Title',
            'meta_description' => 'Custom Twitter Description',
        ]);

        $post->featuredImage()->associate($media);

        $tags = $this->service->generateTwitterCardTags($post);

        $this->assertIsArray($tags);
        $this->assertEquals('summary_large_image', $tags['twitter:card']);
        $this->assertEquals('@yourusername', $tags['twitter:site']);
        $this->assertEquals('Custom Twitter Title', $tags['twitter:title']);
        $this->assertEquals('Custom Twitter Description', $tags['twitter:description']);
        $this->assertEquals('https://example.com/twitter-image.jpg', $tags['twitter:image']);
        $this->assertEquals('Twitter Image', $tags['twitter:image:alt']);
    }

    public function test_generate_structured_data()
    {
        $author = User::factory()->create([
            'name' => 'Author Name',
            'display_name' => 'Display Name',
        ]);
        $category = Category::factory()->create(['name' => 'Technology', 'slug' => 'technology']);

        $post = Post::factory()->create([
            'title' => 'Structured Data Test',
            'slug' => 'structured-data-test',
            'excerpt' => 'Test excerpt',
            'meta_description' => 'Meta description',
            'published_at' => now()->subDays(7),
            'author_id' => $author->id,
        ]);

        $post->categories()->attach($category->id);

        $structuredData = $this->service->generateStructuredData($post);
        $data = json_decode($structuredData, true);

        $this->assertIsArray($data);
        $this->assertEquals('https://schema.org', $data['@context']);
        $this->assertEquals('BlogPosting', $data['@type']);
        $this->assertEquals('Structured Data Test', $data['headline']);
        $this->assertEquals('Meta description', $data['description']);
        $this->assertEquals('Display Name', $data['author']['name']);
        $this->assertEquals('Organization', $data['publisher']['@type']);
        $this->assertEquals('Test Blog', $data['publisher']['name']);
    }

    public function test_generate_structured_data_includes_breadcrumbs()
    {
        $author = User::factory()->create();
        $category = Category::factory()->create(['name' => 'Tech', 'slug' => 'tech']);

        $post = Post::factory()->create([
            'title' => 'Breadcrumb Test',
            'slug' => 'breadcrumb-test',
            'published_at' => now(),
            'author_id' => $author->id,
        ]);

        $post->categories()->attach($category->id);

        $structuredData = $this->service->generateStructuredData($post);
        $data = json_decode($structuredData, true);

        $this->assertArrayHasKey('breadcrumb', $data);
        $this->assertEquals('BreadcrumbList', $data['breadcrumb']['@type']);
        $this->assertCount(4, $data['breadcrumb']['itemListElement']);
        $this->assertEquals('Home', $data['breadcrumb']['itemListElement'][0]['name']);
        $this->assertEquals('Blog', $data['breadcrumb']['itemListElement'][1]['name']);
        $this->assertEquals('Tech', $data['breadcrumb']['itemListElement'][2]['name']);
    }

    public function test_generate_canonical_url()
    {
        $post = Post::factory()->create([
            'slug' => 'canonical-test',
        ]);

        $url = $this->service->generateCanonicalUrl($post);

        $this->assertEquals('https://example.com/blog/canonical-test', $url);
    }

    public function test_generate_meta_robots_for_published_post()
    {
        $post = Post::factory()->create([
            'status' => 'published',
        ]);

        $robots = $this->service->generateMetaRobots($post);

        $this->assertEquals('index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1', $robots);
    }

    public function test_generate_meta_robots_for_draft_post()
    {
        $post = Post::factory()->create([
            'status' => 'draft',
        ]);

        $robots = $this->service->generateMetaRobots($post);

        $this->assertEquals('noindex, nofollow', $robots);
    }

    public function test_get_seo_context()
    {
        $post = Post::factory()->create([
            'title' => 'SEO Context Test',
            'slug' => 'seo-context-test',
            'status' => 'published',
            'meta_title' => 'Custom Title',
            'meta_description' => 'Custom Description',
        ]);

        $context = $this->service->getSeoContext($post);

        $this->assertIsArray($context);
        $this->assertArrayHasKey('title', $context);
        $this->assertArrayHasKey('description', $context);
        $this->assertArrayHasKey('canonical', $context);
        $this->assertArrayHasKey('robots', $context);
        $this->assertArrayHasKey('og', $context);
        $this->assertArrayHasKey('twitter', $context);
        $this->assertArrayHasKey('structured_data', $context);
        $this->assertStringContainsString('Custom Title', $context['title']);
        $this->assertEquals('Custom Description', $context['description']);
    }

    public function test_generate_meta_title_with_custom_meta_title()
    {
        $post = Post::factory()->create([
            'meta_title' => 'My Custom Title',
        ]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateMetaTitle');
        $method->setAccessible(true);

        $title = $method->invoke($this->service, $post);

        $this->assertEquals('My Custom Title | Test Blog', $title);
    }

    public function test_generate_meta_title_truncates_long_title()
    {
        $post = Post::factory()->create([
            'title' => 'This is a very long title that exceeds the recommended length and should be truncated properly',
            'meta_title' => null,
        ]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateMetaTitle');
        $method->setAccessible(true);

        $title = $method->invoke($this->service, $post);

        $this->assertLessThanOrEqual(60, strlen($title));
        $this->assertStringContainsString('...', $title);
        $this->assertStringEndsWith('| Test Blog', $title);
    }

    public function test_generate_meta_description_with_custom_meta_description()
    {
        $post = Post::factory()->create([
            'meta_description' => 'My custom meta description',
        ]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateMetaDescription');
        $method->setAccessible(true);

        $description = $method->invoke($this->service, $post);

        $this->assertEquals('My custom meta description', $description);
    }

    public function test_generate_meta_description_truncates_long_excerpt()
    {
        $post = Post::factory()->create([
            'excerpt' => 'This is a very long excerpt that exceeds the recommended meta description length of 160 characters and should be properly truncated to fit within the search engine display limits.',
            'meta_description' => null,
            'content' => '<p>Content</p>',
        ]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateMetaDescription');
        $method->setAccessible(true);

        $description = $method->invoke($this->service, $post);

        $this->assertLessThanOrEqual(160, strlen($description));
        $this->assertStringContainsString('...', $description);
    }

    public function test_generate_meta_description_falls_back_to_content()
    {
        $post = Post::factory()->create([
            'excerpt' => null,
            'meta_description' => null,
            'content' => '<p>This is the content without excerpt</p>',
        ]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateMetaDescription');
        $method->setAccessible(true);

        $description = $method->invoke($this->service, $post);

        $this->assertEquals('This is the content without excerpt', $description);
    }

    public function test_open_graph_tags_include_article_properties()
    {
        $author = User::factory()->create(['display_name' => 'Test Author']);
        $category = Category::factory()->create(['name' => 'Programming']);
        $tag1 = Tag::factory()->create(['name' => 'PHP']);
        $tag2 = Tag::factory()->create(['name' => 'Laravel']);

        $post = Post::factory()->create([
            'published_at' => now()->subDays(5),
            'author_id' => $author->id,
        ]);

        $post->categories()->attach($category->id);
        $post->tags()->attach([$tag1->id, $tag2->id]);

        $tags = $this->service->generateOpenGraphTags($post);

        $this->assertArrayHasKey('article:published_time', $tags);
        $this->assertArrayHasKey('article:modified_time', $tags);
        $this->assertArrayHasKey('article:author', $tags);
        $this->assertArrayHasKey('article:section', $tags);
        $this->assertArrayHasKey('article:tag', $tags);
        $this->assertEquals('Test Author', $tags['article:author']);
        $this->assertEquals('Programming', $tags['article:section']);
        $this->assertIsArray($tags['article:tag']);
        $this->assertCount(2, $tags['article:tag']);
    }
}
