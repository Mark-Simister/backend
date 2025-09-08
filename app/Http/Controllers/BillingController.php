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
    $to   = strtoupper($to);

    if ($from === $to) return 1.0;

    $cacheKey = "fx_{$from}_{$to}";
    // Try cache FIRST (stale-while-revalidate semantics)
    if (($cached = Cache::get($cacheKey)) && is_numeric($cached) && $cached > 0) {
        return (float)$cached;
    }

    // Try fresh fetch; if success, save and return; else, try reverse cache
    $rate = $this->fetchFromYahoo($from, $to)
        ?? $this->fetchFromExchangerateHost($from, $to)
        ?? $this->fetchFromEcb($from, $to);

    if (is_numeric($rate) && $rate > 0) {
        Cache::put($cacheKey, $rate, now()->addMinutes(15));
        return (float)$rate;
    }

    // Try reverse cached pair as a last-ditch (invert)
    $revKey = "fx_{$to}_{$from}";
    if (($rev = Cache::get($revKey)) && is_numeric($rev) && $rev > 0) {
        $inv = 1.0 / (float)$rev;
        Cache::put($cacheKey, $inv, now()->addMinutes(15));
        return $inv;
    }

    // Final fallback: 1.0 (you can choose to bail out instead)
    return 1.0;
}

private function fetchFromYahoo(string $from, string $to): ?float
{
    try {
        $pair = $from.$to.'=X';
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
        if (!$resp->successful()) return null;

        $json  = $resp->json();
        $price = $json['quoteResponse']['result'][0]['regularMarketPrice'] ?? null;
        if (is_numeric($price) && $price > 0) return (float)$price;

        // Reverse pair & invert
        $rev = Http::timeout(8)->withHeaders($headers)
            ->get("https://query1.finance.yahoo.com/v7/finance/quote?symbols={$to}{$from}=X");
        if (!$rev->successful()) return null;
        $rjson = $rev->json();
        $r     = $rjson['quoteResponse']['result'][0]['regularMarketPrice'] ?? null;
        return (is_numeric($r) && $r > 0) ? (1.0 / (float)$r) : null;
    } catch (\Throwable $e) {
        return null;
    }
}

private function fetchFromExchangerateHost(string $from, string $to): ?float
{
    try {
        $resp = Http::timeout(8)->get("https://api.exchangerate.host/convert?from={$from}&to={$to}&amount=1");
        if (!$resp->successful()) return null;
        $rate = $resp->json('result');
        return (is_numeric($rate) && $rate > 0) ? (float)$rate : null;
    } catch (\Throwable $e) {
        return null;
    }
}

private function fetchFromEcb(string $from, string $to): ?float
{
    try {
        $resp = Http::timeout(8)->get('https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml');
        if (!$resp->successful()) return null;
        $xml = simplexml_load_string($resp->body());
        if (!$xml) return null;

        $cube = $xml->xpath('//gesmes:Envelope/*[local-name()="Cube"]/*[local-name()="Cube"]/*[local-name()="Cube"]');
        $eurMap = ['EUR' => 1.0];
        foreach ($cube as $c) {
            $attr = $c->attributes();
            if (isset($attr['currency'], $attr['rate'])) {
                $eurMap[(string)$attr['currency']] = (float)$attr['rate'];
            }
        }

        if ($from === 'EUR' && isset($eurMap[$to])) return (float)$eurMap[$to];
        if ($to   === 'EUR' && isset($eurMap[$from])) return 1.0 / (float)$eurMap[$from];
        if (isset($eurMap[$from], $eurMap[$to])) return $eurMap[$to] / $eurMap[$from];
        return null;
    } catch (\Throwable $e) {
        return null;
    }
}

    
//     public function purchase(Request $req)
// {
//     $validated = $req->validate([
//         'plan_id' => ['required','integer','exists:subscription_listing,id'],
//         'payment_method' => ['required','string'],   // PaymentMethod ID from Stripe Elements
//         'auto_renew' => ['nullable','boolean'],
//     ]);
    

