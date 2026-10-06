<?php

namespace Tests\Feature\AdminContent;

use App\Models\AdminAuditEvent;
use App\Models\MediaAttachment;
use App\Models\ReviewEditorialRecord;
use App\Models\ReviewPublicationDisposition;
use App\Services\ReviewDispositionService;
use App\Support\Reviews\FingerprintScheme;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\AdminContent\Concerns\AdminContentFixtures;
use Tests\TestCase;

/**
 * reviews:fingerprint-backfill and reviews:reconcile-editorial (design record 4.10):
 * explicit, idempotent, --check read-only, --apply audited. Neither is scheduled.
 */
class ReviewCommandsTest extends TestCase
{
    use AdminContentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureAdminContent();
        $this->associations();
    }

    private function bypassEdit(int $payloadId, string $summary): void
    {
        $rp = json_decode(DB::table('published_review_payloads')->where('id', $payloadId)->value('review_page_json'), true);
        $rp['quick_verdict']['summary'] = $summary;
        DB::table('published_review_payloads')->where('id', $payloadId)->update(['review_page_json' => json_encode($rp)]);
    }

    public function test_both_commands_need_exactly_one_mode(): void
    {
        foreach (['reviews:fingerprint-backfill', 'reviews:reconcile-editorial'] as $command) {
            $this->artisan($command)->assertExitCode(Command::INVALID);
            $this->artisan($command, ['--check' => true, '--apply' => true])->assertExitCode(Command::INVALID);
        }
    }

    public function test_both_commands_fail_closed_on_an_invalid_video_authority(): void
    {
        config(['reviews.video_authority' => 'Payload']);

        $this->artisan('reviews:fingerprint-backfill', ['--check' => true])->assertExitCode(Command::FAILURE);
        $this->artisan('reviews:reconcile-editorial', ['--apply' => true])->assertExitCode(Command::FAILURE);
    }

    public function test_backfill_check_is_read_only_and_apply_repairs_and_demotes(): void
    {
        $payload = $this->releasedReview();
        $this->artisan('reviews:fingerprint-backfill', ['--check' => true])->expectsOutput('scheme=' . FingerprintScheme::P1 . ' drift=0')->assertExitCode(Command::SUCCESS);

        $this->bypassEdit($payload->id, 'Changed without the ingestion service.');
        $stored = $payload->fresh()->content_fingerprint;

        $this->artisan('reviews:fingerprint-backfill', ['--check' => true])
            ->expectsOutput('  drift: ' . $payload->published_review_id)
            ->assertExitCode(Command::FAILURE);
        $this->assertSame($stored, $payload->fresh()->content_fingerprint, '--check writes nothing');
        $this->assertSame(ReviewEditorialRecord::STATE_RELEASED, $this->record($payload)->editorial_state);

        $this->artisan('reviews:fingerprint-backfill', ['--apply' => true])->assertExitCode(Command::SUCCESS);
        $this->assertNotSame($stored, $payload->fresh()->content_fingerprint);
        $this->assertSame(ReviewEditorialRecord::STATE_PENDING_REVIEW, $this->record($payload)->editorial_state);
        $event = AdminAuditEvent::query()->where('action', 'review.invalidated')->firstOrFail();
        $this->assertSame('fingerprint_backfill', $event->details['source']);

        $this->artisan('reviews:fingerprint-backfill', ['--check' => true])->assertExitCode(Command::SUCCESS);
        $this->artisan('reviews:fingerprint-backfill', ['--apply' => true])->assertExitCode(Command::SUCCESS);
        $this->assertSame(1, AdminAuditEvent::query()->where('action', 'review.invalidated')->count(), 'idempotent');
    }

    public function test_backfill_fills_missing_derived_fingerprints_without_touching_content(): void
    {
        $payload = $this->ingest($this->reviewRow());
        DB::table('published_review_payloads')->where('id', $payload->id)->update(['content_fingerprint' => null, 'content_fingerprint_scheme' => null]);
        $before = DB::table('published_review_payloads')->where('id', $payload->id)->value('review_page_json');

        $this->artisan('reviews:fingerprint-backfill', ['--apply' => true])->assertExitCode(Command::SUCCESS);

        $this->assertSame(FingerprintScheme::P1, $payload->fresh()->content_fingerprint_scheme);
        $this->assertNotNull($payload->fresh()->content_fingerprint);
        $this->assertSame($before, DB::table('published_review_payloads')->where('id', $payload->id)->value('review_page_json'));
    }

    public function test_reconcile_reports_and_demotes_governance_and_disposition_blocks(): void
    {
        $d3 = $this->releasedReview('pr_d3');
        $withdrawn = $this->releasedReview('pr_withdrawn');
        $unavailable = $this->releasedReview('pr_unavailable');
        $clean = $this->releasedReview('pr_clean');

        $this->artisan('reviews:reconcile-editorial', ['--check' => true])->assertExitCode(Command::SUCCESS);

        config(['reviews.divergence_hold_product_uids' => [$d3->product_uid]]);
        // A disposition written without the Admin action (so no T5 ran): the backstop catches it.
        app(ReviewDispositionService::class)->recordWithdrawal($withdrawn->published_review_id, null, 'direct', null);
        app(ReviewDispositionService::class)->setAvailability($unavailable->published_review_id, ReviewPublicationDisposition::AVAILABILITY_TEMPORARILY_UNAVAILABLE);

        $this->artisan('reviews:reconcile-editorial', ['--check' => true])
            ->expectsOutput('  issue: pr_d3 d3_held')
            ->expectsOutput('  issue: pr_withdrawn disposition_blocked')
            ->doesntExpectOutput('  issue: pr_unavailable disposition_blocked')
            ->assertExitCode(Command::FAILURE);
        $this->assertSame(ReviewEditorialRecord::STATE_RELEASED, $this->record($d3)->editorial_state, '--check writes nothing');

        $this->artisan('reviews:reconcile-editorial', ['--apply' => true])->assertExitCode(Command::SUCCESS);

        $this->assertSame(ReviewEditorialRecord::STATE_QA_PASSED, $this->record($d3)->editorial_state);
        $this->assertSame(ReviewEditorialRecord::STATE_QA_PASSED, $this->record($withdrawn)->editorial_state);
        $this->assertSame(ReviewEditorialRecord::STATE_RELEASED, $this->record($unavailable)->editorial_state, 'temporary unavailability never demotes');
        $this->assertSame(ReviewEditorialRecord::STATE_RELEASED, $this->record($clean)->editorial_state);
        $this->assertSame(['d3_held', 'disposition_blocked'], AdminAuditEvent::query()->where('action', 'review.demoted')->orderBy('id')->pluck('reason')->all());

        $this->artisan('reviews:reconcile-editorial', ['--check' => true])->assertExitCode(Command::SUCCESS);
    }

    public function test_reconcile_invalidates_binding_and_scheme_mismatches(): void
    {
        $content = $this->releasedReview('pr_content');
        $media = $this->ingest($this->reviewRow('pr_media'));
        $this->confirm($media);
        $this->registry()->stage($this->videoAsset(), MediaAttachment::OWNER_REVIEW, 'pr_media', 'video', null);
        $this->qaPass($media);
        $this->release($media);

        $this->bypassEdit($content->id, 'Edited directly.');
        DB::table('media_assets')->update(['description' => 'Edited directly.']);

        $this->artisan('reviews:reconcile-editorial', ['--apply' => true])
            ->expectsOutput('  demoted: pr_content binding_mismatch')
            ->expectsOutput('  demoted: pr_media binding_mismatch')
            ->assertExitCode(Command::SUCCESS);

        foreach (['pr_content', 'pr_media'] as $id) {
            $record = $this->record($id);
            $this->assertSame(ReviewEditorialRecord::STATE_PENDING_REVIEW, $record->editorial_state);
            $this->assertFalse($record->hasBinding());
        }

        $scheme = $this->releasedReview('pr_scheme');
        config(['reviews.video_authority' => 'admin']);
        $this->artisan('reviews:reconcile-editorial', ['--check' => true])
            ->expectsOutput('  issue: pr_scheme scheme_mismatch')
            ->assertExitCode(Command::FAILURE);
        $this->assertSame(ReviewEditorialRecord::STATE_RELEASED, $this->record($scheme)->editorial_state);
    }

    public function test_no_scheduler_runs_either_command(): void
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $commands = collect($schedule->events())->map(fn ($e) => (string) $e->command)->implode("\n");

        $this->assertStringNotContainsString('reviews:fingerprint-backfill', $commands);
        $this->assertStringNotContainsString('reviews:reconcile-editorial', $commands);
    }
}
