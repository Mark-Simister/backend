<?php

namespace Tests\Feature\AdminContent\Concerns;

use App\Models\PublishedReviewPayload;
use App\Support\Reviews\PublicReleaseVerifier;
use App\Support\Reviews\SafetyFeedContradiction;
use InvalidArgumentException;

/**
 * REVIEWFIX-03 negative controls, without touching application source: when the
 * PUBLIC_RELEASE_NEGATIVE_CONTROL environment variable names a control, the container
 * serves a test double that removes one guard, so the real tests can be run against it
 * and shown to fail. Unset - as in every normal run - this does nothing.
 *
 *   structural  structural evaluability always passes
 *   d1          D1 never blocks
 *   per-row     the SQL gates run once per row instead of once per bounded chunk
 */
trait PublicReleaseNegativeControls
{
    protected function applyPublicReleaseNegativeControl(): void
    {
        $control = getenv('PUBLIC_RELEASE_NEGATIVE_CONTROL');

        match ($control) {
            false, '' => null,
            'structural' => $this->app->bind(PublicReleaseVerifier::class, fn ($app) => new class($app->make(SafetyFeedContradiction::class)) extends PublicReleaseVerifier {
                protected function structurallyEvaluable(PublishedReviewPayload $payload): bool
                {
                    return true;
                }
            }),
            'd1' => $this->app->instance(SafetyFeedContradiction::class, new class extends SafetyFeedContradiction {
                public function blocks(mixed $reviewPage): bool
                {
                    return false;
                }
            }),
            'per-row' => $this->app->bind(PublicReleaseVerifier::class, fn ($app) => new class($app->make(SafetyFeedContradiction::class)) extends PublicReleaseVerifier {
                protected function chunkSize(): int
                {
                    return 1;
                }
            }),
            default => throw new InvalidArgumentException("Unknown negative control [{$control}]."),
        };
    }
}
