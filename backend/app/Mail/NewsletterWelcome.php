<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewsletterWelcome extends Mailable
{
    use Queueable, SerializesModels;

    public $subscriber;
    public $url;

    public function __construct(NewsletterSubscriber $subscriber)
    {
        $this->subscriber = $subscriber;
        $this->url = config('app.url');
    }

    public function build()
    {
        return $this->markdown('emails.newsletter.welcome')
                    ->subject('Welcome to our Newsletter');
    }
}
