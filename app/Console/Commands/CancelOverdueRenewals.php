<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\Subscription;
use Carbon\Carbon;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;

class CancelOverdueRenewals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cancel-overdue-renewals';
    
    /**
     * The console command description.
    *
    * @var string
    */
    
    protected $description = 'Cancel Stripe subscriptions that failed to renew by the day after subscription_end_date';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = Carbon::today();
        $cutoff = $today->copy()->subDay();

        $unpaidStatuses = ['renew_pending', 'past_due', 'incomplete', 'unpaid', 'requires_payment_method'];
        $notAlreadyCanceled = ['active', 'renew_pending', 'past_due', 'incomplete', 'cancel_scheduled'];

        Stripe::setApiKey(config('services.stripe.secret'));

        Subscription::query()
            ->where('billing_cycle', 'recurring')
            ->where('auto_renew', true)
            ->whereDate('subscription_end_date', '<', $today)
            ->whereIn('subscription_status', $notAlreadyCanceled)
            ->whereNotNull('stripe_subscription_id')
            ->chunkById(200, function ($subs) use ($cutoff, $unpaidStatuses) {
                foreach ($subs as $sub) {
                    if (Carbon::parse($sub->subscription_end_date)->gt($cutoff)) {
                        continue; // grace day not passed yet
                    }

                    if (!in_array($sub->subscription_status, $unpaidStatuses) &&
                        ($sub->last_payment_status === 'succeeded')) {
                        continue; // they paid, skip
                    }

                    try {
                        $stripeId = $sub->stripe_subscription_id;
                        $stripeSub = StripeSubscription::retrieve($stripeId);

                        if ($stripeSub->status !== 'canceled') {
                            $stripeSub->cancel(); // immediate cancel
                        }

                        $sub->update([
                            'subscription_status' => 'canceled',
                            'canceled_at'         => now(),
                            'updated_at'          => now(),
                        ]);

                        Log::info('Canceled overdue renewal', [
                            'local_id'  => $sub->id,
                            'stripe_id' => $stripeId,
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('Failed canceling overdue renewal', [
                            'local_id'  => $sub->id,
                            'stripe_id' => $sub->stripe_subscription_id,
                            'error'     => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info('Checked and canceled overdue renewals.');
        return self::SUCCESS; 
    }
}
