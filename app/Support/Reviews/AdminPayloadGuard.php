<?php

namespace App\Support\Reviews;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Refuses to run a seeder against a database that holds admin-authored public review
 * pages.
 *
 * The three region-syncing seeders (ContentSeeder, FlagshipReviewSeeder,
 * WebsiteReviewSeeder) rewrite `videos` and the `video_region` pivot. Run against the
 * shared staging database they would silently desync every `source='admin'` payload from
 * the video it was snapshotted from — changing which regions a live public page serves,
 * with no error and no trace.
 *
 * `php artisan reviews:sync-payload-regions` can repair that afterwards, but relying on
 * someone remembering to run it is not a safeguard. This makes the mistake hard to make.
 *
 * Those three seeders are deliberately NOT tracked in git, so they cannot run on any
 * server today. This guard travels with them if that ever changes, and — more usefully —
 * it protects the tracked DatabaseSeeder, which is what a stray `php artisan db:seed`
 * actually executes.
 */
class AdminPayloadGuard
{
    /**
     * @throws RuntimeException when admin payloads exist and the operator does not confirm
     */
    public static function assertSafe(?Command $command, string $what): void
    {
        $count = self::adminPayloadCount();

        if ($count === 0) {
            return;
        }

        $message = "{$what} would rewrite videos and their region pivot, but this database "
            . "holds {$count} admin-published review page(s). Their public regions would "
            . 'silently desync from the payload snapshot.';

        // Refuse before asking, when there is nobody to ask: outside the console there is
        // no $command, and under --no-interaction Symfony would silently hand back the
        // default. Depending on that default would make this guard's behaviour a property
        // of the question helper rather than of the guard. Fail closed, explicitly.
        $nobodyToAsk = ! $command || (bool) $command->option('no-interaction');

        if ($nobodyToAsk || ! $command->confirm($message . ' Continue anyway?', false)) {
            throw new RuntimeException(
                $message . ' Refusing to run. If this is genuinely intended, re-run it '
                . 'interactively and confirm, then `php artisan reviews:sync-payload-regions`.'
            );
        }

        $command->warn("Proceeding against {$count} admin payload(s). Run `php artisan reviews:sync-payload-regions` afterwards.");
    }

    public static function adminPayloadCount(): int
    {
        if (! Schema::hasTable('published_review_payloads')) {
            return 0;
        }

        return DB::table('published_review_payloads')->where('source', 'admin')->count();
    }
}
