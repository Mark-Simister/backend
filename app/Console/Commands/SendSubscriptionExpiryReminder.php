<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionExpiryReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:send-subscription-expiry-reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminder emails to users about upcoming subscription expirations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tomorrow = Carbon::tomorrow()->toDateString();

        $subscriptions = Subscription::whereDate('subscription_end_date', $tomorrow)
            ->where('subscription_status', 'active')
            ->get();

        foreach ($subscriptions as $sub) {
            if ($this->isFreePlan($sub)) {
                // Free plan reminder
                Mail::to($sub->user_email)->send(new \App\Mail\FreeTrialExpiring($sub));
            } else {
                // Paid plan reminder
                Mail::to($sub->user_email)->send(new \App\Mail\PaidSubscriptionExpiring($sub));
            }
        }

        $this->info("Expiry reminder emails sent successfully.");
    }

    private function isFreePlan($subscription): bool
    {
        return $subscription->total_amount == 0 || strtolower($subscription->subscription_name) === 'free';
    }
}
