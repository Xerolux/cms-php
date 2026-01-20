@component('mail::message')
# Confirm Subscription

Please confirm your subscription to our newsletter by clicking the button below.

@component('mail::button', ['url' => $url])
Confirm Subscription
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
