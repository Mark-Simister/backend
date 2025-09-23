<x-mail::message>
# Your Free Trial is Ending Soon

Hi {{ $subscription->user->name }},

Your **free 1-month subscription** will expire tomorrow ({{ \Carbon\Carbon::parse($subscription->subscription_end_date)->format('F d, Y') }}).

To continue enjoying uninterrupted access, kindly purchase a subscription plan today.

<x-mail::button :url="'https://fstg.beastierated.com/subscription'">
Upgrade Now
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
