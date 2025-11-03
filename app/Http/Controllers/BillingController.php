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
use Stripe\PaymentMethod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;



class BillingController extends Controller
{

    private function getRealtimeRate(string $from, string $to): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to)
            return 1.0;

        $cacheKey = "fx_{$from}_{$to}";
        // Try cache FIRST (stale-while-revalidate semantics)
        if (($cached = Cache::get($cacheKey)) && is_numeric($cached) && $cached > 0) {
            return (float) $cached;
        }

        // Try fresh fetch; if success, save and return; else, try reverse cache
        $rate = $this->fetchFromYahoo($from, $to)
            ?? $this->fetchFromExchangerateHost($from, $to)
            ?? $this->fetchFromEcb($from, $to);

        if (is_numeric($rate) && $rate > 0) {
            Cache::put($cacheKey, $rate, now()->addMinutes(15));
            return (float) $rate;
        }

        // Try reverse cached pair as a last-ditch (invert)
        $revKey = "fx_{$to}_{$from}";
        if (($rev = Cache::get($revKey)) && is_numeric($rev) && $rev > 0) {
            $inv = 1.0 / (float) $rev;
            Cache::put($cacheKey, $inv, now()->addMinutes(15));
            return $inv;
        }

        // Final fallback: 1.0 (you can choose to bail out instead)
        return 1.0;
    }

    private function fetchFromYahoo(string $from, string $to): ?float
    {
        try {
            $pair = $from . $to . '=X';
            $headers = [
                'User-Agent' => 'Mozilla/5.0',
                'Accept' => 'application/json,text/plain,*/*',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Connection' => 'keep-alive',
            ];
            $resp = Http::timeout(8)->withHeaders($headers)
                ->get("https://query1.finance.yahoo.com/v7/finance/quote?symbols={$pair}");

            if ($resp->status() === 401 || !$resp->successful()) {
                $resp = Http::timeout(8)->withHeaders($headers)
                    ->get("https://query2.finance.yahoo.com/v7/finance/quote?symbols={$pair}");
            }
            if (!$resp->successful())
                return null;

            $json = $resp->json();
            $price = $json['quoteResponse']['result'][0]['regularMarketPrice'] ?? null;
            if (is_numeric($price) && $price > 0)
                return (float) $price;

            // Reverse pair & invert
            $rev = Http::timeout(8)->withHeaders($headers)
                ->get("https://query1.finance.yahoo.com/v7/finance/quote?symbols={$to}{$from}=X");
            if (!$rev->successful())
                return null;
            $rjson = $rev->json();
            $r = $rjson['quoteResponse']['result'][0]['regularMarketPrice'] ?? null;
            return (is_numeric($r) && $r > 0) ? (1.0 / (float) $r) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function fetchFromExchangerateHost(string $from, string $to): ?float
    {
        try {
            $resp = Http::timeout(8)->get("https://api.exchangerate.host/convert?from={$from}&to={$to}&amount=1");
            if (!$resp->successful())
                return null;
            $rate = $resp->json('result');
            return (is_numeric($rate) && $rate > 0) ? (float) $rate : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function fetchFromEcb(string $from, string $to): ?float
    {
        try {
            $resp = Http::timeout(8)->get('https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml');
            if (!$resp->successful())
                return null;
            $xml = simplexml_load_string($resp->body());
            if (!$xml)
                return null;

            $cube = $xml->xpath('//gesmes:Envelope/*[local-name()="Cube"]/*[local-name()="Cube"]/*[local-name()="Cube"]');
            $eurMap = ['EUR' => 1.0];
            foreach ($cube as $c) {
                $attr = $c->attributes();
                if (isset($attr['currency'], $attr['rate'])) {
                    $eurMap[(string) $attr['currency']] = (float) $attr['rate'];
                }
            }

            if ($from === 'EUR' && isset($eurMap[$to]))
                return (float) $eurMap[$to];
            if ($to === 'EUR' && isset($eurMap[$from]))
                return 1.0 / (float) $eurMap[$from];
            if (isset($eurMap[$from], $eurMap[$to]))
                return $eurMap[$to] / $eurMap[$from];
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }


    public function purchase(Request $req)
    {
        // 1) Validate light
        $validated = $req->validate([
            'plan_id' => ['required', 'integer', 'exists:subscription_listing,id'],
            'payment_method' => ['required', 'string'],
            'auto_renew' => ['nullable', 'boolean'],
            //  'region'         => ['nullable','string'],
        ]);

        // 2) Region → currency (same as yours, trimmed)
        // $inputRegion     = strtoupper((string)($validated['region'] ?? $req->input('region','')));
        $allowedRegions = ['AU', 'CA', 'UK', 'US', 'GLOBAL'];

        // Normalize route param
        $routeRegion = $req->route('region');      
        $inputRegion = strtoupper((string) $routeRegion);
        $regionCode = in_array($inputRegion, $allowedRegions, true) ? $inputRegion : 'GLOBAL';

        // (optional) merge so it's part of $validated
        $req->merge(['region' => $regionCode]);

        // dd($routeRegion);

        $regionCurrency = match ($regionCode) {
            'AU' => 'AUD',
            'CA' => 'CAD',
            'UK' => 'GBP',
            'US' => 'USD',
            'GLOBAL' => 'AUD',
            // 'GLOBAL' => 'INR',
            // default => 'INR',
            default => 'AUD',
        };
        $baseCurrency = 'USD';

        // 3) FX + amount math
        $fxRate = $this->getRealtimeRate($baseCurrency, $regionCurrency);
        $user = Auth::guard('api')->user();
        $plan = DB::table('subscription_listing')->where('id', $validated['plan_id'])->first();

        if (!$plan) {
            return response()->json(['message' => 'Plan not found'], 404);
        }

        $priceLocal = round((float) $plan->price * $fxRate, 2);
        $zeroDecimals = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];
        $currency = strtolower($regionCurrency);
        $amountCents = in_array(strtoupper($regionCurrency), $zeroDecimals, true)
            ? (int) round($priceLocal)
            : (int) round($priceLocal * 100);

        // dd($inputRegion, $priceLocal, $zeroDecimals, $currency, $plan);
        $autoRenew = array_key_exists('auto_renew', $validated)
            ? (bool) $validated['auto_renew']
            : ($plan->type === 'recurring');

        // 4) Stripe setup (key + customer reuse)
        Stripe::setApiKey(config('services.stripe.secret'));

        $existingCustomerId = \App\Models\Subscription::where('user_id', $user->id)
            ->whereNotNull('stripe_customer_id')
            ->value('stripe_customer_id');

        if (!$existingCustomerId) {
            $customer = Customer::create([
                'email' => $user->email,
                'name' => $user->name,
            ]);
            $stripeCustomerId = $customer->id;
        } else {
            $stripeCustomerId = $existingCustomerId;
        }

        // Attach PM if needed
        $pmId = $validated['payment_method'];
        // dd($pmId);
        try {
            $pm = PaymentMethod::retrieve($pmId);
            if (empty($pm->customer)) {
                $pm->attach(['customer' => $stripeCustomerId]);
            } elseif ($pm->customer !== $stripeCustomerId) {
                // Keep it simple: detach & reattach to our customer (for test mode)
                $pm->detach();
                PaymentMethod::retrieve($pmId)->attach(['customer' => $stripeCustomerId]);
            }
            Customer::update($stripeCustomerId, [
                'invoice_settings' => ['default_payment_method' => $pmId],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Invalid payment method.'], 422);
        }

        // Pre-create a minimal local subscription row (pending)
        $startsAt = Carbon::now();
        $endsAt = $this->computeEnd($startsAt, (int) $plan->duration, $plan->duration_unit);

        $subscription = \App\Models\Subscription::create([
            'user_id' => $user->id,
            'user_email' => $user->email,
            'order_id' => strtoupper(uniqid('ORD_')),
            'plan_id' => $plan->id,
            'subscription_name' => $plan->subscription_name,
            'subscription_period' => "{$plan->duration} " . ucfirst($plan->duration_unit),
            'billing_cycle' => $plan->type, // 'one_time' | 'recurring'
            'subscription_start_date' => $startsAt->toDateString(),
            'subscription_end_date' => $endsAt->toDateString(),
            'total_amount' => $priceLocal,
            'currency' => $currency,
            'payment_method' => 'Stripe',
            'payment_status' => 'pending',
            'subscription_status' => 'incomplete',
            'auto_renew' => $autoRenew,
            'stripe_customer_id' => $stripeCustomerId,
            'is_first_payment' => true,
        ]);

        // 5) THE SIMPLE PART
        if ($plan->type === 'one_time') {
            // One-time: single PaymentIntent, confirm now
            if ($amountCents <= 0) {
                // free plan — mark active
                $subscription->update([
                    'payment_status' => 'succeeded',
                    'subscription_status' => 'active',
                ]);
                return response()->json([
                    'subscription_id' => $subscription->id,
                    'status' => 'active',
                    'billing_cycle' => 'one_time',
                    'region' => $regionCode,
                    'currency' => strtoupper($regionCurrency),
                    'fx_rate_used' => $fxRate,
                ]);
            }

            $pi = PaymentIntent::create([
                'amount' => $amountCents,
                'currency' => $currency,
                'customer' => $stripeCustomerId,
                'payment_method' => $pmId,
                'confirm' => true,
                'description' => "One-time payment for {$plan->subscription_name}",
                'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
            ], [
                // Idempotency to avoid double charges on retries
                'idempotency_key' => 'pi_' . $subscription->order_id,
            ]);

            $subscription->update([
                'stripe_payment_intent_id' => $pi->id,
                'transaction_id' => $pi->id,
                'last_payment_status' => $pi->status,
                'last_payment_at' => now(),
            ]);

            if ($pi->status === 'requires_action') {
                return response()->json([
                    'requires_action' => true,
                    'payment_intent_client_secret' => $pi->client_secret,
                    'message' => '3DS authentication required',
                    'region' => $regionCode,
                    'currency' => strtoupper($regionCurrency),
                    'fx_rate_used' => $fxRate,
                ], 200);
            }

            if ($pi->status !== 'succeeded' && $pi->status !== 'requires_capture') {
                return response()->json([
                    'message' => 'Payment incomplete',
                    'status' => $pi->status,
                    'region' => $regionCode,
                    'currency' => strtoupper($regionCurrency),
                    'fx_rate_used' => $fxRate,
                ], 402);
            }

            $subscription->update([
                'payment_status' => 'succeeded',
                'subscription_status' => 'active',
            ]);

            return response()->json([
                'subscription_id' => $subscription->id,
                'status' => 'active',
                'billing_cycle' => 'one_time',
                'region' => $regionCode,
                'currency' => strtoupper($regionCurrency),
                'fx_rate_used' => $fxRate,
            ]);
        }

        // Recurring: create Price once, then Subscription (payment happens on first invoice)
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
            'items' => [['price' => $price->id]],
            'default_payment_method' => $pmId,
            // 'payment_behavior'       => 'default_incomplete', // we’ll inspect the first invoice’s PI
            'payment_behavior' => 'error_if_incomplete',
            'expand' => ['latest_invoice.payment_intent'],
            'cancel_at' => $cancelAt,

            'payment_settings' => ['save_default_payment_method' => 'off'],
        ], [
            'idempotency_key' => 'sub_' . $subscription->order_id,
        ]);

        $pi = $stripeSub->latest_invoice->payment_intent ?? null;

        //     if ($pi) {
//     \Stripe\PaymentIntent::update($pi->id, [
//         'setup_future_usage' => null, // turn it off
//     ]);
// }

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
                'region' => $regionCode,
                'currency' => strtoupper($regionCurrency),
                'fx_rate_used' => $fxRate,
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
                'region' => $regionCode,
                'currency' => strtoupper($regionCurrency),
                'fx_rate_used' => $fxRate,
            ]);
        }

        // Otherwise let webhooks (invoice.payment_succeeded / payment_failed) finalize
        return response()->json([
            'subscription_id' => $subscription->id,
            'status' => 'incomplete',
            'message' => 'Awaiting payment confirmation',
            'region' => $regionCode,
            'currency' => strtoupper($regionCurrency),
            'fx_rate_used' => $fxRate,
        ], 202);
    }





    // public function cancel($id)
    // {
    //     $subscription = Subscription::find($id);
    //     if (!$subscription)
    //         return response()->json(['message' => 'Not found'], 404);

    //     if ($subscription->billing_cycle === 'recurring' && $subscription->stripe_subscription_id) {
    //         Stripe::setApiKey(config('services.stripe.secret'));
    //         StripeSubscription::update($subscription->stripe_subscription_id, [
    //             'cancel_at_period_end' => true,
    //         ]);
    //     }

    //     $subscription->update([
    //         'cancel_at_period_end' => true,
    //         'subscription_status' => 'cancel_scheduled',
    //     ]);

    //     return response()->json(['message' => 'Will cancel at period end']);
    // }
    public function cancelNow($id)
    {
        $user = Auth::guard('api')->user();

        $sub = \App\Models\Subscription::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$sub) {
            return response()->json(['message' => 'Subscription not found'], 404);
        }

        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        // Case 1: RECURRING with Stripe subscription -> cancel immediately on Stripe
        if ($sub->billing_cycle === 'recurring' && $sub->stripe_subscription_id) {
            $stripeSub = \Stripe\Subscription::retrieve($sub->stripe_subscription_id);
            $canceled = $stripeSub->cancel(); // instance method

            $today = now()->toDateString();
            $sub->update([
                'auto_renew' => false,
                'cancel_at_period_end' => false,
                'subscription_end_date' => $today,
                'subscription_status' => 'canceled',
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Subscription canceled immediately',
                'data' => [
                    'stripe_status' => $canceled->status,
                    'ended_on' => $today,
                ],
            ], 200);
        }

        // Case 2: ONE-TIME
        if ($sub->billing_cycle === 'one_time') {
            // Optional refund if paid via Stripe and within your refund window
            $refunded = false;
            $refundId = null;

            // If you store PI id on the subscription (you do in purchase flow as 'stripe_payment_intent_id')
            if (!empty($sub->stripe_payment_intent_id)) {
                try {
                    // Example policy: refund only if within 24h and amount > 0
                    $withinWindow = now()->diffInHours($sub->created_at) <= 24;
                    $paidAmount = (float) $sub->total_amount;

                    if ($withinWindow && $paidAmount > 0) {
                        $refund = \Stripe\Refund::create([
                            'payment_intent' => $sub->stripe_payment_intent_id,
                            // 'amount' =>  /* optional partial refund in cents */,
                        ]);
                        $refunded = true;
                        $refundId = $refund->id;
                    }
                } catch (\Throwable $e) {

                }
            }

            // Revoke access locally (no auto-renew concept)
            $today = now()->toDateString();
            $sub->update([
                'auto_renew' => false,
                'cancel_at_period_end' => false,
                'subscription_end_date' => $today,
                'subscription_status' => 'canceled',
                'payment_status' => $refunded ? 'refunded' : $sub->payment_status,
                'last_payment_status' => $refunded ? 'refunded' : $sub->last_payment_status,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'One-time access revoked',
                'data' => [
                    'action' => 'one_time_access_revoked',
                    'refunded' => $refunded,
                    'refund_id' => $refundId,
                    'ended_on' => $today,
                ],
            ], 200);

        }

        // Case 3: Unknown/misaligned state
        return response()->json([
            'message' => 'Unsupported subscription state',
            'billing_cycle' => $sub->billing_cycle,
        ], 422);
    }



    private function computeEnd(Carbon $start, int $duration, string $unit): Carbon
    {
        return match (strtolower($unit)) {
            'day', 'days' => $start->copy()->addDays($duration),
            'week', 'weeks' => $start->copy()->addWeeks($duration),
            'month', 'months' => $start->copy()->addMonths($duration),
            'year', 'years' => $start->copy()->addYears($duration),
            default => $start->copy()->addMonths($duration),
        };
    }

    private function mapInterval(string $unit): string
    {
        return match (strtolower($unit)) {
            'day', 'days' => 'day',
            'week', 'weeks' => 'week',
            'month', 'months' => 'month',
            'year', 'years' => 'year',
            default => 'month',
        };
    }

    public function confirm(Request $req)
    {
        $data = $req->validate([
            'subscription_id' => ['required', 'integer', 'exists:subscriptions,id'],
        ]);

        $sub = \App\Models\Subscription::findOrFail($data['subscription_id']);

        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        $invoice = null;
        $piId = null;

        // 1) Try the invoice saved on our row, but EXPAND payment_intent so we’re sure
        if (!empty($sub->stripe_invoice_id)) {
            $invoice = \Stripe\Invoice::retrieve([
                'id' => $sub->stripe_invoice_id,
                'expand' => ['payment_intent'],
            ]);

            if (isset($invoice->payment_intent)) {
                // Could be object or string depending on expansion
                $piId = is_object($invoice->payment_intent)
                    ? $invoice->payment_intent->id
                    : $invoice->payment_intent;
            }
        }

        // 2) Fallback: pull the subscription and expand latest_invoice.payment_intent
        if (!$piId && !empty($sub->stripe_subscription_id)) {
            $stripeSub = \Stripe\Subscription::retrieve([
                'id' => $sub->stripe_subscription_id,
                'expand' => ['latest_invoice.payment_intent'],
            ]);

            if (isset($stripeSub->latest_invoice)) {
                $invoice = $stripeSub->latest_invoice;

                if (isset($invoice->payment_intent)) {
                    $piId = is_object($invoice->payment_intent)
                        ? $invoice->payment_intent->id
                        : $invoice->payment_intent;
                }
            }
        }

        // 3) Still no PI? Then the invoice likely has no charge (amount 0 / send_invoice)
        if (!$piId) {
            return response()->json([
                'status' => 'no_payment_intent',
                'message' => 'No PaymentIntent found on the invoice.',
                'invoice_id' => $invoice->id ?? $sub->stripe_invoice_id,
                'invoice_status' => $invoice->status ?? null,
                'invoice_collection_method' => $invoice->collection_method ?? null,
                'amount_due' => $invoice->amount_due ?? null,
            ], 422);
        }

        // 4) Retrieve & confirm if needed (instance methods, not static)
        $pi = \Stripe\PaymentIntent::retrieve($piId);

        // Track current status
        $sub->update([
            'stripe_payment_intent_id' => $pi->id, // ensure we persist it now
            'last_payment_status' => $pi->status,
            'last_payment_at' => now(),
        ]);

        if (in_array($pi->status, ['requires_action', 'requires_confirmation'])) {
            // Relies on customer's default_payment_method you set during purchase()
            $pi = $pi->confirm();

            $sub->update([
                'last_payment_status' => $pi->status,
                'last_payment_at' => now(),
            ]);
        }

        if ($pi->status === 'succeeded') {
            $sub->update([
                'payment_status' => 'succeeded',
                'subscription_status' => 'active',
            ]);

            return response()->json(['status' => 'active'], 200);
        }

        if ($pi->status === 'requires_payment_method') {
            return response()->json([
                'status' => 'requires_payment_method',
                'message' => 'Payment failed/declined. Attach a new payment method and retry.',
            ], 402);
        }

        // Otherwise inform current state (may be processing, requires_action, etc.)
        return response()->json([
            'status' => $pi->status,
            'client_secret' => $pi->client_secret ?? null,
        ], 202);
    }


    // 1) Turn OFF auto-renew (keeps access until current period end)
    public function cancelAutoRenew(Request $req, $id)
    {
        $user = Auth::guard('api')->user();

        $sub = \App\Models\Subscription::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$sub) {
            return response()->json(['message' => 'Subscription not found'], 404);
        }

        // Not applicable for one-time purchases
        if ($sub->billing_cycle !== 'recurring') {
            return response()->json([
                'message' => 'Auto-renew is not applicable to one-time purchases',
                'billing_cycle' => $sub->billing_cycle,
            ], 409);
        }

        if (!$sub->stripe_subscription_id) {
            return response()->json(['message' => 'Stripe subscription id missing'], 422);
        }

        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        try {
            // Stop renewing after the current period
            // $stripeSub = \Stripe\Subscription::update($sub->stripe_subscription_id, [
            //     'cancel_at_period_end' => true,
            // ]);

            // // Align local end date with Stripe
            // $periodEnd = \Carbon\Carbon::createFromTimestamp($stripeSub->current_period_end)->toDateString();

            $stripeSub = \Stripe\Subscription::update($sub->stripe_subscription_id, [
    'cancel_at_period_end' => true,
]);

// Re-fetch to ensure all timestamps are populated
$stripeSub = \Stripe\Subscription::retrieve($sub->stripe_subscription_id);

// Pick a safe timestamp (some statuses may not set current_period_end on update)
$periodEndTs = $stripeSub->current_period_end
    ?? $stripeSub->cancel_at
    ?? $stripeSub->trial_end
    ?? null;

$periodEnd = $periodEndTs
    ? \Carbon\Carbon::createFromTimestamp($periodEndTs)->toDateString()
    : ($sub->subscription_end_date ?: now()->toDateString());

            // Still active until period end
            // $sub->update([
            //     'auto_renew' => false,
            //     'subscription_end_date' => $periodEnd,
            //     'subscription_status' => $sub->subscription_status === 'active'
            //         ? 'cancels_at_period_end'
            //         : $sub->subscription_status,
            //     'cancel_at_period_end' => true,
            // ]);
            $sub->update([
    'auto_renew' => false,
    'subscription_end_date' => $periodEnd,
    'cancel_at_period_end' => true,
]);


            return response()->json([
                'status' => true,
                'message' => 'Auto-renew turned off; will cancel at period end',
                'data' => [
                    'action' => 'auto_renew_off',
                    'stripe_status' => $stripeSub->status,
                    'cancels_at_period_end' => $stripeSub->cancel_at_period_end,
                    'current_period_end' => $periodEnd,
                    'subscription' => [
                        'id' => $sub->id,
                        'auto_renew' => (bool) $sub->auto_renew,
                        'end_date' => $sub->subscription_end_date,
                        'status' => $sub->subscription_status,
                    ],
                ],
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Unable to cancel at period end',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    // 2) Turn ON auto-renew again (only before the period actually ends)
    public function reactivateAutoRenew(Request $req, $id)
    {
        $user = Auth::guard('api')->user();

        $sub = \App\Models\Subscription::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$sub) {
            return response()->json(['message' => 'Subscription not found'], 404);
        }

        // Not applicable for one-time purchases
        if ($sub->billing_cycle !== 'recurring') {
            return response()->json([
                'message' => 'Auto-renew is not applicable to one-time purchases',
                'billing_cycle' => $sub->billing_cycle,
            ], 409);
        }

        // If it already fully ended/canceled, user must purchase again
        if (in_array($sub->subscription_status, ['canceled', 'expired'], true)) {
            return response()->json([
                'message' => 'Subscription already ended. Please purchase again to reactivate.',
            ], 409);
        }

        if (!$sub->stripe_subscription_id) {
            return response()->json(['message' => 'Stripe subscription id missing'], 422);
        }

        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        try {
            // Remove the period-end cancel flag so Stripe will keep renewing
            $stripeSub = \Stripe\Subscription::update($sub->stripe_subscription_id, [
                'cancel_at_period_end' => false,
            ]);

            // Keep local dates in sync with Stripe
            $periodEnd = \Carbon\Carbon::createFromTimestamp($stripeSub->current_period_end)->toDateString();

            $sub->update([
                'auto_renew' => true,
                'subscription_end_date' => $periodEnd,
                'subscription_status' => $sub->subscription_status === 'cancels_at_period_end'
                    ? 'active'
                    : $sub->subscription_status,
                'cancel_at_period_end' => false,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Auto-renew reactivated',
                'data' => [
                    'action' => 'auto_renew_on',
                    'stripe_status' => $stripeSub->status,
                    'cancels_at_period_end' => $stripeSub->cancel_at_period_end,
                    'current_period_end' => $periodEnd,
                    'subscription' => [
                        'id' => $sub->id,
                        'auto_renew' => (bool) $sub->auto_renew,
                        'end_date' => $sub->subscription_end_date,
                        'status' => $sub->subscription_status,
                    ],
                ],
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Unable to reactivate auto-renew',
                'error' => $e->getMessage(),
            ], 422);
        }
    }


}
