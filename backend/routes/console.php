<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\PublishScheduledPosts;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('posts:publish-scheduled', function () {
    $command = new PublishScheduledPosts();
    return $command->handle();
})->describe('Publish scheduled posts');
