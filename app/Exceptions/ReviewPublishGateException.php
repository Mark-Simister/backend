<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An EXPECTED refusal to publish: the video does not satisfy the publish gate, or its
 * data is too incomplete to build a public page.
 *
 * These are recorded on the video (seo_publish_status='error' + seo_publish_error) and
 * deliberately swallowed. Retrying cannot help — nothing about the video has changed —
 * and any previously published page must keep serving.
 *
 * Everything else that escapes the publisher is UNEXPECTED (a bug, a dropped connection,
 * a deadlock). Those are reported and rethrown so the worker retries and, once $tries is
 * exhausted, records the job in failed_jobs.
 *
 * Do NOT widen the expected-failure catch to RuntimeException: an unrelated runtime fault
 * would then be swallowed, the job would report success, and the page would rot silently.
 */
class ReviewPublishGateException extends RuntimeException
{
    /** @param string[] $errors */
    public static function fromGateErrors(array $errors): self
    {
        return new self('Cannot publish to SEO: ' . implode('; ', $errors));
    }
}