//     // Get the currently authenticated user from the token
//     $user = Auth::guard('api')->user();
//     $plan = DB::table('subscription_listing')->where('id',$validated['plan_id'])->first();
//     // dd($user,$plan);
//     $amountCents = (int) round(floatval($plan->price) * 100);
//     $currency = config('services.stripe.currency', 'usd');
//     $autoRenew = array_key_exists('auto_renew', $validated)
//         ? (bool)$validated['auto_renew']
//         : ($plan->type === 'recurring');

//     Stripe::setApiKey(config('services.stripe.secret'));

//     // ensure/reuse stripe customer (create WITHOUT payment_method)
//     $existingCustomerId = Subscription::where('user_id',$user->id)
//         ->whereNotNull('stripe_customer_id')
//         ->value('stripe_customer_id');

//     if (!$existingCustomerId) {
//         $customer = Customer::create([
//             'email' => $user->email,
//             'name'  => $user->name,
//         ]);
//         $stripeCustomerId = $customer->id;
//     } else {
//         $stripeCustomerId = $existingCustomerId;
//     }

//     // ATTACH THE PM + SET AS DEFAULT (critical)
//     $pmId = $validated['payment_method'];
//     try {
//         $pm = PaymentMethod::retrieve($pmId);
//     } catch (\Exception $e) {
//         return response()->json([
//             'message' => "Invalid payment method id or mode mismatch: {$pmId}"
//         ], 422);
//     }

//     $pm = PaymentMethod::retrieve($pmId);

//     if (empty($pm->customer)) {
//         $pm->attach(['customer' => $stripeCustomerId]);
//     } elseif ($pm->customer !== $stripeCustomerId) {
//         // TEST-ONLY: in live, create a new pm_ for this user instead of reusing
//         $pm->detach();
//         $pm = PaymentMethod::retrieve($pmId); // optional re-fetch
//         $pm->attach(['customer' => $stripeCustomerId]);
//     }


//     Customer::update($stripeCustomerId, [
//         'invoice_settings' => ['default_payment_method' => $pmId],
//     ]);

//     // create local row (pending)
//     $startsAt = Carbon::now();
//     $endsAt   = $this->computeEnd($startsAt, (int)$plan->duration, $plan->duration_unit);

//     $subscription = Subscription::create([
//         'user_id' => $user->id,
//         'user_email' => $user->email,
//         'order_id' => strtoupper(uniqid('ORD_')),
//         'plan_id' => $plan->id,
//         'subscription_name' => $plan->subscription_name,
//         'subscription_period' => "{$plan->duration} " . ucfirst($plan->duration_unit),
//         'billing_cycle' => $plan->type,
//         'subscription_start_date' => $startsAt->toDateString(),
//         'subscription_end_date' => $endsAt->toDateString(),
//         'total_amount' => $plan->price,
//         'currency' => $currency,
//         'payment_method' => 'Stripe',
//         'payment_status' => 'pending',
//         'subscription_status' => 'incomplete',
//         'auto_renew' => $autoRenew,
//         'stripe_customer_id' => $stripeCustomerId,
//         'is_first_payment' => true,
//     ]);

//     if ($plan->type === 'one_time') {
//         if ($amountCents > 0) {
//             $pi = PaymentIntent::create([
//                 'amount' => $amountCents,
//                 'currency' => $currency,
//                 'customer' => $stripeCustomerId,
//                 'payment_method' => $pmId, // now attached
//                 'confirm' => true,
//                 'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
//                 'description' => "One-time purchase: {$plan->subscription_name}",
//             ]);

//             $subscription->update([
//                 'stripe_payment_intent_id' => $pi->id,
//                 'transaction_id' => $pi->id,
//                 'last_payment_status' => $pi->status,
//                 'last_payment_at' => now(),
//             ]);

//             if (!in_array($pi->status, ['succeeded','requires_capture'])) {
//                 return response()->json([
//                     'requires_action' => $pi->status === 'requires_action',
//                     'payment_intent_client_secret' => $pi->client_secret ?? null,
//                     'message' => 'Payment incomplete',
//                 ], 402);
//             }
//         }

