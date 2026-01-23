<?php

namespace Tests\Unit\Services;

use App\Models\Post;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditLogServiceTest extends TestCase
{
    protected AuditLogService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AuditLogService();
    }

    public function test_log_creates_audit_entry()
    {
        $user = User::factory()->create();
        Auth::login($user);

        $this->service->log('user_login');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'user_login',
        ]);
    }

    public function test_log_with_auditable_model()
    {
        $user = User::factory()->create();
        Auth::login($user);

        $post = Post::factory()->create();

        $this->service->log('post_updated', $post);

        $log = DB::table('audit_logs')->where('action', 'post_updated')->first();

        $this->assertEquals(Post::class, $log->auditable_type);
        $this->assertEquals($post->id, $log->auditable_id);
    }

    public function test_log_with_old_and_new_values()
    {
        $user = User::factory()->create();
        Auth::login($user);

        $oldValues = ['status' => 'draft', 'title' => 'Old Title'];
        $newValues = ['status' => 'published', 'title' => 'New Title'];

        $this->service->log('post_updated', null, $oldValues, $newValues);

        $log = DB::table('audit_logs')->where('action', 'post_updated')->first();

        $this->assertJson($log->old_values);
        $this->assertJson($log->new_values);

        $oldData = json_decode($log->old_values, true);
        $newData = json_decode($log->new_values, true);

        $this->assertEquals('draft', $oldData['status']);
        $this->assertEquals('published', $newData['status']);
    }

    public function test_log_includes_ip_address()
    {
        $user = User::factory()->create();
        Auth::login($user);

        $this->service->log('user_login');

        $log = DB::table('audit_logs')->where('action', 'user_login')->first();

        $this->assertNotNull($log->ip_address);
    }

    public function test_log_includes_user_agent()
    {
        $user = User::factory()->create();
        Auth::login($user);

        $this->service->log('user_login');

        $log = DB::table('audit_logs')->where('action', 'user_login')->first();

        $this->assertNotNull($log->user_agent);
    }

    public function test_log_includes_url()
    {
        $user = User::factory()->create();
        Auth::login($user);

        $this->service->log('user_action');

        $log = DB::table('audit_logs')->where('action', 'user_action')->first();

        $this->assertNotNull($log->url);
        $this->assertStringContainsString('http', $log->url);
    }

    public function test_log_with_description()
    {
        $user = User::factory()->create();
        Auth::login($user);

        $description = 'User manually updated their profile';

        $this->service->log('profile_updated', null, null, null, $description);

        $log = DB::table('audit_logs')->where('action', 'profile_updated')->first();

        $this->assertEquals($description, $log->description);
    }

    public function test_log_without_authentication()
    {
        Auth::logout();

        $this->service->log('guest_action');

        $log = DB::table('audit_logs')->where('action', 'guest_action')->first();

        $this->assertNull($log->user_id);
    }

    public function test_log_with_all_parameters()
    {
        $user = User::factory()->create();
        Auth::login($user);

        $post = Post::factory()->create();
        $oldValues = ['title' => 'Old'];
        $newValues = ['title' => 'New'];
        $description = 'Post title was updated';

        $this->service->log('post_updated', $post, $oldValues, $newValues, $description);

        $log = DB::table('audit_logs')->where('action', 'post_updated')->first();

        $this->assertEquals($user->id, $log->user_id);
        $this->assertEquals(Post::class, $log->auditable_type);
        $this->assertEquals($post->id, $log->auditable_id);
        $this->assertJson($log->old_values);
        $this->assertJson($log->new_values);
        $this->assertEquals($description, $log->description);
        $this->assertNotNull($log->ip_address);
        $this->assertNotNull($log->user_agent);
        $this->assertNotNull($log->url);
    }

    public function test_log_creates_timestamps()
    {
        $user = User::factory()->create();
        Auth::login($user);

        $this->service->log('test_action');

        $log = DB::table('audit_logs')->where('action', 'test_action')->first();

        $this->assertNotNull($log->created_at);
        $this->assertNotNull($log->updated_at);
    }

    public function test_log_with_null_auditable()
    {
        $user = User::factory()->create();
        Auth::login($user);

        $this->service->log('general_action', null);

        $log = DB::table('audit_logs')->where('action', 'general_action')->first();

        $this->assertNull($log->auditable_type);
        $this->assertNull($log->auditable_id);
    }

    public function test_log_with_null_values()
    {
        $user = User::factory()->create();
        Auth::login($user);

        $this->service->log('simple_action');

        $log = DB::table('audit_logs')->where('action', 'simple_action')->first();

        $this->assertNull($log->old_values);
        $this->assertNull($log->new_values);
        $this->assertNull($log->description);
    }
}
