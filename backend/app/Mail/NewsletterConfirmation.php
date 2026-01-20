<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewsletterConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public $subscriber;
    public $url;

    public function __construct(NewsletterSubscriber $subscriber)
    {
        $this->subscriber = $subscriber;
        $this->url = url("/api/v1/newsletter/confirm/{$subscriber->confirmation_token}");
        // Note: In a real app, this should link to the FRONTEND, not the API.
        // Assuming Frontend URL is configurable or inferable.
        // Let's assume APP_URL points to frontend or we use a separate config.
        // For now, linking to API is technically functional for confirmation but bad UX.
        // Better: Link to Frontend Route which calls API.
        // Example: https://frontend.com/newsletter/confirm?token=...
        // I will use APP_URL/newsletter/confirm/{token}
        $this->url = config('app.url') . "/newsletter/confirm/{$subscriber->confirmation_token}";
    }

    public function build()
    {
        return $this->markdown('emails.newsletter.confirmation')
                    ->subject('Confirm your subscription');
    }
}
