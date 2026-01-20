@component('mail::message')
# Welcome!

You have successfully subscribed to our newsletter.

@component('mail::button', ['url' => $url])
Visit Website
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
