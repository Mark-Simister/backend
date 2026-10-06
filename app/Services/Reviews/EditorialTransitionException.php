<?php

namespace App\Services\Reviews;

use DomainException;

/**
 * An expected, user-facing refusal of an editorial action (a guard failed). The record
 * is unchanged. `errors` lists every failed guard so Admin can show the blockers.
 */
class EditorialTransitionException extends DomainException
{
    /** @param string[] $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }
}
