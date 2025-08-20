<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Video;
use Carbon\Carbon;

class AutoTaggingJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:auto-tagging-job';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically assign tags to videos based on defined logic';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $videos = Video::all(); // You can add more specific criteria to limit the scope

        foreach ($videos as $video) {
            $tags = [];

            // 🔥 Top Deal: If product has >20% discount
            if ($video->affiliate_link && $video->original_price && ($video->original_price - $video->price) / $video->original_price > 0.2) {
                $tags[] = 'Top Deal';
            }

            // 🆕 New Review: If the video was created within the last 3 days
            if (Carbon::parse($video->created_at)->diffInDays(now()) <= 3) {
                $tags[] = 'New Review';
            }

            // 💥 Trending Now: If views > 500 in the first 24 hours
            if ($video->views > 500 && Carbon::parse($video->created_at)->diffInHours(now()) <= 24) {
                $tags[] = 'Trending Now';
            }

            // 🎯 Editor’s Pick: If editorial score ≥ 0.8
            if ($video->editorial_score >= 0.8) {
                $tags[] = 'Editor\'s Pick';
            }

            // 🧪 First Look: If product release is recent (< 14 days)
            if (Carbon::parse($video->created_at)->diffInDays(now()) <= 14) {
                $tags[] = 'First Look';
            }

            // 😍 Fan Favorite: If likes-to-views ratio > 15%
            if (($video->likes / max($video->views, 1)) > 0.15) {
                $tags[] = 'Fan Favorite';
            }

            // 🕒 Time-Sensitive: If sale end date is within 72 hours
            if ($video->sale_end_date && Carbon::parse($video->sale_end_date)->diffInHours(now()) <= 72) {
                $tags[] = 'Time-Sensitive';
            }

            // 🧠 Smart Pick: If final beastie score ≥ 4.5
            if ($video->final_beastie_score >= 4.5) {
                $tags[] = 'Smart Pick';
            }

            // 🛍️ Amazon Choice: If is_amazon_choice is true
            if ($video->is_amazon_choice) {
                $tags[] = 'Amazon Choice';
            }

            // Sync auto tags
            if (!empty($tags)) {
                $video->auto_tags = $tags;  // Assuming auto_tags is a JSON column (array of tags)
                $video->save();
            }
        }

        $this->info('Automated tags applied successfully!');
    }
}
