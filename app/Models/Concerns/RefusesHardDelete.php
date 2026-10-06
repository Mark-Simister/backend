<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Admin Content & Media Operations entities are archived, retired or withdrawn - never
 * hard-deleted (design record sections 7 and 11). Any model delete is refused.
 */
trait RefusesHardDelete
{
    public static function bootRefusesHardDelete(): void
    {
        static::deleting(function () {
            throw new LogicException(static::class . ' rows are never hard-deleted; archive, retire or withdraw instead.');
        });
    }

    public function delete()
    {
        throw new LogicException(static::class . ' rows are never hard-deleted; archive, retire or withdraw instead.');
    }
}