//         // free or succeeded
//         $subscription->update([
//             'payment_status' => 'succeeded',
//             'subscription_status' => 'active',
//         ]);

//         return response()->json([
//             'subscription_id' => $subscription->id,
//             'status' => 'active',
//             'billing_cycle' => 'one_time',
//             'starts_at' => $subscription->subscription_start_date,
//             'ends_at' => $subscription->subscription_end_date,
//         ]);
//     }

//     // recurring
//     $interval = $this->mapInterval($plan->duration_unit); // day|week|month|year
//     $intervalCount = (int) $plan->duration;

//     $price = Price::create([
//         'unit_amount' => $amountCents,
//         'currency' => $currency,
//         'recurring' => [
//             'interval' => $interval,
//             'interval_count' => $intervalCount,
//         ],
//         'product_data' => ['name' => $plan->subscription_name],
//     ]);

//     $cancelAt = $autoRenew ? null : $endsAt->timestamp;

//     $stripeSub = StripeSubscription::create([
//         'customer' => $stripeCustomerId,
//         'items' => [[ 'price' => $price->id ]],
//         'default_payment_method' => $pmId,      
//         'payment_behavior' => 'default_incomplete',
//         'expand' => ['latest_invoice.payment_intent'],
//         'cancel_at' => $cancelAt,
//     ]);

//     $pi = $stripeSub->latest_invoice->payment_intent ?? null;

//     $subscription->update([
//         'stripe_subscription_id' => $stripeSub->id,
//         'stripe_price_id' => $price->id,
//         'stripe_invoice_id' => $stripeSub->latest_invoice->id ?? null,
//         'transaction_id' => $stripeSub->id,
//     ]);

//     if ($pi && $pi->status === 'requires_action') {
//         $subscription->update([
//             'last_payment_status' => $pi->status,
//             'last_payment_at' => now(),
//         ]);
//         return response()->json([
//             'requires_action' => true,
//             'payment_intent_client_secret' => $pi->client_secret,
//             'stripe_subscription_id' => $stripeSub->id,
//             'message' => '3DS authentication required',
//         ], 200);
//     }

//     if ($pi && $pi->status === 'succeeded') {
//         $subscription->update([
//             'payment_status' => 'succeeded',
//             'subscription_status' => 'active',
//             'last_payment_status' => 'succeeded',
//             'last_payment_at' => now(),
//         ]);

//         return response()->json([
//             'subscription_id' => $subscription->id,
//             'status' => 'active',
//             'billing_cycle' => 'recurring',
//             'auto_renew' => $autoRenew,
//             'starts_at' => $subscription->subscription_start_date,
//             'ends_at' => $subscription->subscription_end_date,
//         ]);
//     }

//     // otherwise, wait for webhook
//     return response()->json([
//         'subscription_id' => $subscription->id,
//         'status' => 'incomplete',
//         'message' => 'Awaiting payment confirmation',
//     ], 202);
// }

// public function purchase(Request $req)
// {
//     $validated = $req->validate([
//         'plan_id' => ['required','integer','exists:subscription_listing,id'],
//         'payment_method' => ['required','string'],   
//         'auto_renew' => ['nullable','boolean'],
//         'region' => ['nullable','string'],
//     ]);
    
    
//     $inputRegion   = strtoupper((string)($validated['region'] ?? $req->input('region', '')));
//     $allowedRegions = ['AU','CA','UK','US','GLOBAL'];
//     $regionCode    = in_array($inputRegion, $allowedRegions, true) ? $inputRegion : 'GLOBAL';
//     $regionCurrency = match ($regionCode) {
//         'AU' => 'AUD',
//         'CA' => 'CAD',
//         'UK' => 'GBP',
//         'US' => 'USD',
//         'GLOBAL' => 'INR',
//         default => 'USD',
//     };
//     $baseCurrency = 'USD';
//     $fxRate = 1.0;
//     if ($regionCurrency !== $baseCurrency) {
//         try {
//             $url = "https://api.exchangerate.host/convert?from={$baseCurrency}&to={$regionCurrency}&amount=1";
//             $resp = Http::timeout(8)->get($url);
//             if ($resp->successful()) {
//                 $json = $resp->json();
//                 $rate = $json['result'] ?? null;
//                 if (is_numeric($rate) && $rate > 0) {
//                     $fxRate = (float) $rate;
//                 }
//             }
//         } catch (\Throwable $e) {
            
