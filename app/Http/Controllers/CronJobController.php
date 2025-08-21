<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Subscription;
use Carbon\Carbon;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;

class CronJobController extends Controller
{
    /**
     * Run the "cancel overdue renewals" job via HTTP.
     * TIP: Protect this route (auth/signed key/IP allowlist) before exposing in prod.
    */

    public function cancelOverdueRenewals(Request $request)
{
     $today  = Carbon::today();
    // $today = Carbon::today()->addMonth()->addDay();
    $cutoff = $today->copy()->subDay(); 

    $unpaidStatuses       = ['renew_pending', 'past_due', 'incomplete', 'unpaid', 'requires_payment_method'];
    $notAlreadyCanceled   = ['active', 'renew_pending', 'past_due', 'incomplete', 'cancel_scheduled'];

    Stripe::setApiKey(config('services.stripe.secret'));

    $found   = 0;
    $skipped = 0;
    $canceled = 0;
    $errors  = 0;
    $idsCanceled = [];
    $idsErrors   = [];
    $errorsDetail = []; // keep this so you can see exact reasons

   $subscription = Subscription::query()
        ->where('billing_cycle', 'recurring')
        ->where('auto_renew', true)
        ->whereDate('subscription_end_date', '<', $today) // end date has passed
        ->whereIn('subscription_status', $notAlreadyCanceled)
        ->whereNotNull('stripe_subscription_id')
        ->orderBy('id')
        ->chunkById(200, function ($subs) use (
            $cutoff, $unpaidStatuses, &$found, &$skipped, &$canceled, &$errors, &$idsCanceled, &$idsErrors, &$errorsDetail
        ) {
            foreach ($subs as $sub) {
                $found++;

                // Enforce “cancel next day” grace
                if (Carbon::parse($sub->subscription_end_date)->gt($cutoff)) {
                    $skipped++;
                    continue;
                }

                // If they actually paid (renewal succeeded), skip
                if (!in_array($sub->subscription_status, $unpaidStatuses) &&
                    ($sub->last_payment_status === 'succeeded')) {
                    $skipped++;
                    continue;
                }

                try {
                    $stripeId = $sub->stripe_subscription_id;
                    $stripeSub = \Stripe\Subscription::retrieve($stripeId);

                    if ($stripeSub->status !== 'canceled') {
                        // Cancel immediately (not "at period end")
                        $stripeSub->cancel();
                    }

                    $sub->update([
                        'subscription_status' => 'canceled',
                        'canceled_at'         => now(),
                        'updated_at'          => now(),
                    ]);

                    $canceled++;
                    $idsCanceled[] = $sub->id;

                    \Log::info('Canceled overdue renewal (HTTP job)', [
                        'local_id'  => $sub->id,
                        'stripe_id' => $stripeId,
                    ]);
                } catch (\Stripe\Exception\InvalidRequestException $e) {
                    // Handle "No such subscription" (already deleted in Stripe)
                    $msg = $e->getMessage();
                    if (stripos($msg, 'No such subscription') !== false) {
                        $sub->update([
                            'subscription_status' => 'canceled',
                            'canceled_at'         => now(),
                            'updated_at'          => now(),
                        ]);

                        $canceled++;
                        $idsCanceled[] = $sub->id;

                        \Log::warning('Stripe says subscription no longer exists; mirrored local as canceled', [
                            'local_id'  => $sub->id,
                            'stripe_id' => $sub->stripe_subscription_id,
                            'error'     => $msg,
                        ]);

                        // Do NOT count as an error — it’s already canceled upstream
                        continue;
                    }

                    // Other InvalidRequest errors → count as error
                    $errors++;
                    $idsErrors[] = $sub->id;
                    $detail = [
                        'local_id'   => $sub->id,
                        'stripe_id'  => $sub->stripe_subscription_id,
                        'exception'  => get_class($e),
                        'message'    => $e->getMessage(),
                        'request_id' => $e->getRequestId(),
                    ];
                    if ($e->getError()) {
                        $detail['stripe_code']         = $e->getError()->code ?? null;
                        $detail['stripe_decline_code'] = $e->getError()->decline_code ?? null;
                        $detail['param']               = $e->getError()->param ?? null;
                        $detail['doc_url']             = $e->getError()->doc_url ?? null;
                    }
                    $errorsDetail[] = $detail;
                    \Log::error('Failed canceling overdue renewal (HTTP job)', $detail);

                } catch (\Throwable $e) {
                    $errors++;
                    $idsErrors[] = $sub->id;

                    $detail = [
                        'local_id'   => $sub->id,
                        'stripe_id'  => $sub->stripe_subscription_id,
                        'exception'  => get_class($e),
                        'message'    => $e->getMessage(),
                    ];
                    $errorsDetail[] = $detail;
                    \Log::error('Failed canceling overdue renewal (HTTP job)', $detail);
                }
            }
        });
    return response()->json([
        'ok'           => true,
        'found'        => $found,
        'skipped'      => $skipped,
        'canceled'     => $canceled,
        'errors'       => $errors,
        'ids_canceled' => $idsCanceled,
        'ids_errors'   => $idsErrors,
        'errors_detail'=> $errorsDetail,
        'ran_at'       => now()->toDateTimeString(),
    ]);
}
}