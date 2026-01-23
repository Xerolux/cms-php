<?php

namespace Tests\Unit\Services;

use App\Models\Post;
use App\Models\Page;
use App\Services\ScheduleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScheduleServiceTest extends TestCase
{
    protected ScheduleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ScheduleService();
    }

    public function test_publish_scheduled_post()
    {
        $post = Post::factory()->create([
            'status' => 'scheduled',
            'published_at' => now()->subHour(),
        ]);

        $result = $this->service->publishScheduledPost($post);

        $this->assertInstanceOf(Post::class, $result);
        $this->assertEquals('published', $result->status);
        $this->assertNotNull($result->published_at);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'published',
            'model_type' => 'Post',
            'model_id' => $post->id,
        ]);
    }

    public function test_publish_scheduled_page()
    {
        $page = Page::factory()->create([
            'status' => 'scheduled',
            'published_at' => now()->subHour(),
        ]);

        $result = $this->service->publishScheduledPage($page);

        $this->assertInstanceOf(Page::class, $result);
        $this->assertEquals('published', $result->status);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'published',
            'model_type' => 'Page',
            'model_id' => $page->id,
        ]);
    }

    public function test_schedule_post_sets_status_and_dispatches_job()
    {
        Queue::fake();

        $post = Post::factory()->create([
            'status' => 'draft',
        ]);

        $publishAt = now()->addDays(3);

        $this->service->schedulePost($post, $publishAt);

        $this->assertEquals('scheduled', $post->fresh()->status);
        $this->assertEquals($publishAt, $post->fresh()->published_at);

        Queue::assertPushed(\App\Jobs\PublishScheduledPost::class);
    }

    public function test_schedule_post_throws_exception_for_past_date()
    {
        $post = Post::factory()->create();

        $pastDate = now()->subDay();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Publish date must be in the future');

        $this->service->schedulePost($post, $pastDate);
    }

    public function test_reschedule_post_deletes_old_job_and_creates_new()
    {
        Queue::fake();

        $post = Post::factory()->create([
            'status' => 'scheduled',
        ]);

        $newPublishAt = now()->addWeek();

        $this->service->reschedulePost($post, $newPublishAt);

        $this->assertEquals($newPublishAt, $post->fresh()->published_at);
    }

    public function test_cancel_scheduled_post()
    {
        $post = Post::factory()->create([
            'status' => 'scheduled',
            'published_at' => now()->addDays(2),
        ]);

        $this->service->cancelScheduledPost($post);

        $this->assertEquals('draft', $post->fresh()->status);
        $this->assertNull($post->fresh()->published_at);
    }

    public function test_get_scheduled_content()
    {
        Post::factory()->count(3)->create([
            'status' => 'scheduled',
            'published_at' => now()->addDays(1),
        ]);

        Page::factory()->count(2)->create([
            'status' => 'scheduled',
            'published_at' => now()->addDays(2),
        ]);

        $result = $this->service->getScheduledContent();

        $this->assertArrayHasKey('posts', $result);
        $this->assertArrayHasKey('pages', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertCount(3, $result['posts']);
        $this->assertCount(2, $result['pages']);
        $this->assertEquals(5, $result['total']);
    }

    public function test_get_scheduled_stats()
    {
        Post::factory()->count(5)->create([
            'status' => 'scheduled',
            'published_at' => now()->addDays(1),
        ]);

        Post::factory()->count(3)->create([
            'status' => 'scheduled',
            'published_at' => now()->subHour(), // overdue
        ]);

        $stats = $this->service->getScheduledStats();

        $this->assertArrayHasKey('total_scheduled', $stats);
        $this->assertArrayHasKey('publishing_today', $stats);
        $this->assertArrayHasKey('publishing_this_week', $stats);
        $this->assertArrayHasKey('overdue', $stats);
        $this->assertArrayHasKey('upcoming', $stats);
        $this->assertEquals(8, $stats['total_scheduled']);
        $this->assertEquals(3, $stats['overdue']);
    }

    public function test_check_and_publish_overdue_posts()
    {
        Post::factory()->count(3)->create([
            'status' => 'scheduled',
            'published_at' => now()->subHour(),
        ]);

        Page::factory()->count(2)->create([
            'status' => 'scheduled',
            'published_at' => now()->subHour(),
        ]);

        $count = $this->service->checkAndPublishOverduePosts();

        $this->assertEquals(5, $count);
        $this->assertEquals(5, Post::where('status', 'published')->count());
    }

    public function test_get_calendar_schedule()
    {
        $year = now()->year;
        $month = now()->month;

        Post::factory()->create([
            'status' => 'scheduled',
            'published_at' => now()->setYear($year)->setMonth($month)->setDay(15),
        ]);

        Page::factory()->create([
            'status' => 'scheduled',
            'published_at' => now()->setYear($year)->setMonth($month)->setDay(20),
        ]);

        $result = $this->service->getCalendarSchedule($year, $month);

        $this->assertEquals($year, $result['year']);
        $this->assertEquals($month, $result['month']);
        $this->assertArrayHasKey('events', $result);
        $this->assertCount(2, $result['events']);
    }

    public function test_publish_scheduled_post_rolls_back_on_exception()
    {
        $post = Post::factory()->create([
            'status' => 'scheduled',
        ]);

        // Force database error by using invalid data
        DB::statement('DROP TABLE IF EXISTS activity_logs_temp');

        try {
            $this->service->publishScheduledPost($post);
        } catch (\Exception $e) {
            $this->assertNotEquals('published', $post->fresh()->status);
        }
    }

    public function test_get_scheduled_content_includes_author_relationships()
    {
        $post = Post::factory()->create([
            'status' => 'scheduled',
        ]);

        $result = $this->service->getScheduledContent();

        $this->assertArrayHasKey('author', $result['posts']->first()->toArray());
    }

    public function test_get_scheduled_content_includes_category_relationships()
    {
        $post = Post::factory()->create([
            'status' => 'scheduled',
        ]);

        $category = \App\Models\Category::factory()->create();
        $post->categories()->attach($category->id);

        $result = $this->service->getScheduledContent();

        $postData = $result['posts']->first()->toArray();
        $this->assertArrayHasKey('categories', $postData);
    }

    public function test_get_scheduled_content_ordered_by_publish_date()
    {
        $post1 = Post::factory()->create([
            'status' => 'scheduled',
            'published_at' => now()->addDays(3),
        ]);

        $post2 = Post::factory()->create([
            'status' => 'scheduled',
            'published_at' => now()->addDay(),
        ]);

        $result = $this->service->getScheduledContent();

        $this->assertEquals($post2->id, $result['posts']->first()->id);
    }
}