//         }
//     }
//     $user = Auth::guard('api')->user();
//     $plan = DB::table('subscription_listing')->where('id',$validated['plan_id'])->first();

//     $priceLocal = round(floatval($plan->price) * $fxRate, 2);
//     $zeroDecimal = ['JPY'];
//     $currency = strtolower($regionCurrency); // Stripe expects lowercase ISO
//     $amountCents = in_array(strtoupper($regionCurrency), $zeroDecimal, true)
//         ? (int) round($priceLocal)
//         : (int) round($priceLocal * 100);

//     $autoRenew = array_key_exists('auto_renew', $validated)
//         ? (bool)$validated['auto_renew']
//         : ($plan->type === 'recurring');
    
//     dd($user,$plan,$priceLocal,$zeroDecimal,$currency,$amountCents);
    

//     Stripe::setApiKey(config('services.stripe.secret'));

//     $existingCustomerId = Subscription::where('user_id',$user->id)
//         ->whereNotNull('stripe_customer_id')
//         ->value('stripe_customer_id');

//     if (!$existingCustomerId) {
//         $customer = Customer::create([
//             'email' => $user->email,
//             'name'  => $user->name,
//         ]);
//         $stripeCustomerId = $customer->id;
//     } else {
//         $stripeCustomerId = $existingCustomerId;
//     }

//     // ATTACH THE PM + SET AS DEFAULT (critical)
//     $pmId = $validated['payment_method'];
//     try {
//         $pm = PaymentMethod::retrieve($pmId);
//     } catch (\Exception $e) {
//         return response()->json([
//             'message' => "Invalid payment method id or mode mismatch: {$pmId}"
//         ], 422);
//     }

//     $pm = PaymentMethod::retrieve($pmId);

//     if (empty($pm->customer)) {
//         $pm->attach(['customer' => $stripeCustomerId]);
//     } elseif ($pm->customer !== $stripeCustomerId) {
//         // TEST-ONLY: in live, create a new pm_ for this user instead of reusing
//         $pm->detach();
//         $pm = PaymentMethod::retrieve($pmId); // optional re-fetch
//         $pm->attach(['customer' => $stripeCustomerId]);
//     }


//     Customer::update($stripeCustomerId, [
//         'invoice_settings' => ['default_payment_method' => $pmId],
//     ]);

//     // create local row (pending)
//     $startsAt = Carbon::now();
//     $endsAt   = $this->computeEnd($startsAt, (int)$plan->duration, $plan->duration_unit);

//     $subscription = Subscription::create([
//         'user_id' => $user->id,
//         'user_email' => $user->email,
//         'order_id' => strtoupper(uniqid('ORD_')),
//         'plan_id' => $plan->id,
//         'subscription_name' => $plan->subscription_name,
//         'subscription_period' => "{$plan->duration} " . ucfirst($plan->duration_unit),
//         'billing_cycle' => $plan->type,
//         'subscription_start_date' => $startsAt->toDateString(),
//         'subscription_end_date' => $endsAt->toDateString(),
//         'total_amount' => $priceLocal,
//         'currency' => $currency,
//         'payment_method' => 'Stripe',
//         'payment_status' => 'pending',
//         'subscription_status' => 'incomplete',
//         'auto_renew' => $autoRenew,
//         'stripe_customer_id' => $stripeCustomerId,
//         'is_first_payment' => true,
//     ]);

