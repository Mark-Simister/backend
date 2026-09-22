<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An EXPECTED refusal to publish a Best For edition: the edition does not satisfy the
 * publication gate.
 *
 * Deliberately mirrors ReviewPublishGateException, which already establishes this shape
 * for the review publisher — an expected refusal carrying the collected gate errors,
 * distinguishable from an unexpected runtime fault.
 *
 * Retrying without changing something cannot help: nothing about the edition, the
 * referenced publications or the supplied human confirmations has changed. The caller
 * should surface the errors, not loop.
 *
 * Do NOT widen a catch of this to RuntimeException. An unrelated runtime fault would
 * then read as a gate refusal, and an edition would silently fail to publish for a
 * reason nobody recorded.
 */
class BestForPublishGateException extends RuntimeException
{
    /** @var string[] */
    public readonly array $errors;

    /** @param string[] $errors */
    public function __construct(array $errors)
    {
        $this->errors = array_values($errors);

        parent::__construct('Cannot publish Best For edition: ' . implode('; ', $this->errors));
    }

    /** @param string[] $errors */
    public static function fromGateErrors(array $errors): self
    {
        return new self($errors);
    }
}
