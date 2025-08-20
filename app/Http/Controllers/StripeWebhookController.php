<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $sig = $request->header('Stripe-Signature');
        $payload = $request->getContent();
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sig, $secret);
        } catch (\Throwable $e) {
            return response('Invalid', 400);
        }
        // try {
        //         $event = Webhook::constructEvent($payload, $sig, $secret);
        //     } catch (UnexpectedValueException $e) {
        //         // Invalid JSON payload
        //         return response()->json([
        //             'ok'       => false,
        //             'error'    => 'invalid_payload',
        //             'message'  => $e->getMessage(),
        //             'endpoint' => $request->url(),
        //             // helpful but safe: do NOT echo secrets
        //             'sig_header_received' => $sig,
        //         ], 400);
        //     } catch (SignatureVerificationException $e) {
        //         // Signature didn’t match the signing secret
        //         return response()->json([
        //             'ok'       => false,
        //             'error'    => 'signature_verification_failed',
        //             'message'  => $e->getMessage(),
        //             'endpoint' => $request->url(),
        //             'sig_header_received' => $sig,
        //             'secret_hint' => substr((string) $secret, 0, 6).'…', // hint only, not the full secret
        //         ], 400);
        //     }

        switch ($event->type) {
            case 'invoice.payment_succeeded':
                $invoice = $event->data->object;
                $stripeSubId = $invoice->subscription ?? null;
                if ($stripeSubId) {
                    Subscription::where('stripe_subscription_id',$stripeSubId)->update([
                        'payment_status' => 'succeeded',
                        'subscription_status' => 'active',
                        'stripe_invoice_id' => $invoice->id,
                        'last_payment_status' => 'succeeded',
                        'last_payment_at' => now(),
                    ]);
                }
                break;

            case 'invoice.payment_failed':
                $invoice = $event->data->object;
                $stripeSubId = $invoice->subscription ?? null;
                if ($stripeSubId) {
                    Subscription::where('stripe_subscription_id',$stripeSubId)->update([
                        'payment_status' => 'failed',
                        'subscription_status' => 'renew_pending', // or 'past_due' if you add it
                        'stripe_invoice_id' => $invoice->id,
                        'last_payment_status' => 'failed',
                        'last_payment_at' => now(),
                    ]);
                }
                break;

            case 'customer.subscription.deleted':
                $sub = $event->data->object;
                Subscription::where('stripe_subscription_id',$sub->id)->update([
                    'subscription_status' => 'canceled',
                    'canceled_at' => now(),
                ]);
                break;

            case 'payment_intent.succeeded':
                $pi = $event->data->object;
                Subscription::where('stripe_payment_intent_id',$pi->id)->update([
                    'payment_status' => 'succeeded',
                    'subscription_status' => 'active',
                    'last_payment_status' => 'succeeded',
                    'last_payment_at' => now(),
                ]);
                break;
        }

        return response('OK', 200);
    }
}
