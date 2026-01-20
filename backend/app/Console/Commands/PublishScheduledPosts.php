<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;

class PublishScheduledPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:publish-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish posts that are scheduled for publication';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $scheduledPosts = Post::scheduled()
            ->where('scheduled_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($scheduledPosts as $post) {
            $post->update([
                'status' => 'published',
                'published_at' => $post->scheduled_at ?? now(),
            ]);

            $count++;
            $this->info("Published: {$post->title}");
        }

        if ($count === 0) {
            $this->info('No scheduled posts to publish.');
        } else {
            $this->info("Successfully published {$count} scheduled post(s).");
        }

        return Command::SUCCESS;
    }
}
