<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\Price;
use Stripe\Subscription as StripeSubscription;
use Illuminate\Support\Facades\Auth;

class BillingController extends Controller
{
    public function purchase(Request $req)
    {
        $validated = $req->validate([
            'plan_id' => ['required','integer','exists:subscription_listing,id'],
            'payment_method' => ['required','string'],   // PaymentMethod ID from Stripe Elements
            'auto_renew' => ['nullable','boolean'],
        ]);

        // Get the currently authenticated user from the token
        $user = Auth::guard('api')->user();
        $plan = DB::table('subscription_listing')->where('id',$validated['plan_id'])->first();
        // dd($user,$plan);
        $amountCents = (int) round(floatval($plan->price) * 100);
        $currency = config('services.stripe.currency', 'usd');
        $autoRenew = array_key_exists('auto_renew', $validated)
            ? (bool)$validated['auto_renew']
            : ($plan->type === 'recurring');

        Stripe::setApiKey(config('services.stripe.secret'));

        // ensure/reuse stripe customer
        $existingCustomerId = Subscription::where('user_id',$user->id)
            ->whereNotNull('stripe_customer_id')
            ->value('stripe_customer_id');

        if (!$existingCustomerId) {
            $customer = Customer::create([
                'email' => $user->email,
                'name'  => $user->name,
                'payment_method' => $validated['payment_method'],
                'invoice_settings' => ['default_payment_method' => $validated['payment_method']],
            ]);
            $stripeCustomerId = $customer->id;
        } else {
            $stripeCustomerId = $existingCustomerId;
            Customer::update($stripeCustomerId, [
                'invoice_settings' => ['default_payment_method' => $validated['payment_method']],
            ]);
        }

        // create local row (pending)
        $startsAt = Carbon::now();
        $endsAt   = $this->computeEnd($startsAt, (int)$plan->duration, $plan->duration_unit);

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'user_email' => $user->email,
            'order_id' => strtoupper(uniqid('ORD_')),
            'plan_id' => $plan->id,
            'subscription_name' => $plan->subscription_name,
            'subscription_period' => "{$plan->duration} " . ucfirst($plan->duration_unit),
            'billing_cycle' => $plan->type,
            'subscription_start_date' => $startsAt->toDateString(),
            'subscription_end_date' => $endsAt->toDateString(),
            'total_amount' => $plan->price,
            'currency' => $currency,
            'payment_method' => 'Stripe',
            'payment_status' => 'pending',
            'subscription_status' => 'incomplete',
            'auto_renew' => $autoRenew,
            'stripe_customer_id' => $stripeCustomerId,
            'is_first_payment' => true,
        ]);

        if ($plan->type === 'one_time') {
            if ($amountCents > 0) {
                $pi = PaymentIntent::create([
                    'amount' => $amountCents,
                    'currency' => $currency,
                    'customer' => $stripeCustomerId,
                    'payment_method' => $validated['payment_method'],
                    'confirm' => true,
                    'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
                    'description' => "One-time purchase: {$plan->subscription_name}",
                ]);

                $subscription->update([
                    'stripe_payment_intent_id' => $pi->id,
                    'transaction_id' => $pi->id,
                    'last_payment_status' => $pi->status,
                    'last_payment_at' => now(),
                ]);

                if (!in_array($pi->status, ['succeeded','requires_capture'])) {
                    return response()->json([
                        'requires_action' => $pi->status === 'requires_action',
                        'payment_intent_client_secret' => $pi->client_secret ?? null,
                        'message' => 'Payment incomplete',
                    ], 402);
                }
            }

            // free or succeeded
            $subscription->update([
                'payment_status' => 'succeeded',
                'subscription_status' => 'active',
            ]);

            return response()->json([
                'subscription_id' => $subscription->id,
                'status' => 'active',
                'billing_cycle' => 'one_time',
                'starts_at' => $subscription->subscription_start_date,
                'ends_at' => $subscription->subscription_end_date,
            ]);
        }

        // recurring
        $interval = $this->mapInterval($plan->duration_unit); // day|week|month|year
        $intervalCount = (int) $plan->duration;

        $price = Price::create([
            'unit_amount' => $amountCents,
            'currency' => $currency,
            'recurring' => [
                'interval' => $interval,
                'interval_count' => $intervalCount,
            ],
            'product_data' => ['name' => $plan->subscription_name],
        ]);

        $cancelAt = $autoRenew ? null : $endsAt->timestamp;

        $stripeSub = StripeSubscription::create([
            'customer' => $stripeCustomerId,
            'items' => [[ 'price' => $price->id ]],
            'payment_behavior' => 'default_incomplete',
            'expand' => ['latest_invoice.payment_intent'],
            'cancel_at' => $cancelAt,
        ]);

        $pi = $stripeSub->latest_invoice->payment_intent ?? null;

        $subscription->update([
            'stripe_subscription_id' => $stripeSub->id,
            'stripe_price_id' => $price->id,
            'stripe_invoice_id' => $stripeSub->latest_invoice->id ?? null,
            'transaction_id' => $stripeSub->id,
        ]);

        if ($pi && $pi->status === 'requires_action') {
            $subscription->update([
                'last_payment_status' => $pi->status,
                'last_payment_at' => now(),
            ]);
            return response()->json([
                'requires_action' => true,
                'payment_intent_client_secret' => $pi->client_secret,
                'stripe_subscription_id' => $stripeSub->id,
                'message' => '3DS authentication required',
            ], 200);
        }

        if ($pi && $pi->status === 'succeeded') {
            $subscription->update([
                'payment_status' => 'succeeded',
                'subscription_status' => 'active',
                'last_payment_status' => 'succeeded',
                'last_payment_at' => now(),
            ]);

            return response()->json([
                'subscription_id' => $subscription->id,
                'status' => 'active',
                'billing_cycle' => 'recurring',
                'auto_renew' => $autoRenew,
                'starts_at' => $subscription->subscription_start_date,
                'ends_at' => $subscription->subscription_end_date,
            ]);
        }

        // otherwise, wait for webhook
        return response()->json([
            'subscription_id' => $subscription->id,
            'status' => 'incomplete',
            'message' => 'Awaiting payment confirmation',
        ], 202);
    }

    public function cancel($id)
    {
        $subscription = Subscription::find($id);
        if (!$subscription) return response()->json(['message' => 'Not found'], 404);

        if ($subscription->billing_cycle === 'recurring' && $subscription->stripe_subscription_id) {
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            \Stripe\Subscription::update($subscription->stripe_subscription_id, [
                'cancel_at_period_end' => true,
            ]);
        }

        $subscription->update([
            'cancel_at_period_end' => true,
            'subscription_status' => 'cancel_scheduled',
        ]);

        return response()->json(['message' => 'Will cancel at period end']);
    }

    private function computeEnd(Carbon $start, int $duration, string $unit): Carbon
    {
        return match (strtolower($unit)) {
            'day','days'     => $start->copy()->addDays($duration),
            'week','weeks'   => $start->copy()->addWeeks($duration),
            'month','months' => $start->copy()->addMonths($duration),
            'year','years'   => $start->copy()->addYears($duration),
            default          => $start->copy()->addMonths($duration),
        };
    }

    private function mapInterval(string $unit): string
    {
        return match (strtolower($unit)) {
            'day','days'     => 'day',
            'week','weeks'   => 'week',
            'month','months' => 'month',
            'year','years'   => 'year',
            default          => 'month',
        };
    }
}
