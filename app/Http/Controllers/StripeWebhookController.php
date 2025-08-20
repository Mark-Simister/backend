<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;
use Carbon\Carbon;

class StripeWebhookController extends Controller
{

public function handle(Request $request)
{
    $sig     = $request->header('Stripe-Signature');
    $payload = $request->getContent();
    $secret  = config('services.stripe.webhook_secret');

    // Verify signature (return details in response so you can see them in Stripe dashboard while debugging)
    try {
        $event = Webhook::constructEvent($payload, $sig, $secret);
    } catch (UnexpectedValueException $e) {
        // Invalid JSON payload
        return response()->json([
            'ok'                  => false,
            'error'               => 'invalid_payload',
            'message'             => $e->getMessage(),
            'endpoint'            => $request->url(),
            'sig_header_received' => $sig,
        ], 400);
    } catch (SignatureVerificationException $e) {
        // Signature didn’t match the signing secret (wrong/mismatched whsec_)
        return response()->json([
            'ok'                  => false,
            'error'               => 'signature_verification_failed',
            'message'             => $e->getMessage(),
            'endpoint'            => $request->url(),
            'sig_header_received' => $sig,
            'secret_hint'         => substr((string) $secret, 0, 6) . '…',
        ], 400);
    }

    // You will call Stripe API below to retrieve/confirm PIs
    \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

    switch ($event->type) {

        case 'invoice.payment_succeeded': {
            /** @var \Stripe\Invoice $invoice */
            $invoice = $event->data->object;
            $stripeSubId = $invoice->subscription ?? null;

            // payment_intent can be a string id or an object depending on expansion
            $piId = null;
            if (isset($invoice->payment_intent)) {
                $piId = is_object($invoice->payment_intent)
                    ? $invoice->payment_intent->id
                    : $invoice->payment_intent;
            }

            if ($stripeSubId) {
                Subscription::where('stripe_subscription_id', $stripeSubId)->update([
                    'payment_status'            => 'succeeded',
                    'subscription_status'       => 'active',
                    'stripe_invoice_id'         => $invoice->id,
                    'stripe_payment_intent_id'  => $piId,
                    'last_payment_status'       => 'succeeded',
                    'last_payment_at'           => now(),
                ]);
            }
            break;
        }

        case 'invoice.payment_failed': {
            /** @var \Stripe\Invoice $invoice */
            $invoice = $event->data->object;
            $stripeSubId = $invoice->subscription ?? null;

            $piId = null;
            if (isset($invoice->payment_intent)) {
                $piId = is_object($invoice->payment_intent)
                    ? $invoice->payment_intent->id
                    : $invoice->payment_intent;
            }

            if ($stripeSubId) {
                Subscription::where('stripe_subscription_id', $stripeSubId)->update([
                    'payment_status'            => 'failed',
                    'subscription_status'       => 'renew_pending', // or 'past_due' if you add that status
                    'stripe_invoice_id'         => $invoice->id,
                    'stripe_payment_intent_id'  => $piId,
                    'last_payment_status'       => 'failed',
                    'last_payment_at'           => now(),
                ]);
            }
            break;
        }

        case 'invoice.payment_action_required': {
            /** @var \Stripe\Invoice $invoice */
            $invoice = $event->data->object;
            $stripeSubId = $invoice->subscription ?? null;

            $piId = null;
            if (isset($invoice->payment_intent)) {
                $piId = is_object($invoice->payment_intent)
                    ? $invoice->payment_intent->id
                    : $invoice->payment_intent;
            }

            if ($stripeSubId) {
                // Attempt a server-side confirm (works only if bank does not require user SCA)
                if ($piId) {
                    try {
                        $pi = \Stripe\PaymentIntent::retrieve($piId);

                        if (in_array($pi->status, ['requires_action', 'requires_confirmation'])) {
                            // Attempt off-session confirmation using customer's default PM
                            $pi = $pi->confirm(); // instance method; will succeed only if no SCA needed
                        }

                        // Persist latest PI status regardless of outcome
                        $updates = [
                            'subscription_status'       => ($pi->status === 'succeeded') ? 'active' : 'incomplete',
                            'stripe_invoice_id'         => $invoice->id,
                            'stripe_payment_intent_id'  => $pi->id,
                            'last_payment_status'       => $pi->status,
                            'last_payment_at'           => now(),
                        ];

                        if ($pi->status === 'succeeded') {
                            $updates['payment_status'] = 'succeeded';
                        }

                        Subscription::where('stripe_subscription_id', $stripeSubId)->update($updates);

                    } catch (\Throwable $e) {
                        // Even if confirm fails, keep invoice + PI saved for later client confirmation
                        Subscription::where('stripe_subscription_id', $stripeSubId)->update([
                            'subscription_status'       => 'incomplete',
                            'stripe_invoice_id'         => $invoice->id,
                            'stripe_payment_intent_id'  => $piId,
                            'last_payment_status'       => 'requires_action',
                            'last_payment_at'           => now(),
                        ]);
                    }
                } else {
                    // No PI present; just persist invoice
                    Subscription::where('stripe_subscription_id', $stripeSubId)->update([
                        'subscription_status'  => 'incomplete',
                        'stripe_invoice_id'    => $invoice->id,
                        'last_payment_status'  => 'requires_action',
                        'last_payment_at'      => now(),
                    ]);
                }
            }
            break;
        }

        case 'invoice.finalized': {
            /** @var \Stripe\Invoice $invoice */
            $invoice = $event->data->object;
            $stripeSubId = $invoice->subscription ?? null;

            $piId = null;
            if (isset($invoice->payment_intent)) {
                $piId = is_object($invoice->payment_intent)
                    ? $invoice->payment_intent->id
                    : $invoice->payment_intent;
            }

            if ($stripeSubId) {
                // Store invoice + PI early so later confirm endpoints can find it
                Subscription::where('stripe_subscription_id', $stripeSubId)->update([
                    'stripe_invoice_id'         => $invoice->id,
                    'stripe_payment_intent_id'  => $piId,
                    'last_payment_at'           => now(),
                ]);

                // Try to confirm automatically here as well (off-session)
                if ($piId) {
                    try {
                        $pi = \Stripe\PaymentIntent::retrieve($piId);

                        if (in_array($pi->status, ['requires_confirmation', 'requires_action'])) {
                            $pi = $pi->confirm(); // instance method
                        }

                        $updates = [
                            'last_payment_status' => $pi->status,
                            'last_payment_at'     => now(),
                        ];

                        if ($pi->status === 'succeeded') {
                            $updates['payment_status']      = 'succeeded';
                            $updates['subscription_status'] = 'active';
                        } elseif ($pi->status === 'requires_payment_method') {
                            $updates['subscription_status'] = 'incomplete';
                        }

                        Subscription::where('stripe_subscription_id', $stripeSubId)->update($updates);

                    } catch (\Throwable $e) {
                        // Ignore; user might need to complete SCA on hosted page / client
                    }
                }
            }
            break;
        }

        case 'customer.subscription.updated': {
            /** @var \Stripe\Subscription $sub */
            $sub = $event->data->object;

            $updates = [
                'updated_at' => now(),
            ];

            // Reflect cancel flags & schedule
            if (isset($sub->cancel_at_period_end)) {
                $updates['cancel_at_period_end'] = (bool) $sub->cancel_at_period_end;
                // If cancel is scheduled, keep status informative
                if ($sub->cancel_at_period_end) {
                    $updates['subscription_status'] = 'cancel_scheduled';
                } else {
                    $updates['subscription_status'] = 'active';
                }
            }

            // If Stripe provides a cancel_at timestamp, store when it will cancel
            if (!empty($sub->cancel_at)) {
                $updates['canceled_at'] = Carbon::createFromTimestamp($sub->cancel_at);
            }

            // Optionally sync current period end -> subscription_end_date
            if (!empty($sub->current_period_end)) {
                $updates['subscription_end_date'] = Carbon::createFromTimestamp($sub->current_period_end)->toDateString();
            }

            if (!empty($updates)) {
                Subscription::where('stripe_subscription_id', $sub->id)->update($updates);
            }
            break;
        }

        case 'customer.subscription.deleted': {
            /** @var \Stripe\Subscription $sub */
            $sub = $event->data->object;

            Subscription::where('stripe_subscription_id', $sub->id)->update([
                'subscription_status' => 'canceled',
                'canceled_at'         => now(),
            ]);
            break;
        }

        case 'payment_intent.succeeded': {
            /** @var \Stripe\PaymentIntent $pi */
            $pi = $event->data->object;

            // This covers one-time purchases (where you stored stripe_payment_intent_id)
            Subscription::where('stripe_payment_intent_id', $pi->id)->update([
                'payment_status'       => 'succeeded',
                'subscription_status'  => 'active',
                'last_payment_status'  => 'succeeded',
                'last_payment_at'      => now(),
            ]);
            break;
        }

        // Informational events you don't need to mutate DB for in your flow
        case 'customer.subscription.created':
        default: {
            // No state change needed; acknowledge with 200 OK below
            break;
        }
    }

    return response('OK', 200);
}



