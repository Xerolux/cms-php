<?php

namespace Tests\Unit\Services;

use App\Models\Post;
use App\Models\User;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Media;
use App\Services\ContentWorkflowService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContentWorkflowServiceTest extends TestCase
{
    protected ContentWorkflowService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ContentWorkflowService();
    }

    public function test_assign_post_to_user()
    {
        $post = Post::factory()->create();
        $user = User::factory()->create();

        $this->service->assignPost($post, $user->id, 'reviewer');

        $this->assertDatabaseHas('post_assignments', [
            'post_id' => $post->id,
            'user_id' => $user->id,
            'role' => 'reviewer',
        ]);
    }

    public function test_assign_post_updates_existing_assignment()
    {
        $post = Post::factory()->create();
        $user = User::factory()->create();

        DB::table('post_assignments')->insert([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'role' => 'author',
            'assigned_at' => now(),
        ]);

        $this->service->assignPost($post, $user->id, 'editor');

        $this->assertDatabaseHas('post_assignments', [
            'post_id' => $post->id,
            'user_id' => $user->id,
            'role' => 'editor',
        ]);
    }

    public function test_submit_for_review()
    {
        $post = Post::factory()->create([
            'status' => 'draft',
        ]);
        $user = User::factory()->create();

        $this->service->submitForReview($post, $user->id);

        $this->assertEquals('pending_review', $post->fresh()->status);
        $this->assertNotNull($post->fresh()->submitted_for_review_at);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'submitted_for_review',
            'model_type' => 'Post',
            'model_id' => $post->id,
        ]);
    }

    public function test_approve_post()
    {
        $post = Post::factory()->create([
            'status' => 'pending_review',
        ]);
        $reviewer = User::factory()->create();

        $this->service->approvePost($post, $reviewer->id, 'Looks good!');

        $this->assertEquals('approved', $post->fresh()->status);
        $this->assertNotNull($post->fresh()->approved_at);
        $this->assertEquals($reviewer->id, $post->fresh()->approved_by);
        $this->assertEquals('Looks good!', $post->fresh()->reviewer_feedback);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $reviewer->id,
            'action' => 'approved',
            'model_type' => 'Post',
            'model_id' => $post->id,
        ]);
    }

    public function test_approve_post_without_feedback()
    {
        $post = Post::factory()->create([
            'status' => 'pending_review',
        ]);
        $reviewer = User::factory()->create();

        $this->service->approvePost($post, $reviewer->id);

        $this->assertEquals('approved', $post->fresh()->status);
        $this->assertNull($post->fresh()->reviewer_feedback);
    }

    public function test_request_changes()
    {
        $post = Post::factory()->create([
            'status' => 'pending_review',
        ]);
        $reviewer = User::factory()->create();

        $feedback = 'Please add more details about the implementation.';

        $this->service->requestChanges($post, $reviewer->id, $feedback);

        $this->assertEquals('changes_requested', $post->fresh()->status);
        $this->assertEquals($feedback, $post->fresh()->reviewer_feedback);
        $this->assertNotNull($post->fresh()->changes_requested_at);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $reviewer->id,
            'action' => 'changes_requested',
            'model_type' => 'Post',
            'model_id' => $post->id,
        ]);
    }

    public function test_get_editorial_calendar()
    {
        $year = now()->year;
        $month = now()->month;

        $post = Post::factory()->create([
            'status' => 'draft',
            'published_at' => now()->setYear($year)->setMonth($month)->setDay(15),
        ]);

        $result = $this->service->getEditorialCalendar($year, $month);

        $this->assertEquals($year, $result['year']);
        $this->assertEquals($month, $result['month']);
        $this->assertArrayHasKey('events', $result);
        $this->assertIsArray($result['events']);
    }

    public function test_calculate_seo_score_with_all_checks_passed()
    {
        $category = Category::factory()->create();
        $tag = Tag::factory()->create();
        $media = Media::factory()->create();

        $post = Post::factory()->create([
            'title' => 'This is a perfect title length for SEO optimization',
            'meta_description' => 'This is an optimal meta description that is between 120 and 160 characters long. It provides good context for search engines.',
            'content' => '<p>This is a long content that exceeds 1000 words. ' . str_repeat('Word ', 1000) . '</p>',
            'excerpt' => 'This is a good excerpt for the post.',
            'featured_image_id' => $media->id,
        ]);

        $post->categories()->attach($category->id);
        $post->tags()->attach($tag->id);

        $result = $this->service->calculateSEOScore($post);

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('grade', $result);
        $this->assertArrayHasKey('issues', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertArrayHasKey('passes', $result);
        $this->assertGreaterThanOrEqual(90, $result['score']);
        $this->assertEquals('A', $result['grade']);
        $this->assertEmpty($result['issues']);
    }

    public function test_calculate_seo_score_with_missing_elements()
    {
        $post = Post::factory()->create([
            'title' => '',
            'meta_description' => '',
            'content' => '<p>Short content</p>',
            'excerpt' => '',
        ]);

        $result = $this->service->calculateSEOScore($post);

        $this->assertLessThan(60, $result['score']);
        $this->assertNotEmpty($result['issues']);
        $this->assertContains('Post title is missing', $result['issues']);
        $this->assertContains('Meta description is missing', $result['issues']);
        $this->assertContains('No featured image set', $result['issues']);
    }

    public function test_calculate_seo_score_with_warnings()
    {
        $post = Post::factory()->create([
            'title' => 'Short',
            'meta_description' => 'Short meta desc',
            'content' => '<p>Content with less than 300 words but more than a sentence. ' . str_repeat('More content. ', 20) . '</p>',
        ]);

        $result = $this->service->calculateSEOScore($post);

        $this->assertNotEmpty($result['warnings']);
        $this->assertContains('Post title is too short (minimum 30 characters)', $result['warnings']);
    }

    public function test_get_seo_grade()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getSEOGrade');
        $method->setAccessible(true);

        $this->assertEquals('A', $method->invoke($this->service, 95));
        $this->assertEquals('B', $method->invoke($this->service, 85));
        $this->assertEquals('C', $method->invoke($this->service, 75));
        $this->assertEquals('D', $method->invoke($this->service, 65));
        $this->assertEquals('F', $method->invoke($this->service, 55));
    }

    public function test_get_workflow_stats()
    {
        Post::factory()->count(3)->create(['status' => 'pending_review']);
        Post::factory()->count(5)->create(['status' => 'approved']);
        Post::factory()->count(2)->create(['status' => 'changes_requested']);
        Post::factory()->count(7)->create(['status' => 'draft']);

        $stats = $this->service->getWorkflowStats();

        $this->assertEquals(3, $stats['pending_review']);
        $this->assertEquals(5, $stats['approved']);
        $this->assertEquals(2, $stats['changes_requested']);
        $this->assertEquals(7, $stats['draft']);
    }

    public function test_calculate_seo_score_content_length_warning()
    {
        $post = Post::factory()->create([
            'title' => 'This is a perfect title length for SEO',
            'meta_description' => 'This is an optimal meta description that is between 120 and 160 characters long.',
            'content' => '<p>This content has between 300 and 1000 words. ' . str_repeat('Word ', 400) . '</p>',
        ]);

        $result = $this->service->calculateSEOScore($post);

        $this->assertContains('Content could be longer (recommended 1000+ words)', $result['warnings']);
    }

    public function test_calculate_seo_score_passes_categories_and_tags()
    {
        $category = Category::factory()->create();
        $tag = Tag::factory()->create();

        $post = Post::factory()->create();
        $post->categories()->attach($category->id);
        $post->tags()->attach($tag->id);

        $result = $this->service->calculateSEOScore($post);

        $this->assertContains('Categories are assigned', $result['passes']);
        $this->assertContains('Tags are assigned', $result['passes']);
    }

    public function test_editorial_calendar_includes_assignees()
    {
        $post = Post::factory()->create([
            'status' => 'draft',
        ]);

        $user = User::factory()->create();
        $post->assignees()->attach($user->id);

        $result = $this->service->getEditorialCalendar(now()->year, now()->month);

        $this->assertArrayHasKey('assignees', $result['events'][0]);
    }

    public function test_submit_for_review_logs_correct_description()
    {
        $post = Post::factory()->create([
            'title' => 'Test Post Title',
            'status' => 'draft',
        ]);
        $user = User::factory()->create();

        $this->service->submitForReview($post, $user->id);

        $log = DB::table('activity_logs')
            ->where('model_type', 'Post')
            ->where('model_id', $post->id)
            ->first();

        $this->assertStringContainsString('Test Post Title', $log->description);
        $this->assertStringContainsString('submitted for review', $log->description);
    }
}
