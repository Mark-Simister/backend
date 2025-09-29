<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Subscription;
use Carbon\Carbon;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;
use App\Models\Video;
use App\Models\VideoLike;
use App\Models\VideoWatchHistory;

class CronJobController extends Controller
{
    /**
     * Run the "cancel overdue renewals" job via HTTP.
     * TIP: Protect this route (auth/signed key/IP allowlist) before exposing in prod.
     */

    public function cancelOverdueRenewals(Request $request)
    {
        $today = Carbon::today();
        $cutoff = $today->copy()->subDay(); // grace: cancel the day *after* end_date

        $unpaidStatuses = ['renew_pending', 'past_due', 'incomplete', 'unpaid', 'requires_payment_method'];
        $notAlreadyCanceled = ['active', 'renew_pending', 'past_due', 'incomplete', 'cancel_scheduled'];

        Stripe::setApiKey(config('services.stripe.secret'));

        $found = 0;
        $skipped = 0;
        $canceled = 0;
        $errors = 0;
        $idsCanceled = [];
        $idsErrors = [];
        $errorsDetail = [];

        // -----------------------------
        // (A) ONE-TIME: local closeout
        // -----------------------------
        \App\Models\Subscription::query()
            ->where('billing_cycle', 'one_time')
            ->whereDate('subscription_end_date', '<', $today)
            ->whereIn('subscription_status', $notAlreadyCanceled)
            ->orderBy('id')
            ->chunkById(200, function ($subs) use ($cutoff, &$found, &$skipped, &$canceled, &$idsCanceled) {
                foreach ($subs as $sub) {
                    $found++;

                    // enforce 1-day grace
                    if (Carbon::parse($sub->subscription_end_date)->gt($cutoff)) {
                        $skipped++;
                        continue;
                    }


                    $sub->update([
                        'subscription_status' => 'canceled',
                        'canceled_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $canceled++;
                    $idsCanceled[] = $sub->id;

                    \Log::info('Closed out one-time subscription at period end', [
                        'local_id' => $sub->id,
                    ]);
                }
            });

        // -------------------------------------------------------------
        // (B) RECURRING + AUTO RENEW = TRUE: your existing cancellation
        // -------------------------------------------------------------
        \App\Models\Subscription::query()
            ->where('billing_cycle', 'recurring')
            ->where('auto_renew', true)
            ->whereDate('subscription_end_date', '<', $today)
            ->whereIn('subscription_status', $notAlreadyCanceled)
            ->whereNotNull('stripe_subscription_id')
            ->orderBy('id')
            ->chunkById(200, function ($subs) use ($cutoff, $unpaidStatuses, &$found, &$skipped, &$canceled, &$errors, &$idsCanceled, &$idsErrors, &$errorsDetail) {
                foreach ($subs as $sub) {
                    $found++;

                    // grace day
                    if (Carbon::parse($sub->subscription_end_date)->gt($cutoff)) {
                        $skipped++;
                        continue;
                    }


                    if (
                        !in_array($sub->subscription_status, $unpaidStatuses) &&
                        ($sub->last_payment_status === 'succeeded')
                    ) {
                        $skipped++;
                        continue;
                    }

                    try {
                        $stripeId = $sub->stripe_subscription_id;
                        $stripeSub = \Stripe\Subscription::retrieve($stripeId);

                        if ($stripeSub->status !== 'canceled') {
                            $stripeSub->cancel();
                        }

                        $sub->update([
                            'subscription_status' => 'canceled',
                            'canceled_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $canceled++;
                        $idsCanceled[] = $sub->id;

                        \Log::info('Canceled overdue recurring renewal', [
                            'local_id' => $sub->id,
                            'stripe_id' => $stripeId,
                        ]);
                    } catch (\Stripe\Exception\InvalidRequestException $e) {
                        $msg = $e->getMessage();

                        if (stripos($msg, 'No such subscription') !== false) {
                            // already gone upstream — mirror locally
                            $sub->update([
                                'subscription_status' => 'canceled',
                                'canceled_at' => now(),
                                'updated_at' => now(),
                            ]);

                            $canceled++;
                            $idsCanceled[] = $sub->id;

                            \Log::warning('Stripe subscription missing; mirrored local as canceled', [
                                'local_id' => $sub->id,
                                'stripe_id' => $sub->stripe_subscription_id,
                                'error' => $msg,
                            ]);
                            return; // not counted as error
                        }

                        // other request errors
                        $errors++;
                        $idsErrors[] = $sub->id;
                        $detail = [
                            'local_id' => $sub->id,
                            'stripe_id' => $sub->stripe_subscription_id,
                            'exception' => get_class($e),
                            'message' => $e->getMessage(),
                            'request_id' => $e->getRequestId(),
                        ];
                        if ($e->getError()) {
                            $detail['stripe_code'] = $e->getError()->code ?? null;
                            $detail['stripe_decline_code'] = $e->getError()->decline_code ?? null;
                            $detail['param'] = $e->getError()->param ?? null;
                            $detail['doc_url'] = $e->getError()->doc_url ?? null;
                        }
                        $errorsDetail[] = $detail;
                        \Log::error('Failed canceling overdue recurring renewal', $detail);

                    } catch (\Throwable $e) {
                        $errors++;
                        $idsErrors[] = $sub->id;

                        $detail = [
                            'local_id' => $sub->id,
                            'stripe_id' => $sub->stripe_subscription_id,
                            'exception' => get_class($e),
                            'message' => $e->getMessage(),
                        ];
                        $errorsDetail[] = $detail;
                        \Log::error('Failed canceling overdue recurring renewal', $detail);
                    }
                }
            });

        // ------------------------------------------------------------------
        // (C) RECURRING + AUTO RENEW = FALSE: mirror end-of-term as canceled
        //     (Stripe usually handles this via cancel_at; we mirror locally,
        //      and if Stripe still shows active for any reason, hard-cancel.)
        // ------------------------------------------------------------------
        \App\Models\Subscription::query()
            ->where('billing_cycle', 'recurring')
            ->where('auto_renew', false)
            ->whereDate('subscription_end_date', '<', $today)
            ->whereIn('subscription_status', $notAlreadyCanceled)
            ->orderBy('id')
            ->chunkById(200, function ($subs) use ($cutoff, &$found, &$skipped, &$canceled, &$errors, &$idsCanceled, &$idsErrors, &$errorsDetail) {
                foreach ($subs as $sub) {
                    $found++;

                    if (Carbon::parse($sub->subscription_end_date)->gt($cutoff)) {
                        $skipped++;
                        continue;
                    }

                    // mirror locally first
                    $sub->update([
                        'subscription_status' => 'canceled',
                        'canceled_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $canceled++;
                    $idsCanceled[] = $sub->id;

                    // if we still have a Stripe sub id, make sure it's not left active
                    try {
                        if (!empty($sub->stripe_subscription_id)) {
                            $stripeSub = \Stripe\Subscription::retrieve($sub->stripe_subscription_id);
                            if ($stripeSub->status !== 'canceled') {
                                $stripeSub->cancel();
                            }
                        }
                    } catch (\Stripe\Exception\InvalidRequestException $e) {
                        // "No such subscription" → fine; already gone upstream
                        if (stripos($e->getMessage(), 'No such subscription') === false) {
                            $errors++;
                            $idsErrors[] = $sub->id;
                            $errorsDetail[] = [
                                'local_id' => $sub->id,
                                'stripe_id' => $sub->stripe_subscription_id,
                                'exception' => get_class($e),
                                'message' => $e->getMessage(),
                            ];
                            \Log::error('Post-mirror Stripe cancel (auto_renew=false) failed', [
                                'local_id' => $sub->id,
                                'stripe_id' => $sub->stripe_subscription_id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    } catch (\Throwable $e) {
                        $errors++;
                        $idsErrors[] = $sub->id;
                        $errorsDetail[] = [
                            'local_id' => $sub->id,
                            'stripe_id' => $sub->stripe_subscription_id,
                            'exception' => get_class($e),
                            'message' => $e->getMessage(),
                        ];
                        \Log::error('Post-mirror Stripe cancel (auto_renew=false) failed', [
                            'local_id' => $sub->id,
                            'stripe_id' => $sub->stripe_subscription_id,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    \Log::info('Closed out non-renewing recurring subscription at period end', [
                        'local_id' => $sub->id,
                        'stripe_id' => $sub->stripe_subscription_id,
                    ]);
                }
            });

        return response()->json([
            'ok' => true,
            'found' => $found,
            'skipped' => $skipped,
            'canceled' => $canceled,
            'errors' => $errors,
            'ids_canceled' => $idsCanceled,
            'ids_errors' => $idsErrors,
            'errors_detail' => $errorsDetail,
            'ran_at' => now()->toDateTimeString(),
        ]);
    }

    // public function assignHighlightTags()
    // {
    //     $now = Carbon::now();

    //     // Process videos in chunks to avoid memory overload
    //     Video::where('highlight_tags_last_checked_at', '<', $now->copy()->subMinutes(5))
    //         ->orWhereNull('highlight_tags_last_checked_at')
    //         ->chunk(100, function ($videos) use ($now) {
    //             foreach ($videos as $video) {
    //                 $tags = $video->highlight_tags
    //                     ? collect(explode(',', $video->highlight_tags))->map(fn($id) => (int) $id)->unique()->toArray()
    //                     : [];

    //                 $newTags = $tags;

    //                 // Rule 1: New Review (within 3 days)
    //                 if (!in_array(1, $tags) && $video->created_at >= $now->copy()->subDays(3)) {
    //                     $newTags[] = 1;
    //                     // dd('this condition New Review is satisfied');
    //                 }

    //                 // Rule 2: Trending Now (Views > 500 in first 24h)
    //                 if (!in_array(3, $tags) && $video->views > 500 && $video->created_at >= $now->copy()->subDay()) {
    //                     $newTags[] = 3;
    //                     // dd('this condition Trending Now is satisfied');
    //                 }

    //                 // Rule 3: Editor’s Pick (Editorial score ≥ 0.8)
    //                 if (!in_array(5, $tags) && $video->editorial_score >= 0.8) {
    //                     $newTags[] = 5;
    //                     // dd('this condition Editor’s Pick is satisfied');
    //                 }

    //                 // Rule 4: First Look (Product released < 14 days ago)
    //                 if (!in_array(4, $tags) && $video->product_release_date && Carbon::parse($video->product_release_date) >= $now->copy()->subDays(14)) {
    //                     $newTags[] = 4;
    //                     // dd('this condition First Look is satisfied');
    //                 }

    //                 // Rule 5: Fan Favorite (likes/views > 15%)
    //                 if (!in_array(6, $tags)) {
    //                     $likes = VideoLike::where('video_id', $video->id)->count();
    //                     $watches = VideoWatchHistory::where('video_id', $video->id)->count();
    //                     // dd($likes, $watches);
    
    //                     if ($watches > 0 && ($likes / $watches) > 0.15) {
    //                         $newTags[] = 6;
    //                         // dd('this condition Fan Favorite is satisfied');
    //                     }
    //                 }

    //                 // Rule 6: Smart Pick (Final BeastieScore ≥ 4.5)
    //                 if (!in_array(9, $tags) && $video->final_beastie_score >= 4.5) {
    //                     $newTags[] = 9;
    //                     // dd($video->final_beastie_score);
    //                     // dd('this condition Smart Pick is satisfied');
    //                 }

    //                 // Rule 7: Amazon Choice
    //                 if (!in_array(8, $tags) && $video->is_amazon_choice) {
    //                     $newTags[] = 8;
    //                     // dd('this condition Amazon Choice is satisfied');
    //                     // dd($video->is_amazon_choice);
    //                 }

    //                 // Rule 8: Time Sensitive - not clear this point
    //                 // dd($video->sale_end_date);
    //                 // if (!in_array(8, $tags) && $video->sale_end_date) {
    //                 //     $newTags[] = 8;
    //                 //     // dd('this condition Time Sensitive is satisfied');
    //                 //     // dd($video->sale_end_date);
    //                 // }

    //                 // Remove duplicates
    //                 $newTags = collect($newTags)->unique()->values()->toArray();

    //                 // Only update if new tags were added
    //                 if ($newTags !== $tags) {
    //                     $video->highlight_tags = implode(',', $newTags);
    //                 }

    //                 // Update last checked timestamp regardless
    //                 $video->highlight_tags_last_checked_at = now();
    //                 $video->save();
    //             }
    //         });

    //     return response()->json(['status' => 'success', 'message' => 'Highlight tags updated successfully.']);
    // }

    public function assignHighlightTags()
{
    $now = Carbon::now();

    // Process videos in chunks to avoid memory overload
    Video::where('highlight_tags_last_checked_at', '<', $now->copy()->subMinutes(5))
        ->orWhereNull('highlight_tags_last_checked_at')
        ->chunk(100, function ($videos) use ($now) {
            foreach ($videos as $video) {
                $tags = $video->highlight_tags
                    ? collect(explode(',', $video->highlight_tags))->map(fn($id) => (int) $id)->unique()->toArray()
                    : [];

                $newTags = $tags;

                // -------------------------------
                // Rule 1: New Review (within 3 days)
                // -------------------------------
                if ($video->created_at >= $now->copy()->subDays(3)) {
                    if (!in_array(1, $newTags)) {
                        $newTags[] = 1;
                        // dd('this condition New Review is satisfied');
                    }
                } else {
                    // Remove tag if older than 3 days
                    if (in_array(1, $newTags)) {
                        $newTags = array_diff($newTags, [1]);
                        // dd('this condition New Review expired, removed');
                    }
                }

                // -------------------------------
                // Rule 2: Trending Now (Views > 500 in first 24h)
                // -------------------------------
                if (!in_array(3, $newTags) && $video->views > 500 && $video->created_at >= $now->copy()->subDay()) {
                    $newTags[] = 3;
                    // dd('this condition Trending Now is satisfied');
                }

                // -------------------------------
                // Rule 3: Editor’s Pick (Editorial score ≥ 0.8)
                // -------------------------------
                if (!in_array(5, $newTags) && $video->editorial_score >= 0.8) {
                    $newTags[] = 5;
                    // dd('this condition Editor’s Pick is satisfied');
                }

                // -------------------------------
                // Rule 4: First Look (Product released < 14 days ago)
                // -------------------------------
                if ($video->product_release_date) {
                    if (Carbon::parse($video->product_release_date) >= $now->copy()->subDays(14)) {
                        if (!in_array(4, $newTags)) {
                            $newTags[] = 4;
                            // dd('this condition First Look is satisfied');
                        }
                    } else {
                        // Remove tag if older than 14 days
                        if (in_array(4, $newTags)) {
                            $newTags = array_diff($newTags, [4]);
                            // dd('this condition First Look expired, removed');
                        }
                    }
                }

                // -------------------------------
                // Rule 5: Fan Favorite (likes/views > 15%)
                // -------------------------------
                if (!in_array(6, $newTags)) {
                    $likes = VideoLike::where('video_id', $video->id)->count();
                    $watches = VideoWatchHistory::where('video_id', $video->id)->count();

                    if ($watches > 0 && ($likes / $watches) > 0.15) {
                        $newTags[] = 6;
                        // dd('this condition Fan Favorite is satisfied');
                    }
                }

                // -------------------------------
                // Rule 6: Smart Pick (Final BeastieScore ≥ 4.5)
                // -------------------------------
                if (!in_array(9, $newTags) && $video->final_beastie_score >= 4.5) {
                    $newTags[] = 9;
                    // dd($video->final_beastie_score);
                    // dd('this condition Smart Pick is satisfied');
                }

                // -------------------------------
                // Rule 7: Amazon Choice
                // -------------------------------
                if (!in_array(8, $newTags) && $video->is_amazon_choice) {
                    $newTags[] = 8;
                    // dd('this condition Amazon Choice is satisfied');
                }

                // Rule 8: Time Sensitive - not clear this point
                // dd($video->sale_end_date);

                // Remove duplicates
                $newTags = collect($newTags)->unique()->values()->toArray();

                // Only update if tags changed (added or removed)
                if ($newTags !== $tags) {
                    $video->highlight_tags = implode(',', $newTags);
                }

                // Update last checked timestamp regardless
                $video->highlight_tags_last_checked_at = now();
                $video->save();
            }
        });

    return response()->json(['status' => 'success', 'message' => 'Highlight tags updated successfully.']);
}


}