    // asking user to go to confirmation api call then again other issues
//     public function handle(Request $request)
// {
//     $sig     = $request->header('Stripe-Signature');
//     $payload = $request->getContent();
//     $secret  = config('services.stripe.webhook_secret');

//     // Verify signature (return details in response so you can see them in Stripe dashboard while debugging)
//     try {
//         $event = Webhook::constructEvent($payload, $sig, $secret);
//     } catch (UnexpectedValueException $e) {
//         // Invalid JSON payload
//         return response()->json([
//             'ok'                  => false,
//             'error'               => 'invalid_payload',
//             'message'             => $e->getMessage(),
//             'endpoint'            => $request->url(),
//             'sig_header_received' => $sig,
//         ], 400);
//     } catch (SignatureVerificationException $e) {
//         // Signature didn’t match the signing secret (wrong/mismatched whsec_)
//         return response()->json([
//             'ok'                  => false,
//             'error'               => 'signature_verification_failed',
//             'message'             => $e->getMessage(),
//             'endpoint'            => $request->url(),
//             'sig_header_received' => $sig,
//             'secret_hint'         => substr((string) $secret, 0, 6) . '…',
//         ], 400);
//     }

//     switch ($event->type) {

//         case 'invoice.payment_succeeded': {
//             /** @var \Stripe\Invoice $invoice */
//             $invoice = $event->data->object;
//             $stripeSubId = $invoice->subscription ?? null;

//             // payment_intent can be a string id or an object depending on expansion
//             $piId = null;
//             if (isset($invoice->payment_intent)) {
//                 $piId = is_object($invoice->payment_intent)
//                     ? $invoice->payment_intent->id
//                     : $invoice->payment_intent;
//             }

//             if ($stripeSubId) {
//                 Subscription::where('stripe_subscription_id', $stripeSubId)->update([
//                     'payment_status'            => 'succeeded',
//                     'subscription_status'       => 'active',
//                     'stripe_invoice_id'         => $invoice->id,
//                     'stripe_payment_intent_id'  => $piId,
//                     'last_payment_status'       => 'succeeded',
//                     'last_payment_at'           => now(),
//                 ]);
//             }
//             break;
//         }

//         case 'invoice.payment_failed': {
//             /** @var \Stripe\Invoice $invoice */
//             $invoice = $event->data->object;
//             $stripeSubId = $invoice->subscription ?? null;

//             $piId = null;
//             if (isset($invoice->payment_intent)) {
//                 $piId = is_object($invoice->payment_intent)
//                     ? $invoice->payment_intent->id
//                     : $invoice->payment_intent;
//             }

//             if ($stripeSubId) {
//                 Subscription::where('stripe_subscription_id', $stripeSubId)->update([
//                     'payment_status'            => 'failed',
//                     'subscription_status'       => 'renew_pending', // or 'past_due' if you add that status
//                     'stripe_invoice_id'         => $invoice->id,
//                     'stripe_payment_intent_id'  => $piId,
//                     'last_payment_status'       => 'failed',
//                     'last_payment_at'           => now(),
//                 ]);
//             }
//             break;
//         }

//         case 'invoice.payment_action_required': {
//             /** @var \Stripe\Invoice $invoice */
//             $invoice = $event->data->object;
//             $stripeSubId = $invoice->subscription ?? null;

//             $piId = null;
//             if (isset($invoice->payment_intent)) {
//                 $piId = is_object($invoice->payment_intent)
//                     ? $invoice->payment_intent->id
//                     : $invoice->payment_intent;
//             }

//             if ($stripeSubId) {
//                 Subscription::where('stripe_subscription_id', $stripeSubId)->update([
//                     'subscription_status'       => 'incomplete',
//                     'stripe_invoice_id'         => $invoice->id,
//                     'stripe_payment_intent_id'  => $piId,
//                     'last_payment_status'       => 'requires_action',
//                     'last_payment_at'           => now(),
//                 ]);
//             }
//             break;
//         }

//         case 'invoice.finalized': {
//             /** @var \Stripe\Invoice $invoice */
//             $invoice = $event->data->object;
//             $stripeSubId = $invoice->subscription ?? null;

//             $piId = null;
//             if (isset($invoice->payment_intent)) {
//                 $piId = is_object($invoice->payment_intent)
//                     ? $invoice->payment_intent->id
//                     : $invoice->payment_intent;
//             }

//             if ($stripeSubId) {
//                 // Store invoice + PI early so later confirm endpoints can find it
//                 Subscription::where('stripe_subscription_id', $stripeSubId)->update([
//                     'stripe_invoice_id'         => $invoice->id,
//                     'stripe_payment_intent_id'  => $piId,
//                     'last_payment_at'           => now(),
//                 ]);
//             }
//             break;
//         }

//         case 'customer.subscription.updated': {
//             /** @var \Stripe\Subscription $sub */
//             $sub = $event->data->object;

//             $updates = [
//                 'updated_at' => now(),
//             ];

//             // Reflect cancel flags & schedule
//             if (isset($sub->cancel_at_period_end)) {
//                 $updates['cancel_at_period_end'] = (bool) $sub->cancel_at_period_end;
//                 // If cancel is scheduled, keep status informative
//                 if ($sub->cancel_at_period_end) {
//                     $updates['subscription_status'] = 'cancel_scheduled';
//                 } else {
//                     $updates['subscription_status'] = 'active';
//                 }
//             }

//             // If Stripe provides a cancel_at timestamp, store when it will cancel
//             if (!empty($sub->cancel_at)) {
//                 $updates['canceled_at'] = Carbon::createFromTimestamp($sub->cancel_at);
//             }

//             // Optionally sync current period end -> subscription_end_date
//             if (!empty($sub->current_period_end)) {
//                 $updates['subscription_end_date'] = Carbon::createFromTimestamp($sub->current_period_end)->toDateString();
//             }

//             if (!empty($updates)) {
//                 Subscription::where('stripe_subscription_id', $sub->id)->update($updates);
//             }
//             break;
//         }

//         case 'customer.subscription.deleted': {
//             /** @var \Stripe\Subscription $sub */
//             $sub = $event->data->object;

//             Subscription::where('stripe_subscription_id', $sub->id)->update([
//                 'subscription_status' => 'canceled',
//                 'canceled_at'         => now(),
//             ]);
//             break;
//         }

//         case 'payment_intent.succeeded': {
//             /** @var \Stripe\PaymentIntent $pi */
//             $pi = $event->data->object;

//             // This covers one-time purchases (where you stored stripe_payment_intent_id)
//             Subscription::where('stripe_payment_intent_id', $pi->id)->update([
//                 'payment_status'       => 'succeeded',
//                 'subscription_status'  => 'active',
//                 'last_payment_status'  => 'succeeded',
//                 'last_payment_at'      => now(),
//             ]);
//             break;
//         }

//         // Informational events you don't need to mutate DB for in your flow
//         case 'customer.subscription.created':
//         default: {
//             // No state change needed; acknowledge with 200 OK below
//             break;
//         }
//     }

//     return response('OK', 200);
// }


}