//     if ($plan->type === 'one_time') {
//         if ($amountCents > 0) {
//             $pi = PaymentIntent::create([
//                 'amount' => $amountCents,
//                 'currency' => $currency,
//                 'customer' => $stripeCustomerId,
//                 'payment_method' => $pmId, // now attached
//                 'confirm' => true,
//                 'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
//                 'description' => "One-time purchase: {$plan->subscription_name}",
//             ]);

//             $subscription->update([
//                 'stripe_payment_intent_id' => $pi->id,
//                 'transaction_id' => $pi->id,
//                 'last_payment_status' => $pi->status,
//                 'last_payment_at' => now(),
//             ]);

//             if (!in_array($pi->status, ['succeeded','requires_capture'])) {
//                 return response()->json([
//                     'requires_action' => $pi->status === 'requires_action',
//                     'payment_intent_client_secret' => $pi->client_secret ?? null,
//                     'message' => 'Payment incomplete',
//                 ], 402);
//             }
//         }

//         // free or succeeded
//         $subscription->update([
//             'payment_status' => 'succeeded',
//             'subscription_status' => 'active',
//         ]);

//         return response()->json([
//             'subscription_id' => $subscription->id,
//             'status' => 'active',
//             'billing_cycle' => 'one_time',
//             'starts_at' => $subscription->subscription_start_date,
//             'ends_at' => $subscription->subscription_end_date,
//             // ADDED: helpful echoes
//             'region' => $regionCode,
//             'currency' => strtoupper($regionCurrency),
//             'fx_rate_used' => $fxRate,
//         ]);
//     }

//     // recurring
//     $interval = $this->mapInterval($plan->duration_unit); // day|week|month|year
//     $intervalCount = (int) $plan->duration;

//     $price = Price::create([
//         'unit_amount' => $amountCents,
//         'currency' => $currency,
//         'recurring' => [
//             'interval' => $interval,
//             'interval_count' => $intervalCount,
//         ],
//         'product_data' => ['name' => $plan->subscription_name],
//     ]);

//     $cancelAt = $autoRenew ? null : $endsAt->timestamp;

//     $stripeSub = StripeSubscription::create([
//         'customer' => $stripeCustomerId,
//         'items' => [[ 'price' => $price->id ]],
//         'default_payment_method' => $pmId,      
//         'payment_behavior' => 'default_incomplete',
//         'expand' => ['latest_invoice.payment_intent'],
//         'cancel_at' => $cancelAt,
//     ]);

//     $pi = $stripeSub->latest_invoice->payment_intent ?? null;

//     $subscription->update([
//         'stripe_subscription_id' => $stripeSub->id,
//         'stripe_price_id' => $price->id,
//         'stripe_invoice_id' => $stripeSub->latest_invoice->id ?? null,
//         'transaction_id' => $stripeSub->id,
//     ]);

//     if ($pi && $pi->status === 'requires_action') {
//         $subscription->update([
//             'last_payment_status' => $pi->status,
//             'last_payment_at' => now(),
//         ]);
//         return response()->json([
//             'requires_action' => true,
//             'payment_intent_client_secret' => $pi->client_secret,
//             'stripe_subscription_id' => $stripeSub->id,
//             'message' => '3DS authentication required',
//             // ADDED: helpful echoes
//             'region' => $regionCode,
//             'currency' => strtoupper($regionCurrency),
//             'fx_rate_used' => $fxRate,
//         ], 200);
//     }

//     if ($pi && $pi->status === 'succeeded') {
//         $subscription->update([
//             'payment_status' => 'succeeded',
//             'subscription_status' => 'active',
//             'last_payment_status' => 'succeeded',
//             'last_payment_at' => now(),
//         ]);

//         return response()->json([
//             'subscription_id' => $subscription->id,
//             'status' => 'active',
//             'billing_cycle' => 'recurring',
//             'auto_renew' => $autoRenew,
//             'starts_at' => $subscription->subscription_start_date,
//             'ends_at' => $subscription->subscription_end_date,
//             // ADDED: helpful echoes
//             'region' => $regionCode,
//             'currency' => strtoupper($regionCurrency),
//             'fx_rate_used' => $fxRate,
//         ]);
//     }

