<x-mail::message>
# Subscription Renewal Reminder

Hi {{ $subscription->user->name }},

Your **{{ $subscription->subscription_name }}** subscription is set to expire tomorrow ({{ \Carbon\Carbon::parse($subscription->subscription_end_date)->format('F d, Y') }}).

Renew today to keep enjoying uninterrupted access.

<x-mail::button :url="'https://fstg.beastierated.com/subscriptions'">
Renew Now
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
