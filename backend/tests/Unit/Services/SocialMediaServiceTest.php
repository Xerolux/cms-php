<?php

namespace Tests\Unit\Services;

use App\Models\Post;
use App\Services\SocialMediaService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SocialMediaServiceTest extends TestCase
{
    protected SocialMediaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SocialMediaService();
    }

    public function test_post_to_twitter_returns_true_on_success()
    {
        Config::set('services.twitter.bearer_token', 'test-token');

        Http::fake([
            'api.twitter.com/*' => Http::response(['data' => ['id' => '123']], 200),
        ]);

        $post = Post::factory()->create([
            'title' => 'Test Post',
            'slug' => 'test-post',
        ]);

        $result = $this->service->postToTwitter($post);

        $this->assertTrue($result);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.twitter.com/2/tweets') &&
                   $request->method() === 'POST';
        });
    }

    public function test_post_to_twitter_returns_false_when_no_credentials()
    {
        Config::set('services.twitter.bearer_token', null);

        $post = Post::factory()->create();

        $result = $this->service->postToTwitter($post);

        $this->assertFalse($result);
    }

    public function test_post_to_twitter_returns_false_on_api_error()
    {
        Config::set('services.twitter.bearer_token', 'test-token');

        Http::fake([
            'api.twitter.com/*' => Http::response(['error' => 'Invalid token'], 401),
        ]);

        $post = Post::factory()->create();

        $result = $this->service->postToTwitter($post);

        $this->assertFalse($result);
    }

    public function test_post_to_facebook_returns_true_on_success()
    {
        Config::set('services.facebook.page_access_token', 'test-token');
        Config::set('services.facebook.page_id', '123');

        Http::fake([
            'graph.facebook.com/*' => Http::response(['id' => '456'], 200),
        ]);

        $post = Post::factory()->create([
            'title' => 'Test Post',
            'slug' => 'test-post',
        ]);

        $result = $this->service->postToFacebook($post);

        $this->assertTrue($result);
    }

    public function test_post_to_facebook_returns_false_when_no_credentials()
    {
        Config::set('services.facebook.page_access_token', null);

        $post = Post::factory()->create();

        $result = $this->service->postToFacebook($post);

        $this->assertFalse($result);
    }

    public function test_post_to_linkedin_returns_true_on_success()
    {
        Config::set('services.linkedin.access_token', 'test-token');
        Config::set('services.linkedin.page_id', 'abc123');

        Http::fake([
            'api.linkedin.com/*' => Http::response(['id' => '789'], 201),
        ]);

        $post = Post::factory()->create([
            'title' => 'Test Post',
            'slug' => 'test-post',
        ]);

        $result = $this->service->postToLinkedIn($post);

        $this->assertTrue($result);
    }

    public function test_post_to_linkedin_returns_false_when_no_credentials()
    {
        Config::set('services.linkedin.access_token', null);

        $post = Post::factory()->create();

        $result = $this->service->postToLinkedIn($post);

        $this->assertFalse($result);
    }

    public function test_post_to_multiple_platforms()
    {
        Config::set('services.twitter.bearer_token', 'test-token');
        Config::set('services.facebook.page_access_token', 'test-token');
        Config::set('services.facebook.page_id', '123');

        Http::fake([
            'api.twitter.com/*' => Http::response(['id' => '123'], 200),
            'graph.facebook.com/*' => Http::response(['id' => '456'], 200),
        ]);

        $post = Post::factory()->create();

        $results = $this->service->postToMultiple($post, ['twitter', 'facebook']);

        $this->assertIsArray($results);
        $this->assertArrayHasKey('twitter', $results);
        $this->assertArrayHasKey('facebook', $results);
        $this->assertTrue($results['twitter']);
        $this->assertTrue($results['facebook']);
    }

    public function test_get_connection_status()
    {
        Config::set('services.twitter.bearer_token', 'test-token');
        Config::set('services.facebook.page_access_token', null);
        Config::set('services.linkedin.access_token', 'test-token');

        $status = $this->service->getConnectionStatus();

        $this->assertIsArray($status);
        $this->assertArrayHasKey('twitter', $status);
        $this->assertArrayHasKey('facebook', $status);
        $this->assertArrayHasKey('linkedin', $status);
        $this->assertTrue($status['twitter']['configured']);
        $this->assertFalse($status['facebook']['configured']);
        $this->assertTrue($status['linkedin']['configured']);
    }

    public function test_track_share_inserts_to_database()
    {
        $postId = 123;
        $platform = 'twitter';

        $this->service->trackShare($platform, $postId);

        $this->assertDatabaseHas('social_shares', [
            'platform' => $platform,
            'post_id' => $postId,
        ]);
    }

    public function test_get_share_stats()
    {
        $postId = 123;

        // Insert test data
        \DB::table('social_shares')->insert([
            ['platform' => 'twitter', 'post_id' => $postId, 'shared_at' => now()],
            ['platform' => 'twitter', 'post_id' => $postId, 'shared_at' => now()],
            ['platform' => 'facebook', 'post_id' => $postId, 'shared_at' => now()],
        ]);

        $stats = $this->service->getShareStats($postId);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('twitter', $stats);
        $this->assertArrayHasKey('facebook', $stats);
        $this->assertEquals(2, $stats['twitter']);
        $this->assertEquals(1, $stats['facebook']);
    }

    public function test_generate_twitter_message_truncates_long_title()
    {
        $post = Post::factory()->make([
            'title' => 'This is a very long title that should be truncated because it exceeds the maximum character limit for Twitter',
            'slug' => 'test-post',
        ]);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('generateTwitterMessage');
        $method->setAccessible(true);

        $message = $method->invoke($this->service, $post);

        $this->assertLessThanOrEqual(280, strlen($message));
        $this->assertStringContainsString('...', $message);
    }

    public function test_schedule_social_post_dispatches_job()
    {
        $post = Post::factory()->create();
        $publishAt = new \DateTime('+1 day');

        $this->expectsJobs(\App\Jobs\PostToSocialMedia::class);

        $this->service->scheduleSocialPost($post, ['twitter', 'facebook'], $publishAt);
    }
}