//     // otherwise, wait for webhook
//     return response()->json([
//         'subscription_id' => $subscription->id,
//         'status' => 'incomplete',
//         'message' => 'Awaiting payment confirmation',
//         // ADDED: helpful echoes
//         'region' => $regionCode,
//         'currency' => strtoupper($regionCurrency),
//         'fx_rate_used' => $fxRate,
//     ], 202);
// }


public function purchase(Request $req)
{
    try {
        // Validate input parameters
        $validated = $req->validate([
            'plan_id' => ['required', 'integer', 'exists:subscription_listing,id'],
            'payment_method' => ['required', 'string'],
            'auto_renew' => ['nullable', 'boolean'],
            'region' => ['nullable', 'string'],
        ]);

        // Handle region and currency
        $inputRegion = strtoupper((string)($validated['region'] ?? $req->input('region', '')));
        $allowedRegions = ['AU', 'CA', 'UK', 'US', 'GLOBAL'];
        $regionCode = in_array($inputRegion, $allowedRegions, true) ? $inputRegion : 'GLOBAL';
        $regionCurrency = match ($regionCode) {
            'AU' => 'AUD',
            'CA' => 'CAD',
            'UK' => 'GBP',
            'US' => 'USD',
            'GLOBAL' => 'INR',
            default => 'USD',
        };
        $baseCurrency = 'USD';
        $fxRate = $this->getRealtimeRate($baseCurrency, $regionCurrency);

        // Retrieve user and subscription plan details
        $user = Auth::guard('api')->user();
        $plan = DB::table('subscription_listing')->where('id', $validated['plan_id'])->first();

        // Calculate subscription price
        $priceLocal = round(floatval($plan->price) * $fxRate, 2);
        $currency = strtolower($regionCurrency); 
        $amountCents = in_array(strtoupper($regionCurrency), ['JPY'], true)
            ? (int) round($priceLocal)
            : (int) round($priceLocal * 100);

        // Determine if auto-renew is enabled
        $autoRenew = array_key_exists('auto_renew', $validated)
            ? (bool)$validated['auto_renew']
            : ($plan->type === 'recurring');

        // Set Stripe API key
        Stripe::setApiKey(config('services.stripe.secret'));

        // Retrieve or create Stripe customer
        $existingCustomerId = Subscription::where('user_id', $user->id)
            ->whereNotNull('stripe_customer_id')
            ->value('stripe_customer_id');

        if (!$existingCustomerId) {
            $customer = Customer::create([
                'email' => $user->email,
                'name'  => $user->name,
            ]);
            $stripeCustomerId = $customer->id;
        } else {
            $stripeCustomerId = $existingCustomerId;
        }

        // Attach payment method to customer
        $pmId = $validated['payment_method'];
        $pm = PaymentMethod::retrieve($pmId);
        if (empty($pm->customer)) {
            $pm->attach(['customer' => $stripeCustomerId]);
        } elseif ($pm->customer !== $stripeCustomerId) {
            $pm->detach();
            $pm = PaymentMethod::retrieve($pmId);
            $pm->attach(['customer' => $stripeCustomerId]);
        }

        Customer::update($stripeCustomerId, [
            'invoice_settings' => ['default_payment_method' => $pmId],
        ]);

        // Create subscription record (pending)
        $startsAt = Carbon::now();
        $endsAt = $this->computeEnd($startsAt, (int)$plan->duration, $plan->duration_unit);

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
            'total_amount' => $priceLocal,
            'currency' => $currency,
            'payment_method' => 'Stripe',
            'payment_status' => 'pending',
            'subscription_status' => 'incomplete',
            'auto_renew' => $autoRenew,
            'stripe_customer_id' => $stripeCustomerId,
            'is_first_payment' => true,
        ]);

        // Create recurring price
        $interval = $this->mapInterval($plan->duration_unit);
        $intervalCount = (int)$plan->duration;

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

        // Create the Stripe subscription
        $stripeSub = StripeSubscription::create([
            'customer' => $stripeCustomerId,
            'items' => [['price' => $price->id]],
            'default_payment_method' => $pmId,
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

        // Confirm the payment interactively (on-session)
        if ($pi && $pi->status === 'requires_action') {
            try {
                $pi->confirm(['payment_method' => $pmId]);  // Confirm payment interactively
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Payment failed during confirmation.',
                    'message' => $e->getMessage(),
                ], 500);
            }

            // After confirmation, check if the payment succeeded
            if ($pi->status === 'succeeded') {
                $subscription->update([
                    'payment_status' => 'succeeded',
                    'subscription_status' => 'active',
                    'last_payment_status' => 'succeeded',
                    'last_payment_at' => now(),
                ]);
            }
        }

        // If the payment has succeeded
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
                'region' => $regionCode,
                'currency' => strtoupper($regionCurrency),
                'fx_rate_used' => $fxRate,
            ]);
        }

        // Otherwise, wait for webhook
        return response()->json([
            'subscription_id' => $subscription->id,
            'status' => 'incomplete',
            'message' => 'Awaiting payment confirmation',
            'region' => $regionCode,
            'currency' => strtoupper($regionCurrency),
            'fx_rate_used' => $fxRate,
        ], 202);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        return response()->json([
            'error' => 'Stripe API error.',
            'message' => $e->getMessage(),
        ], 500);
    }
}










    
    public function cancel($id)
    {
        $subscription = Subscription::find($id);
        if (!$subscription) return response()->json(['message' => 'Not found'], 404);

        if ($subscription->billing_cycle === 'recurring' && $subscription->stripe_subscription_id) {
            Stripe::setApiKey(config('services.stripe.secret'));
            StripeSubscription::update($subscription->stripe_subscription_id, [
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

    public function confirm(Request $req)
{
    $data = $req->validate([
        'subscription_id' => ['required','integer','exists:subscriptions,id'],
    ]);

    $sub = \App\Models\Subscription::findOrFail($data['subscription_id']);

    \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

    $invoice = null;
    $piId    = null;

    // 1) Try the invoice saved on our row, but EXPAND payment_intent so we’re sure
    if (!empty($sub->stripe_invoice_id)) {
        $invoice = \Stripe\Invoice::retrieve([
            'id'     => $sub->stripe_invoice_id,
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
            'id'     => $sub->stripe_subscription_id,
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
            'status'                    => 'no_payment_intent',
            'message'                   => 'No PaymentIntent found on the invoice.',
            'invoice_id'                => $invoice->id ?? $sub->stripe_invoice_id,
            'invoice_status'            => $invoice->status ?? null,
            'invoice_collection_method' => $invoice->collection_method ?? null,
            'amount_due'                => $invoice->amount_due ?? null,
        ], 422);
    }

    // 4) Retrieve & confirm if needed (instance methods, not static)
    $pi = \Stripe\PaymentIntent::retrieve($piId);

    // Track current status
    $sub->update([
        'stripe_payment_intent_id' => $pi->id, // ensure we persist it now
        'last_payment_status'      => $pi->status,
        'last_payment_at'          => now(),
    ]);

    if (in_array($pi->status, ['requires_action','requires_confirmation'])) {
        // Relies on customer's default_payment_method you set during purchase()
        $pi = $pi->confirm();

        $sub->update([
            'last_payment_status' => $pi->status,
            'last_payment_at'     => now(),
        ]);
    }

    if ($pi->status === 'succeeded') {
        $sub->update([
            'payment_status'      => 'succeeded',
            'subscription_status' => 'active',
        ]);

        return response()->json(['status' => 'active'], 200);
    }

    if ($pi->status === 'requires_payment_method') {
        return response()->json([
            'status'  => 'requires_payment_method',
            'message' => 'Payment failed/declined. Attach a new payment method and retry.',
        ], 402);
    }

    // Otherwise inform current state (may be processing, requires_action, etc.)
    return response()->json([
        'status'         => $pi->status,
        'client_secret'  => $pi->client_secret ?? null,
    ], 202);
}


}
